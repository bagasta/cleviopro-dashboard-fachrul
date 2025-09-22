<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class AgentKnowledgeController extends Controller
{
    private const UPLOAD_ENDPOINT = 'https://n8n-new.chiefaiofficer.id/webhook/Upload';

    public function store(Request $request, Agent $agent): JsonResponse
    {
        $this->ensureOwnership($request, $agent);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,docx,ppt,pptx', 'max:20480'],
        ]);

        $uploadedFile = $validated['file'];
        $uuid = (string) Str::uuid();
        $workingDir = "tmp/agent-knowledge/{$uuid}";
        Storage::makeDirectory($workingDir);

        $extension = strtolower($uploadedFile->getClientOriginalExtension() ?: $uploadedFile->extension());
        $sourceName = 'source.'.$extension;
        $storedRelativePath = $uploadedFile->storeAs($workingDir, $sourceName);
        $sourcePath = Storage::path($storedRelativePath);

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

            if (is_resource($stream)) {
                fclose($stream);
            }

            if ($response->failed()) {
                return response()->json([
                    'message' => 'Unable to upload knowledge base file.',
                    'details' => $response->json(),
                ], $response->status() ?: 500);
            }

            return response()->json([
                'message' => 'Knowledge uploaded successfully.',
            ]);
        } finally {
            Storage::deleteDirectory($workingDir);
        }
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

        $process->setTimeout(5);
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


