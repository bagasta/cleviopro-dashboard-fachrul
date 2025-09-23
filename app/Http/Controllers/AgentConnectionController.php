<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AgentConnectionController extends Controller
{
    private const BASE_ENDPOINT = 'https://wapi-cleviopro.chiefaiofficer.id/sessions';

    public function store(Request $request, Agent $agent): JsonResponse
    {
        $this->ensureOwnership($request, $agent);

        return $this->forwardRequest(
            method: 'POST',
            url: self::BASE_ENDPOINT,
            payload: $this->payload($request, $agent)
        );
    }

    public function destroy(Request $request, Agent $agent): JsonResponse
    {
        $this->ensureOwnership($request, $agent);

        return $this->forwardRequest(
            method: 'DELETE',
            url: self::BASE_ENDPOINT.'/'.urlencode((string) $agent->id)
        );
    }

    public function reconnect(Request $request, Agent $agent): JsonResponse
    {
        $this->ensureOwnership($request, $agent);

        return $this->forwardRequest(
            method: 'POST',
            url: self::BASE_ENDPOINT.'/'.urlencode((string) $agent->id).'/reconnect',
            payload: $this->payload($request, $agent)
        );
    }

    private function forwardRequest(string $method, string $url, ?array $payload = null): JsonResponse
    {
        $request = Http::timeout(120)
            ->withHeaders(['Content-Type' => 'application/json']);

        $response = match (strtoupper($method)) {
            'DELETE' => $request->delete($url),
            default => $request->send($method, $url, ['json' => $payload ?? []]),
        };

        if ($response->failed()) {
            return response()->json([
                'message' => 'Unable to process WhatsApp session request.',
                'details' => $response->json(),
            ], $response->status() ?: 500);
        }

        $json = $response->json();

        return response()->json($json ?? []);
    }

    private function payload(Request $request, Agent $agent): array
    {
        return [
            'userId' => $request->user()->id,
            'agentId' => (string) $agent->id,
            'agentName' => $agent->agent_name ?? $agent->nama_model,
        ];
    }

    private function ensureOwnership(Request $request, Agent $agent): void
    {
        if ($agent->user_id !== $request->user()->id) {
            abort(403);
        }
    }
}
