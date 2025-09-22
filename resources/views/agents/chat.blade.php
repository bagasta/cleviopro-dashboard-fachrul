<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Agent Sandbox') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900" data-agent-chat data-send-endpoint="{{ route('agents.chat.send', $agent) }}" data-agent-name="{{ $agent->agent_name ?? $agent->nama_model }}">
                    <h3 class="text-lg font-semibold text-gray-800">{{ $agent->agent_name ?? $agent->nama_model }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ __('Test your agent without WhatsApp. Messages are routed through the sandbox endpoint.') }}</p>

                    @if (! $hasApiKey)
                        <div class="mt-4 bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg">
                            {{ __('No active API key was found for your account. Please create one before using the try feature.') }}
                        </div>
                    @endif

                    <div class="mt-6 space-y-4">
                        <div class="border border-gray-200 rounded-lg h-80 overflow-y-auto p-4 bg-gray-50 text-sm" id="agent-chat-log">
                            <p class="text-gray-500" id="agent-chat-empty">{{ __('Start the conversation by sending a message below.') }}</p>
                        </div>

                        <form id="agent-chat-form" class="space-y-3" @if (! $hasApiKey) data-disabled="true" @endif>
                            @csrf
                            <div>
                                <label for="agent-chat-message" class="sr-only">{{ __('Message') }}</label>
                                <textarea id="agent-chat-message" name="message" rows="3" class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="{{ __('Type your message...') }}" @if (! $hasApiKey) disabled @endif></textarea>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="text-xs text-gray-400" id="agent-chat-status"></div>
                                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" @if (! $hasApiKey) disabled @endif>
                                    {{ __('Send Message') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
