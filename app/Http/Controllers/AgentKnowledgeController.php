<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class AgentKnowledgeController extends Controller
{
    private const UPLOAD_ENDPOINT = 'https://n8n-new.chiefaiofficer.id/webhook/Upload';

    public function store(Request $request, Agent $agent): JsonResponse
    {
        $this->ensureOwnership($request, $agent);

<<<<<<< HEAD
        $validated = Validator::make([
            'files' => $this->normalizeUploadedFiles($request->file('files')),
        ], [
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['file', 'mimes:pdf,doc,docx,odt,ppt,pptx,odp', 'max:20480'],
        ])->validate();

        foreach ($validated['files'] as $uploadedFile) {
            $uuid = (string) Str::uuid();
            $workingDir = "tmp/agent-knowledge/{$uuid}";
            Storage::makeDirectory($workingDir);

            $extension = strtolower($uploadedFile->getClientOriginalExtension() ?: $uploadedFile->extension());
            $sourceName = 'source.'.$extension;
            $storedRelativePath = $uploadedFile->storeAs($workingDir, $sourceName);
            $sourcePath = Storage::path($storedRelativePath);
            $stream = null;

            try {
                $pdfPath = $extension === 'pdf'
                    ? $sourcePath
                    : $this->convertToPdf($sourcePath);

                $stream = fopen($pdfPath, 'r');

                $response = Http::timeout(120)
                    ->attach('file', $stream, basename($pdfPath))
                    ->post(self::UPLOAD_ENDPOINT, [
                        'UserId' => (string) $request->user()->id,
                        'AgentId' => (string) $agent->id,
                    ]);

=======
        $files = $this->gatherUploadedFiles($request);

        $validated = Validator::make([
            'files' => $files,
        ], [
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['file', 'mimes:pdf,doc,docx,odt,ppt,pptx,odp', 'max:20480'],
        ])->validate();

        foreach ($validated['files'] as $uploadedFile) {
            $uuid = (string) Str::uuid();
            $workingDir = "tmp/agent-knowledge/{$uuid}";
            Storage::makeDirectory($workingDir);

            $extension = strtolower($uploadedFile->getClientOriginalExtension() ?: $uploadedFile->extension());
            $sourceName = 'source.'.$extension;
            $storedRelativePath = $uploadedFile->storeAs($workingDir, $sourceName);
            $sourcePath = Storage::path($storedRelativePath);
            $stream = null;

            try {
                $pdfPath = $extension === 'pdf'
                    ? $sourcePath
                    : $this->convertToPdf($sourcePath);

                $stream = fopen($pdfPath, 'r');

                $response = Http::timeout(120)
                    ->attach('file', $stream, basename($pdfPath))
                    ->post(self::UPLOAD_ENDPOINT, [
                        'UserId' => (string) $request->user()->id,
                        'AgentId' => (string) $agent->id,
                    ]);

>>>>>>> origin/codex/analyze-project-and-provide-explanation-bd23od
                if ($response->failed()) {
                    return response()->json([
                        'message' => 'Unable to upload knowledge base file.',
                        'details' => $response->json(),
                    ], $response->status() ?: 500);
                }
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }

                Storage::deleteDirectory($workingDir);
            }
        }

        return response()->json([
            'message' => 'Knowledge uploaded successfully.',
        ]);
    }

    /**
<<<<<<< HEAD
     * @param  array<int, UploadedFile|null>|UploadedFile|null  $files
=======
     * @param  array<int|string, UploadedFile|array|null>|UploadedFile|null  $files
>>>>>>> origin/codex/analyze-project-and-provide-explanation-bd23od
     * @return array<int, UploadedFile>
     */
    private function normalizeUploadedFiles(null|UploadedFile|array $files): array
    {
        if ($files === null) {
            return [];
        }

<<<<<<< HEAD
        if (! is_array($files)) {
            $files = [$files];
        }

        return array_values(array_filter($files, static fn ($file) => $file instanceof UploadedFile));
=======
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        $normalized = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $normalized[] = $file;
                continue;
            }

            if (is_array($file)) {
                $normalized = array_merge($normalized, $this->normalizeUploadedFiles($file));
            }
        }

        return array_values($normalized);
    }

    private function gatherUploadedFiles(Request $request): array
    {
        $candidates = [
            $request->file('files'),
            $request->file('file'),
            data_get($request->allFiles(), 'files'),
        ];

        $collected = [];
        $seen = [];

        foreach ($candidates as $candidate) {
            foreach ($this->normalizeUploadedFiles($candidate) as $file) {
                $hash = spl_object_hash($file);

                if (isset($seen[$hash])) {
                    continue;
                }

                $seen[$hash] = true;
                $collected[] = $file;
            }
        }

        return $collected;
>>>>>>> origin/codex/analyze-project-and-provide-explanation-bd23od
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


