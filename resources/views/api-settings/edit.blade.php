<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('API Settings') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <header>
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Remote API Credentials') }}</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ __('Update the credentials used to fetch data from a remote API. Tokens are stored encrypted.') }}
                        </p>
                    </header>

                    @if (session('status'))
                        <div class="mt-4 text-sm text-green-600">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('api-settings.update') }}" class="mt-6 space-y-6">
                        @csrf
                        @method('PATCH')

                        <div>
                            <x-input-label for="api_base_url" :value="__('API Base URL')" />
                            <x-text-input
                                id="api_base_url"
                                name="api_base_url"
                                type="url"
                                class="mt-1 block w-full"
                                :value="old('api_base_url', $user->api_base_url)"
                                autocomplete="off"
                                placeholder="https://api.example.com"
                            />
                            <x-input-error class="mt-2" :messages="$errors->get('api_base_url')" />
                        </div>

                        <div>
                            <x-input-label for="api_test_endpoint" :value="__('Test Endpoint Path')" />
                            <x-text-input
                                id="api_test_endpoint"
                                name="api_test_endpoint"
                                type="text"
                                class="mt-1 block w-full"
                                :value="old('api_test_endpoint', $user->api_test_endpoint)"
                                autocomplete="off"
                                placeholder="/status"
                            />
                            <x-input-error class="mt-2" :messages="$errors->get('api_test_endpoint')" />
                        </div>

                        <div>
                            <x-input-label for="api_token" :value="__('API Token')" />
                            <x-text-input
                                id="api_token"
                                name="api_token"
                                type="password"
                                class="mt-1 block w-full"
                                autocomplete="off"
                                placeholder="Leave blank to keep current token"
                            />
                            <x-input-error class="mt-2" :messages="$errors->get('api_token')" />
                            <div class="mt-2 flex items-center">
                                <input id="clear_token" name="clear_token" type="checkbox" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <label for="clear_token" class="ms-2 text-sm text-gray-600">
                                    {{ __('Clear stored token') }}
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Save Settings') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-3xl">
                    <header>
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Fetch Latest Data') }}</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ __('Use your saved credentials to call the remote API and store the latest response for display.') }}
                        </p>
                    </header>

                    <form method="POST" action="{{ route('api-settings.fetch') }}" class="mt-6">
                        @csrf
                        <x-primary-button>{{ __('Fetch Now') }}</x-primary-button>
                    </form>

                    @if ($user->api_last_fetched_at)
                        <p class="mt-4 text-sm text-gray-600">
                            {{ __('Last fetched:') }} {{ $user->api_last_fetched_at->timezone(config('app.timezone'))->toDayDateTimeString() }}
                        </p>
                    @endif

                    @if ($user->api_last_payload)
                        @php
                            $payload = $user->api_last_payload;
                            $dataItems = $payload['body']['data'] ?? null;
                            $dataItems = is_array($dataItems) ? $dataItems : null;
                        @endphp

                        <div class="mt-4">
                            <x-input-label :value="__('Data Array (Events)')" />

                            @if (!$dataItems)
                                <p class="mt-2 text-sm text-gray-600">
                                    {{ __('No data array found in the last response.') }}
                                </p>
                            @else
                                <div class="mt-2 h-[60vh] overflow-auto rounded-lg border border-gray-200 bg-gray-50 p-4">
                                    <div class="space-y-4">
                                        @foreach ($dataItems as $index => $item)
                                            @php
                                                $events = null;
                                                if (is_array($item)) {
                                                    $events = $item['events'] ?? ($item['attributes']['events'] ?? null);
                                                }
                                            @endphp

                                            <div class="rounded-lg bg-white p-4 shadow-sm">
                                                <div class="flex items-center justify-between">
                                                    <h4 class="text-sm font-semibold text-gray-800">
                                                        {{ __('Item') }} {{ $index + 1 }}
                                                    </h4>
                                                    @if (is_array($item) && isset($item['id']))
                                                        <span class="text-xs text-gray-500">{{ $item['id'] }}</span>
                                                    @endif
                                                </div>

                                                @if ($events)
                                                    <div class="mt-3">
                                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                            {{ __('Events') }}
                                                        </p>
                                                        <pre class="mt-2 max-h-64 overflow-auto rounded-md bg-gray-900 p-3 text-xs text-gray-100">{{ json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    </div>
                                                @else
                                                    <div class="mt-3">
                                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                            {{ __('Data') }}
                                                        </p>
                                                        <pre class="mt-2 max-h-64 overflow-auto rounded-md bg-gray-900 p-3 text-xs text-gray-100">{{ json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
