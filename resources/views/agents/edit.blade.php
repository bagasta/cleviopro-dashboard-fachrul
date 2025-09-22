<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Agent') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('agents.update', $agent) }}" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-input-label for="agent_name" :value="__('Agent Name')" />
                            <x-text-input id="agent_name" name="agent_name" type="text" class="mt-1 block w-full" value="{{ old('agent_name', $agent->agent_name) }}" />
                            <x-input-error class="mt-2" :messages="$errors->get('agent_name')" />
                        </div>

                        <div>
                            <x-input-label for="system_message" :value="__('System Message')" />
                            <textarea id="system_message" name="system_message" rows="5" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">{{ old('system_message', $agent->system_message) }}</textarea>
                            <x-input-error class="mt-2" :messages="$errors->get('system_message')" />
                        </div>

                        <div>
                            <x-input-label :value="__('Tools')" />

                            @if ($hasGmail)
                                <p class="mt-2 text-sm text-gray-600">{{ __('Gmail access is already linked to this agent.') }}</p>
                            @endif

                            @php
                                $oldTools = old('tools', $selectedTools);
                                $oldTools = is_array($oldTools) ? $oldTools : (array) $oldTools;
                                $toolErrors = array_merge($errors->get('tools') ?? [], $errors->get('tools.*') ?? []);
                            @endphp

                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                @foreach ($availableTools as $value => $label)
                                    <label class="flex items-center gap-3 rounded-md border border-gray-200 p-3 shadow-sm focus-within:ring-2 focus-within:ring-indigo-500">
                                        <input
                                            type="checkbox"
                                            name="tools[]"
                                            value="{{ $value }}"
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            {{ in_array($value, $oldTools, true) ? 'checked' : '' }}
                                        >
                                        <span class="text-sm text-gray-800">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <x-input-error class="mt-2" :messages="$toolErrors" />
                        </div>

                        <div class="flex items-center gap-3">
                            <x-primary-button>{{ __('Save') }}</x-primary-button>
                            <a href="{{ route('dashboard') }}" class="text-sm text-gray-600 hover:text-gray-800">{{ __('Cancel') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
