<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class AgentTrialController extends Controller
{
    private const BASE_ENDPOINT = 'https://langchain.chiefaiofficer.id/';
    // HAPUS: private const OPENAI_KEY = '...';

    public function show(Request $request, Agent $agent): View
    {
        $this->ensureOwnership($request, $agent);

        $apiKey = $request->user()->apiKeys()
            ->where('active', true)
            ->orderByDesc('created_at')
            ->first();

        return view('agents.chat', [
            'agent' => $agent,
            'hasApiKey' => (bool) $apiKey,
        ]);
    }

    public function store(Request $request, Agent $agent): JsonResponse
    {
        $this->ensureOwnership($request, $agent);

        $apiKey = $request->user()->apiKeys()
            ->where('active', true)
            ->orderByDesc('created_at')
            ->first();

        if (! $apiKey) {
            return response()->json([
                'message' => 'API key not found. Please create one before trying the agent.',
            ], 422);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'sessionId' => ['nullable', 'string', 'max:100'],
        ]);

        $endpoint = rtrim(self::BASE_ENDPOINT, '/').'/agents/'.$agent->id.'/run';

        // Ambil dari config/env
        $openaiKey = config('services.openai.key') ?? env('OPENAI_API_KEY');

        if (empty($openaiKey)) {
            return response()->json([
                'message' => 'OPENAI_API_KEY belum diset di environment.',
            ], 500);
        }

        $payload = [
            'message' => $validated['message'],
            'openai_api_key' => $openaiKey,
            'sessionId' => $validated['sessionId'] ?? ($request->user()->phone_number ?? 'unknown'),
            'memory_enable' => true,
            'context_memory' => '100',
            'rag_enable' => true,
        ];

        $response = Http::timeout(60)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$apiKey->key_hash,
            ])
            ->post($endpoint, $payload);

        if ($response->failed()) {
            return response()->json([
                'message' => 'Agent response failed.',
                'details' => $response->json(),
            ], $response->status() ?: 500);
        }

        return response()->json($response->json());
    }

    private function ensureOwnership(Request $request, Agent $agent): void
    {
        if ($agent->user_id !== $request->user()->id) {
            abort(403);
        }
    }
}
