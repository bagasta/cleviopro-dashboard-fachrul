<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AgentController extends Controller
{
    private const AVAILABLE_TOOL_OPTIONS = [
        'calc' => 'Calculator',
        'docs' => 'Google Docs',
        'gmaps' => 'Google Maps',
        'websearch' => 'Web Search',
    ];

    public function edit(Request $request, Agent $agent): View
    {
        $this->ensureOwnership($request, $agent);


        $rawTools = $this->parseTools($agent->tools);

        return view('agents.edit', [
            'agent' => $agent,
            'availableTools' => self::AVAILABLE_TOOL_OPTIONS,
            'selectedTools' => $this->selectedToolKeys($rawTools),
            'hasGmail' => $this->hasGmailIntegration($rawTools),
        ]);
    }

    public function update(Request $request, Agent $agent): RedirectResponse
    {
        $this->ensureOwnership($request, $agent);

        $validated = $request->validate([
            'agent_name' => ['nullable', 'string', 'max:255'],
            'system_message' => ['required', 'string'],
            'tools' => ['nullable', 'array'],
            'tools.*' => ['in:'.implode(',', array_keys(self::AVAILABLE_TOOL_OPTIONS))],
        ]);

        $selectedTools = $validated['tools'] ?? [];
        $rawTools = $this->parseTools($agent->tools);

        $agent->update([
            'agent_name' => $validated['agent_name'] !== '' ? $validated['agent_name'] : null,
            'system_message' => $validated['system_message'],
            'tools' => $this->encodeTools($selectedTools, $this->preservedTools($rawTools)),
        ]);
        
        $this->warmAgentSilently($agent->id, $request->user()->id);

        return redirect()
            ->route('dashboard')
            ->with('status', 'agent-updated');
    }

    public function destroy(Request $request, Agent $agent): RedirectResponse
    {
        $this->ensureOwnership($request, $agent);

        $agent->delete();

        return redirect()
            ->route('dashboard')
            ->with('status', 'agent-deleted');
    }

    private function ensureOwnership(Request $request, Agent $agent): void
    {
        if ($agent->user_id !== $request->user()->id) {
            abort(403);
        }
    }

    private function parseTools(?string $tools): array
    {
        if (! $tools) {
            return [];
        }

        $decoded = json_decode($tools, true);

        if (is_array($decoded)) {
            $items = $decoded;
        } else {
            $items = explode(',', $tools);
        }

        $cleaned = [];

        foreach ($items as $item) {
            $value = trim((string) $item);

            if ($value !== '') {
                $cleaned[] = $value;
            }
        }

        return $cleaned;
    }

    private function selectedToolKeys(array $tools): array
    {
        $selected = [];

        foreach ($tools as $tool) {
            $normalized = strtolower($tool);

            if (array_key_exists($normalized, self::AVAILABLE_TOOL_OPTIONS)) {
                $selected[] = $normalized;
            }
        }

        return array_values(array_unique($selected));
    }

    private function hasGmailIntegration(array $tools): bool
    {
        foreach ($tools as $tool) {
            if (stripos($tool, 'gmail') !== false) {
                return true;
            }
        }

        return false;
    }

    private function preservedTools(array $tools): array
    {
        $preserved = [];

        foreach ($tools as $tool) {
            $normalized = strtolower($tool);

            if (array_key_exists($normalized, self::AVAILABLE_TOOL_OPTIONS)) {
                continue;
            }

            $preserved[] = $tool;
        }

        return $preserved;
    }

    private function encodeTools(array $selectedTools, array $preservedTools): ?string
    {
        $selected = array_values(array_unique(array_map('strval', $selectedTools)));
        $payload = array_merge($preservedTools, $selected);

        if (empty($payload)) {
            return null;
        }

        return json_encode($payload);
    }

    private function warmAgentSilently(int $agentId, int $userId): void
    {
        try {
            Http::timeout(3)          // kecil agar view tidak nunggu lama
                ->asJson()
                // ->withToken(config('services.langchain.token')) // aktifkan jika endpoint butuh auth
                ->post("https://langchain.chiefaiofficer.id/agents/{$agentId}/warm", [
                    'trigger' => 'edit_view',
                    'user_id' => $userId,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Warm endpoint failed', [
                'agent_id' => $agentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
