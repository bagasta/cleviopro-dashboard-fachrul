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
use Symfony\Component\Process\Process;

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

                $sourceName = $normalizedBaseName.'.'.$extension;
                $storedRelativePath = $uploadedFile->storeAs($workingDir, $sourceName);
                $sourcePath = Storage::path($storedRelativePath);

                $pdfPath = $extension === 'pdf'
                    ? $sourcePath
                    : $this->convertToPdf($sourcePath);

                $uploadFilename = $extension === 'pdf'
                    ? ($originalClientName !== '' ? $originalClientName : $sourceName)
                    : ($originalBaseName !== '' ? $originalBaseName.'.pdf' : 'document.pdf');

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

            foreach ($preparedFiles as $index => $fileMeta) {
                $stream = fopen($fileMeta['pdf_path'], 'r');

                if ($stream === false) {
                    throw new \RuntimeException(sprintf('Unable to open file stream for %s.', $fileMeta['pdf_path']));
                }

                $streams[] = $stream;

                $pendingRequest = $pendingRequest->attach(
                    sprintf('files[%d]', $index),
                    $stream,
                    $fileMeta['sent_filename']
                );
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

    private function convertToPdf(string $sourcePath): string
    {
        $outputDir = dirname($sourcePath);
        $binary = $this->resolveLibreOfficeBinary();

        $process = new Process([
            $binary,
            '--headless',
            '--convert-to', 'pdf',
            '--outdir', $outputDir,
            $sourcePath,
        ]);

        $process->setTimeout(120);
        try {
            $process->run();
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Unable to run LibreOffice for PDF conversion. '.($exception->getMessage() ?: 'Unknown error.'), 0, $exception);
        }

        if (! $process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput() ?: $process->getOutput());
            $message = $errorOutput !== ''
                ? sprintf('LibreOffice failed to convert the document: %s', $errorOutput)
                : 'LibreOffice failed to convert the document to PDF. Ensure LibreOffice is installed and reachable.';

            throw new \RuntimeException($message);
        }

        $pdfPath = $outputDir.'/'.pathinfo($sourcePath, PATHINFO_FILENAME).'.pdf';

        if (! file_exists($pdfPath)) {
            throw new \RuntimeException('Converted PDF file could not be located.');
        }

        return $pdfPath;
    }

    private function resolveLibreOfficeBinary(): string
    {
        $candidates = array_filter([
            env('LIBREOFFICE_BINARY'),
            env('SOFFICE_PATH'),
        ]);

        if (PHP_OS_FAMILY === 'Windows') {
            $candidates[] = 'C:\\Program Files\\LibreOffice\\program\\soffice.exe';
            $candidates[] = 'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe';
        } else {
            $candidates[] = '/usr/bin/soffice';
            $candidates[] = '/usr/local/bin/soffice';
            $candidates[] = '/snap/bin/libreoffice';
        }

        $candidates[] = 'soffice';
        $candidates[] = 'libreoffice';

        foreach ($candidates as $candidate) {
            if ($this->isCommandAvailable($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('LibreOffice executable was not found. Install LibreOffice or set the LIBREOFFICE_BINARY environment variable with the full path to soffice.');
    }

    private function isCommandAvailable(string $command): bool
    {
        if (str_contains($command, DIRECTORY_SEPARATOR)) {
            return is_file($command);
        }

        $process = PHP_OS_FAMILY === 'Windows'
            ? new Process(['where', $command])
            : new Process(['which', $command]);

        $process->setTimeout(120);
        $process->run();

        return $process->isSuccessful();
    }

    private function ensureOwnership(Request $request, Agent $agent): void
    {
        if ($agent->user_id !== $request->user()->id) {
            abort(403);
        }
    }
}


