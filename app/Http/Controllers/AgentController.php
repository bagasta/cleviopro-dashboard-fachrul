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
    private const TOOL_ALIAS_MAP = [
        'google_gmail' => [
            'gmail',
            'google_gmail',
            'gmail_search',
            'gmail_send_message',
            'gmail_read_messages',
            'gmail_get_message',
            'gmail_read',
            'gmail_read_inbox',
            'gmail_get',
            'gmail_message',
            'send_email',
            'email_send',
        ],
        'websearch' => [
            'websearch',
            'web_search',
            'web search',
        ],
        'spreadsheet' => [
            'spreadsheet',
        ],
        'google_docs' => [
            'google_docs',
            'docs',
            'docs_create',
            'docs_get',
            'docs_append',
            'docs_export_pdf',
        ],
        'google_calendar' => [
            'calendar',
            'google_calendar',
            'create_calendar_event',
            'list_calendar_events',
            'get_calendar_event',
            'update_calendar_event',
            'delete_calendar_event',
            'search_calendar_events',
            'get_free_busy',
            'list_calendars',
        ],
        'google_maps' => [
            'google_maps',
            'maps',
            'maps_api',
            'maps_geocode',
            'maps_directions',
            'maps_distance_matrix',
            'maps_nearby',
        ],
        'calculator' => [
            'calculator',
            'calc',
            'calc_service',
        ],
    ];

    private const AVAILABLE_TOOL_OPTIONS = [
        'google_docs' => 'Google Docs',
        'google_maps' => 'Google Maps',
        'calculator' => 'Calculator',
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
        $normalizedTools = $this->buildNormalizedLookup($tools);

        foreach (self::TOOL_ALIAS_MAP as $key => $aliases) {
            if (! array_key_exists($key, self::AVAILABLE_TOOL_OPTIONS)) {
                continue;
            }

            foreach ($aliases as $alias) {
                if (isset($normalizedTools[$this->normalizeToolKey($alias)])) {
                    $selected[] = $key;
                    break;
                }
            }
        }

        $legacyMap = [
            'gmaps' => 'google_maps',
        ];

        foreach ($legacyMap as $legacyKey => $targetKey) {
            if (isset($normalizedTools[$legacyKey]) && array_key_exists($targetKey, self::AVAILABLE_TOOL_OPTIONS)) {
                $selected[] = $targetKey;
            }
        }

        return array_values(array_unique($selected));
    }

    private function hasGmailIntegration(array $tools): bool
    {
        $normalizedTools = $this->buildNormalizedLookup($tools);
        $gmailAliases = self::TOOL_ALIAS_MAP['google_gmail'] ?? [];

        foreach ($gmailAliases as $alias) {
            if (isset($normalizedTools[$this->normalizeToolKey($alias)])) {
                return true;
            }
        }

        return false;
    }

    private function preservedTools(array $tools): array
    {
        $dictionaryLookup = $this->dictionaryAliasLookup();
        $preserved = [];
        $seen = [];

        foreach ($tools as $tool) {
            $normalized = $this->normalizeToolKey($tool);

            if ($normalized === '' || isset($dictionaryLookup[$normalized]) || isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $preserved[] = $tool;
        }

        return $preserved;
    }

    private function encodeTools(array $selectedTools, array $preservedTools): ?string
    {
        $payload = [];
        $seen = [];

        $append = static function (string $value) use (&$payload, &$seen): void {
            $normalized = self::normalizeStaticToolKey($value);

            if ($normalized === '' || isset($seen[$normalized])) {
                return;
            }

            $seen[$normalized] = true;
            $payload[] = $value;
        };

        foreach ($selectedTools as $toolKey) {
            $aliases = self::TOOL_ALIAS_MAP[$toolKey] ?? [];

            if (empty($aliases)) {
                $append((string) $toolKey);
                continue;
            }

            foreach ($aliases as $alias) {
                $append($alias);
            }
        }

        foreach ($preservedTools as $tool) {
            $append($tool);
        }

        if (empty($payload)) {
            return null;
        }

        return json_encode($payload);
    }

    private function buildNormalizedLookup(array $tools): array
    {
        $lookup = [];

        foreach ($tools as $tool) {
            $normalized = $this->normalizeToolKey($tool);

            if ($normalized !== '') {
                $lookup[$normalized] = true;
            }
        }

        return $lookup;
    }

    private function dictionaryAliasLookup(): array
    {
        $lookup = [];

        foreach (self::TOOL_ALIAS_MAP as $aliases) {
            foreach ($aliases as $alias) {
                $normalized = $this->normalizeToolKey($alias);

                if ($normalized !== '') {
                    $lookup[$normalized] = true;
                }
            }
        }

        return $lookup;
    }

    private function normalizeToolKey(string $value): string
    {
        return self::normalizeStaticToolKey($value);
    }

    private static function normalizeStaticToolKey(string $value): string
    {
        $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value);
        $value = str_replace(['-', ' '], '_', $value);
        $value = strtolower($value ?? '');
        $value = preg_replace('/_+/', '_', $value);

        return trim((string) $value, '_');
    }

    private function warmAgentSilently(int $agentId, int $userId): void
    {
        try {
            Http::timeout(10)          // kecil agar view tidak nunggu lama
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
