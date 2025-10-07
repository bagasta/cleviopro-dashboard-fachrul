<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                    {{ session('status') === 'agent-updated' ? __('Agent updated successfully.') : (session('status') === 'agent-deleted' ? __('Agent deleted successfully.') : session('status')) }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    @if ($user->status === 'gratis')
                        <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg">
                            {{ __('You still have a free account. Please make a payment to enjoy other features. Please contact wa.me/6283890930647 to upgrade your account.') }}
                        </div>
                    @else
                        @if ($agents->isEmpty())
                            <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg">
                                {{ __('You do not have an agent yet. Please create an agent.') }}
                            </div>
                        @else

                                                  @php
                                $toolAliasMap = [
                                    'gmail' => ['google_gmail', 'gmail', 'gmail_read_messages', 'gmail_get_message', 'gmail_send_message'],
                                    'docs' => ['google_docs', 'docs'],
                                    'maps' => ['google_maps', 'gmaps', 'maps', 'maps_api'],
                                    'calculator' => ['calculator', 'calc_service', 'calc'],
                                    'websearch' => ['web_search', 'serp_api', 'websearch', 'web search'],
                                ];
                                $toolIconMap = [
                                    'gmail' => ['label' => 'Gmail', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 2v.511l-8 5.333-8-5.333V6Zm-8 7.156L4 7.823V18h16V7.823Z"/></svg>'],
                                    'docs' => ['label' => 'Docs', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-sky-500" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16c0 1.103.897 2 2 2h12a2 2 0 0 0 2-2V8Zm4 18H6V4h7v5h5Zm-2-9H8v-2h8Zm0 4H8v-2h8Zm-4 4H8v-2h4Z"/></svg>'],
                                    'maps' => ['label' => 'Maps', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 14.5 9 2.5 2.5 0 0 1 12 11.5Z"/></svg>'],
                                    'calculator' => ['label' => 'Calculator', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-emerald-500" viewBox="0 0 24 24" fill="currentColor"><path d="M17 2H7a2 2 0 0 0-2 2v16c0 1.103.897 2 2 2h10a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2Zm0 18H7V4h10ZM9 7h6v2H9Zm0 4h2v2H9Zm4 0h2v2h-2Zm-4 4h2v2H9Zm4 0h2v2h-2Z"/></svg>'],
                                    'websearch' => ['label' => 'Web Search', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-500" viewBox="0 0 24 24" fill="currentColor"><path d="M21 11h-1.07a7.955 7.955 0 0 0-1.89-4.556l.76-.761 1.414 1.414 1.414-1.414-4.242-4.243-1.414 1.414 1.414 1.414-.76.761A7.955 7.955 0 0 0 13 3.07V2h-2v1.07a7.955 7.955 0 0 0-4.556 1.89l-.761-.76 1.414-1.414L5.443.929 1.2 5.172l1.414 1.414 1.414-1.414.761.76A7.955 7.955 0 0 0 3.07 11H2v2h1.07a7.955 7.955 0 0 0 1.89 4.556l-.76.761-1.414-1.414-1.414 1.414 4.243 4.242 1.414-1.414-1.414-1.414.76-.761A7.955 7.955 0 0 0 11 20.93V22h2v-1.07a7.955 7.955 0 0 0 4.556-1.89l.761.76-1.414 1.414 1.414 1.414 4.242-4.243-1.414-1.414-1.414 1.414-.761-.76A7.955 7.955 0 0 0 19.93 13H21Zm-9 6a6 6 0 1 1 6-6 6.007 6.007 0 0 1-6 6Z"/></svg>'],
                                ];
                                $displayOrder = ['gmail', 'docs', 'maps', 'calculator', 'websearch'];
                                $normalizeTool = static function (string $value): string {
                                    $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value);
                                    $value = str_replace(['-', ' '], '_', $value);
                                    $value = preg_replace('/_+/', '_', strtolower($value));

                                    return trim($value, '_');
                                };
                            @endphp

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Agent Name') }}</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('System Message') }}</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Tools') }}</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach ($agents as $agent)
                                                                                    @php
                                                $rawTools = $agent->tools;
                                                $decodedTools = [];

                                                if (is_string($rawTools) && $rawTools !== '') {
                                                    $jsonTools = json_decode($rawTools, true);
                                                    if (is_array($jsonTools)) {
                                                        $decodedTools = array_filter(array_map('strval', $jsonTools));
                                                    } else {
                                                        $decodedTools = array_filter(array_map('trim', explode(',', $rawTools)));
                                                    }
                                                } elseif (is_array($rawTools)) {
                                                    $decodedTools = array_filter(array_map('strval', $rawTools));
                                                }

                                                $normalizedSet = [];
                                                foreach ($decodedTools as $tool) {
                                                    $normalized = $normalizeTool($tool);
                                                    if ($normalized !== '') {
                                                        $normalizedSet[$normalized] = true;
                                                    }
                                                }

                                                $services = [];
                                                foreach ($displayOrder as $serviceKey) {
                                                    $aliases = $toolAliasMap[$serviceKey] ?? [];

                                                    foreach ($aliases as $alias) {
                                                        if (isset($normalizedSet[$normalizeTool($alias)])) {
                                                            if (isset($toolIconMap[$serviceKey])) {
                                                                $services[] = $serviceKey;
                                                            }
                                                            break;
                                                        }
                                                    }
                                                }

                                                $services = array_values(array_unique($services));
                                                $connectionStatus = $agent->whatsappUser?->status ?? 'disconnected';
                                            @endphp
                                            <tr>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{ $agent->agent_name ?? $agent->nama_model }}
                                                </td>
                                                <td class="px-4 py-4 text-sm text-gray-700 max-w-xs">
                                                    <div class="block max-h-24 overflow-hidden">{{ $agent->system_message }}</div>
                                                </td>
            <td class="px-4 py-4 text-sm text-gray-700">
                                                    <div class="flex flex-wrap gap-2">
                                                        @forelse ($services as $service)
                                                            @php $icon = $toolIconMap[$service] ?? null; @endphp
                                                            @if (! $icon)
                                                                @continue
                                                            @endif
                                                            <span class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700" title="{{ $icon['label'] }}">
                                                                {!! $icon['icon'] !!}
                                                                {{ $icon['label'] }}
                                                            </span>
                                                        @empty
                                                            <span class="text-xs text-gray-400">{{ __('No tools available') }}</span>
                                                        @endforelse
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 text-sm text-gray-700">
                                                    @php $showConnect = $connectionStatus !== 'connected'; @endphp
                                                    <div class="flex flex-wrap gap-2" data-agent-buttons="{{ $agent->id }}" data-session-state="{{ $connectionStatus }}">
                                                        <a href="{{ route('agents.edit', $agent) }}" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                            {{ __('Edit') }}
                                                        </a>
                                                        <form method="POST" action="{{ route('agents.destroy', $agent) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this agent?') }}');">                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="inline-flex items-center px-3 py-2 border border-red-200 rounded-md text-xs font-medium text-red-600 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                                                {{ __('Delete') }}
                                                            </button>
                                                        </form>

                                                        <a href="{{ route('agents.chat', $agent) }}" target="_blank" class="inline-flex items-center px-3 py-2 border border-indigo-200 rounded-md text-xs font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                            {{ __('Try') }}
                                                        </a>

                                                        <button type="button" class="inline-flex items-center px-3 py-2 border border-sky-200 rounded-md text-xs font-medium text-sky-600 bg-sky-50 hover:bg-sky-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-400 js-agent-knowledge" data-endpoint="{{ route('agents.knowledge.store', $agent) }}" data-agent-id="{{ $agent->id }}" data-agent-name="{{ $agent->agent_name ?? $agent->nama_model }}" data-user-id="{{ $user->id }}">
                                                            {{ __('Add Knowledge') }}
                                                        </button>

                                                        <button type="button" class="inline-flex items-center px-3 py-2 border border-emerald-200 rounded-md text-xs font-medium text-emerald-600 bg-emerald-50 hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 js-agent-session {{ $showConnect ? '' : 'hidden' }}" data-agent-action="connect" data-endpoint="{{ route('agents.sessions.store', $agent) }}" data-agent-id="{{ $agent->id }}" data-agent-name="{{ $agent->agent_name ?? $agent->nama_model }}" data-user-id="{{ $user->id }}">
                                                            {{ __('Connect') }}
                                                        </button>

                                                        <button type="button" class="inline-flex items-center px-3 py-2 border border-red-200 rounded-md text-xs font-medium text-red-600 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 js-agent-session {{ $connectionStatus === 'connected' ? '' : 'hidden' }}" data-agent-action="disconnect" data-endpoint="{{ route('agents.sessions.destroy', $agent) }}" data-agent-id="{{ $agent->id }}" data-agent-name="{{ $agent->agent_name ?? $agent->nama_model }}" data-user-id="{{ $user->id }}">
                                                            {{ __('Disconnect') }}
                                                        </button>
                                                        <button type="button" class="inline-flex items-center px-3 py-2 border border-amber-200 rounded-md text-xs font-medium text-amber-600 bg-amber-50 hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 js-agent-session {{ $connectionStatus === 'connected' ? '' : 'hidden' }}" data-agent-action="reconnect" data-endpoint="{{ route('agents.sessions.reconnect', $agent) }}" data-agent-id="{{ $agent->id }}" data-agent-name="{{ $agent->agent_name ?? $agent->nama_model }}" data-user-id="{{ $user->id }}">
                                                            {{ __('Reconnect') }}
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div id="agent-connect-modal" class="fixed inset-0 hidden items-center justify-center bg-black/60 z-40">
        <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full space-y-4">
            <h3 class="text-lg font-semibold text-gray-900" id="agent-connect-title"></h3>
            <div class="text-sm text-gray-600 space-y-2" id="agent-connect-message"></div>
            <div class="flex justify-center" id="agent-connect-qr"></div>
            <div class="flex justify-end gap-2">
                <button type="button" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800" id="agent-connect-close">{{ __('Close') }}</button>
            </div>
        </div>
    </div>

    <div id="agent-knowledge-modal" class="fixed inset-0 hidden items-center justify-center bg-black/60 z-40">
        <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full space-y-4">
            <div class="flex items-start justify-between">
                <h3 class="text-lg font-semibold text-gray-900" id="agent-knowledge-title"></h3>
                <button type="button" class="text-gray-400 hover:text-gray-600" id="agent-knowledge-close">&times;</button>
            </div>
            <p class="text-sm text-gray-600" id="agent-knowledge-description"></p>
            <form id="agent-knowledge-form" class="space-y-4">
                @csrf
                <div>
                    <label for="agent-knowledge-file" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide">{{ __('Knowledge File') }}</label>
                    <input id="agent-knowledge-file" name="files[]" type="file" accept=".pdf,.doc,.docx,.odt,.ppt,.pptx,.odp" class="mt-1 block w-full text-sm text-gray-700 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" multiple required>
                    <p class="mt-1 text-xs text-gray-500">{{ __('Accepted formats: .pdf, .doc, .docx, .odt, .ppt, .pptx, .odp (max 20 MB per file, up to 20 files).') }}</p>
                    <p class="mt-2 text-xs text-red-600 hidden" id="agent-knowledge-error"></p>
                </div>
                <div class="text-sm text-gray-600" id="agent-knowledge-status"></div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800" id="agent-knowledge-cancel">{{ __('Cancel') }}</button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500" id="agent-knowledge-submit">{{ __('Upload') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div id="floating-chat" class="fixed bottom-6 right-6 z-30" data-endpoint="{{ route('support.chat.send') }}">
        <button type="button" id="floating-chat-toggle" class="rounded-full bg-indigo-600 text-white px-4 py-3 shadow-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            {{ __('Chat with Support Agent') }}
        </button>

        <div id="floating-chat-panel" class="hidden mt-4 w-80 bg-white shadow-xl rounded-lg border border-gray-200">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                <h4 class="text-sm font-semibold text-gray-800">{{ __('Support Chat') }}</h4>
                <button type="button" id="floating-chat-close" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <div class="px-4 py-3 space-y-3">
                <div id="floating-chat-messages" class="h-48 overflow-y-auto space-y-2 text-sm"></div>
                <form id="floating-chat-form" class="space-y-2">
                    @csrf
                    <textarea name="message" id="floating-chat-input" rows="2" class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" placeholder="{{ __('Type your message...') }}" required></textarea>
                    <button type="submit" class="w-full inline-flex justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        {{ __('Send') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

