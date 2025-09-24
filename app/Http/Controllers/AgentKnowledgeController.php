<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AgentKnowledgeController extends Controller
{
    private const UPLOAD_ENDPOINT = 'https://n8n-new.chiefaiofficer.id/webhook/Upload';
    public function store(Request $request, Agent $agent): JsonResponse
    {
        $this->ensureOwnership($request, $agent);

        $files = $this->validatedUploads($request);

        $preparedFiles = [];

        foreach ($files as $uploadedFile) {
            $uuid = (string) Str::uuid();
            $workingDir = "tmp/agent-knowledge/{$uuid}";
            Storage::makeDirectory($workingDir);

            try {
                $extension = strtolower($uploadedFile->getClientOriginalExtension() ?: $uploadedFile->extension());
                $originalClientName = trim($uploadedFile->getClientOriginalName());
                $originalBaseName = pathinfo($originalClientName, PATHINFO_FILENAME) ?: 'document';
                $normalizedBaseName = Str::slug($originalBaseName) ?: 'document';

                $sourceName = $normalizedBaseName . '.' . $extension;
                $storedRelativePath = $uploadedFile->storeAs($workingDir, $sourceName);
                $sourcePath = Storage::path($storedRelativePath);

                // === GANTI: konversi via CloudConvert, bukan LibreOffice ===
                $pdfPath = $extension === 'pdf'
                    ? $sourcePath
                    : $this->convertToPdfViaCloudConvert($sourcePath);

                $uploadFilename = $extension === 'pdf'
                    ? ($originalClientName !== '' ? $originalClientName : $sourceName)
                    : ($originalBaseName !== '' ? $originalBaseName . '.pdf' : 'document.pdf');

                $preparedFiles[] = [
                    'original_filename' => $originalClientName !== '' ? $originalClientName : $uploadedFile->getClientOriginalName(),
                    'sent_filename' => $uploadFilename,
                    'pdf_path' => $pdfPath,
                    'working_dir' => $workingDir,
                ];
            } catch (\Throwable $exception) {
                Storage::deleteDirectory($workingDir);
                throw $exception;
            }
        }

        $streams = [];

        try {
            $pendingRequest = Http::timeout(120);
            $idx = 1;

            foreach ($preparedFiles as $fileMeta) {
                $stream = fopen($fileMeta['pdf_path'], 'r');
                if ($stream === false) {
                    throw new \RuntimeException(sprintf('Unable to open file stream for %s.', $fileMeta['pdf_path']));
                }
                $streams[] = $stream;

                // Gunakan nama unik per part agar n8n tidak menimpa
                $pendingRequest = $pendingRequest->attach(
                    "file{$idx}",
                    $stream,
                    $fileMeta['sent_filename']
                );
                $idx++;
            }

            $filesPayload = array_map(static function (array $fileMeta): array {
                return [
                    'original_filename' => $fileMeta['original_filename'],
                    'sent_filename' => $fileMeta['sent_filename'],
                ];
            }, $preparedFiles);

            Log::info('Uploading knowledge files to n8n.', [
                'agent_id' => $agent->id,
                'user_id' => $request->user()->id,
                'file_count' => count($filesPayload),
                'files' => $filesPayload,
            ]);

            $response = $pendingRequest->post(self::UPLOAD_ENDPOINT, [
                'UserId' => (string) $request->user()->id,
                'AgentId' => (string) $agent->id,
            ]);

            if ($response->failed()) {
                Log::warning('Knowledge upload failed.', [
                    'agent_id' => $agent->id,
                    'user_id' => $request->user()->id,
                    'file_count' => count($filesPayload),
                    'files' => $filesPayload,
                    'status' => $response->status(),
                    'response_body' => $response->body(),
                ]);

                return response()->json([
                    'message' => 'Unable to upload knowledge base file.',
                    'details' => $response->json(),
                ], $response->status() ?: 500);
            }

            Log::info('Knowledge upload succeeded.', [
                'agent_id' => $agent->id,
                'user_id' => $request->user()->id,
                'file_count' => count($filesPayload),
                'files' => $filesPayload,
                'status' => $response->status(),
            ]);
        } finally {
            foreach ($streams as $stream) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            foreach ($preparedFiles as $fileMeta) {
                Storage::deleteDirectory($fileMeta['working_dir']);
            }
        }

        return response()->json([
            'message' => 'Knowledge uploaded successfully.',
        ]);
    }

    /**
     * Konversi dokumen ke PDF via CloudConvert
     *
     * @throws \RuntimeException
     */
    private function convertToPdfViaCloudConvert(string $sourcePath): string
    {
        $apiBase = 'https://api.cloudconvert.com/v2';
        $token = env('CLOUDCONVERT_API_KEY');

        $outputDir = dirname($sourcePath);
        $baseName = pathinfo($sourcePath, PATHINFO_FILENAME);
        $inputExt = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        // Buat job: import(upload) -> convert -> export(url)
        $createJobResp = Http::withToken($token)
            ->acceptJson()
            ->post($apiBase . '/jobs', [
                'tasks' => [
                    'import-1' => [
                        'operation' => 'import/upload',
                    ],
                    'convert-1' => [
                        'operation' => 'convert',
                        'input' => ['import-1'],
                        'output_format' => 'pdf',
                        // Optional tuning:
                        // 'filename' => $baseName . '.pdf',
                    ],
                    'export-1' => [
                        'operation' => 'export/url',
                        'input' => ['convert-1'],
                    ],
                ],
            ]);

        if ($createJobResp->failed()) {
            throw new \RuntimeException('CloudConvert: failed to create job. ' . $createJobResp->body());
        }

        $job = $createJobResp->json('data');
        if (!$job) {
            throw new \RuntimeException('CloudConvert: invalid job response.');
        }

        // Ambil info upload form dari task import-1
        $importTask = collect($job['tasks'] ?? [])->firstWhere('name', 'import-1');
        if (!$importTask || empty($importTask['result']['form']['url']) || empty($importTask['result']['form']['parameters'])) {
            throw new \RuntimeException('CloudConvert: upload form not available.');
        }

        $uploadUrl = $importTask['result']['form']['url'];
        $uploadParams = $importTask['result']['form']['parameters'];

        // Upload file sumber ke CloudConvert (multipart, field "file")
        $uploadFileResp = Http::asMultipart()
            ->timeout(120)
            ->post($uploadUrl, array_merge(
                // form fields harus dikirim sebelum file
                collect($uploadParams)->map(function ($v, $k) {
                    return ['name' => $k, 'contents' => (string) $v];
                })->values()->all(),
                [
                    [
                        'name' => 'file',
                        'contents' => fopen($sourcePath, 'r'),
                        'filename' => basename($sourcePath),
                    ],
                ]
            ));

        if ($uploadFileResp->failed()) {
            throw new \RuntimeException('CloudConvert: upload file failed. ' . $uploadFileResp->body());
        }

        // Poll job sampai selesai
        $jobId = $job['id'] ?? null;
        if (!$jobId) {
            throw new \RuntimeException('CloudConvert: job id missing.');
        }

        $maxWaitSeconds = 60; // sesuaikan kebutuhan
        $sleepSeconds = 2;
        $elapsed = 0;
        $lastStatus = null;

        do {
            sleep($sleepSeconds);
            $elapsed += $sleepSeconds;

            $statusResp = Http::withToken($token)->get($apiBase . '/jobs/' . $jobId);
            if ($statusResp->failed()) {
                throw new \RuntimeException('CloudConvert: failed to fetch job status. ' . $statusResp->body());
            }

            $jobData = $statusResp->json('data');
            $lastStatus = $jobData['status'] ?? null;

            if ($lastStatus === 'finished') {
                // Ambil file URL dari export-1
                $exportTask = collect($jobData['tasks'] ?? [])->firstWhere('name', 'export-1');
                $files = $exportTask['result']['files'] ?? [];

                if (empty($files) || empty($files[0]['url'])) {
                    throw new \RuntimeException('CloudConvert: export URL not found.');
                }

                $downloadUrl = $files[0]['url'];

                // Download hasil PDF ke outputDir
                $pdfPath = $outputDir . '/' . $baseName . '.pdf';
                $download = Http::timeout(120)->get($downloadUrl);

                if ($download->failed()) {
                    throw new \RuntimeException('CloudConvert: failed to download converted PDF.');
                }

                file_put_contents($pdfPath, $download->body());

                if (!file_exists($pdfPath)) {
                    throw new \RuntimeException('Converted PDF file could not be located.');
                }

                return $pdfPath;
            }

            if ($lastStatus === 'error' || $lastStatus === 'failed') {
                $failedTask = collect($jobData['tasks'] ?? [])->firstWhere('status', 'error');
                $reason = $failedTask['message'] ?? 'Unknown error';
                throw new \RuntimeException('CloudConvert job failed: ' . $reason);
            }
        } while ($elapsed < $maxWaitSeconds);

        throw new \RuntimeException('CloudConvert: job timeout waiting for conversion to finish. Last status: ' . ($lastStatus ?? 'unknown'));
    }

    /**
     * @return UploadedFile[]
     */
    private function validatedUploads(Request $request): array
    {
        $files = $this->normalizeUploadedFiles($request);

        return validator(
            ['files' => $files],
            [
                'files' => ['required', 'array', 'max:20'],
                'files.*' => ['file', 'mimes:pdf,doc,docx,odt,ppt,pptx,odp', 'max:20480'],
            ]
        )->validate()['files'];
    }

    /**
     * @return UploadedFile[]
     */
    private function normalizeUploadedFiles(Request $request): array
    {
        $allFiles = $request->allFiles();
        $files = $allFiles['files'] ?? null;

        if ($files === null) {
            $files = $request->file('file');
        }

        return $this->flattenFiles($files);
    }

    /**
     * @param  array<int|string, mixed>|UploadedFile|null  $files
     * @return UploadedFile[]
     */
    private function flattenFiles($files): array
    {
        $normalized = [];

        foreach (Arr::wrap($files) as $file) {
            if ($file instanceof UploadedFile) {
                $normalized[] = $file;
                continue;
            }

            if (is_array($file)) {
                $normalized = array_merge($normalized, $this->flattenFiles($file));
            }
        }

        return $normalized;
    }

    private function ensureOwnership(Request $request, Agent $agent): void
    {
        if ($agent->user_id !== $request->user()->id) {
            abort(403);
        }
    }
}
