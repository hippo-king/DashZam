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

                    {{-- Toast notifications (success / error) --}}
                    <div aria-live="polite" class="fixed inset-0 flex items-end px-4 py-6 pointer-events-none sm:items-start sm:p-6">
                        <div class="w-full flex flex-col items-center space-y-4 sm:items-end">
                            @if (session('status'))
                                <div x-data="{show: true}" x-init="setTimeout(()=> show = false, 4000)" x-show="show" x-transition class="max-w-sm w-full bg-white shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden">
                                    <div class="p-4">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0">
                                                <svg class="h-6 w-6 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </div>
                                            <div class="ml-3 w-0 flex-1 pt-0.5">
                                                <p class="text-sm font-medium text-gray-900">Success</p>
                                                <p class="mt-1 text-sm text-gray-500">{{ session('status') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if ($errors->any())
                                <div x-data="{show: true}" x-init="setTimeout(()=> show = false, 6000)" x-show="show" x-transition class="max-w-sm w-full bg-white shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden">
                                    <div class="p-4">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0">
                                                <svg class="h-6 w-6 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </div>
                                            <div class="ml-3 w-0 flex-1 pt-0.5">
                                                <p class="text-sm font-medium text-gray-900">Error</p>
                                                <p class="mt-1 text-sm text-gray-500">{{ $errors->first() }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

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
                            <x-input-label for="api_client_id" :value="__('Client ID')" />
                            <x-text-input
                                id="api_client_id"
                                name="api_client_id"
                                type="text"
                                class="mt-1 block w-full"
                                :value="old('api_client_id', $user->api_client_id)"
                                autocomplete="off"
                                placeholder="Leave blank to keep current client ID"
                            />
                            <x-input-error class="mt-2" :messages="$errors->get('api_client_id')" />
                        </div>

                        <div>
                            <x-input-label for="api_client_secret" :value="__('Client Secret')" />
                            <x-text-input
                                id="api_client_secret"
                                name="api_client_secret"
                                type="password"
                                class="mt-1 block w-full"
                                autocomplete="off"
                                placeholder="Leave blank to keep current client secret"
                            />
                            <x-input-error class="mt-2" :messages="$errors->get('api_client_secret')" />
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

                    <form id="api-fetch-form" x-data="fetchForm()" @submit.prevent="submitFetch($event)" method="POST" action="{{ route('api-settings.fetch') }}" class="mt-6">
                        @csrf
                        <button type="submit" x-bind:disabled="loading" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <template x-if="loading">
                                <svg class="-ml-1 mr-2 h-5 w-5 text-white animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                </svg>
                            </template>
                            <span x-text="loading ? 'Fetching...' : 'Fetch Now'"></span>
                        </button>
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

                        <div id="api-payload-area" class="mt-4">
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

                    <script>
                        function fetchForm(){
                            return {
                                loading: false,
                                async submitFetch(e){
                                    // client-side cooldown (shared across nav)
                                    const now = Date.now();
                                    const cooldown = window.dashFetchCooldown || 0;
                                    if (now < cooldown) {
                                        const wait = Math.ceil((cooldown - now) / 1000);
                                        showAjaxToast('error', `Please wait ${wait}s before fetching again.`);
                                        return;
                                    }

                                    this.loading = true;
                                    const form = e.target;
                                    const url = form.action;
                                    const token = form.querySelector('input[name="_token"]').value;
                                    const fd = new FormData(form);

                                    try{
                                        const resp = await fetch(url, {
                                            method: 'POST',
                                            headers: {
                                                'X-CSRF-TOKEN': token,
                                                'X-Requested-With': 'XMLHttpRequest'
                                            },
                                            body: fd,
                                            credentials: 'same-origin',
                                        });

                                        if (!resp.ok) {
                                            if (resp.status === 429) {
                                                const retry = resp.headers.get('Retry-After');
                                                let waitSec = 30;
                                                if (retry) {
                                                    const asInt = parseInt(retry, 10);
                                                    if (!Number.isNaN(asInt)) waitSec = asInt;
                                                    else {
                                                        const date = Date.parse(retry);
                                                        if (!Number.isNaN(date)) waitSec = Math.max(1, Math.ceil((date - Date.now())/1000));
                                                    }
                                                }
                                                window.dashFetchCooldown = Date.now() + (waitSec * 1000);
                                                showAjaxToast('error', `Too many requests — retry in ${waitSec}s.`);
                                                return;
                                            }

                                            const bodyText = await resp.text().catch(()=>null);
                                            throw new Error(bodyText || resp.statusText || 'Fetch failed');
                                        }

                                        const text = await resp.text();
                                        // replace payload area with returned HTML fragment
                                        const parser = new DOMParser();
                                        const doc = parser.parseFromString(text, 'text/html');
                                        const newArea = doc.getElementById('api-payload-area');
                                        if (newArea) {
                                            const current = document.getElementById('api-payload-area');
                                            current.innerHTML = newArea.innerHTML;
                                        }

                                        // set short cooldown to avoid repeats
                                        window.dashFetchCooldown = Date.now() + (30 * 1000);
                                        // show toast
                                        showAjaxToast('success', 'API response fetched.');
                                    }catch(err){
                                        let msg = err.message || 'Failed to fetch API.';
                                        showAjaxToast('error', msg);
                                        // small cooldown after error
                                        window.dashFetchCooldown = Date.now() + (10 * 1000);
                                    }finally{
                                        this.loading = false;
                                    }
                                }
                            };
                        }
                        // If Alpine is not present, attach a vanilla submit handler so
                        // the form still submits via the same JS logic.
                        document.addEventListener('DOMContentLoaded', function(){
                            try{
                                if (typeof Alpine === 'undefined'){
                                    const handler = fetchForm();
                                    const form = document.getElementById('api-fetch-form');
                                    if (form){
                                        form.addEventListener('submit', function(ev){
                                            ev.preventDefault();
                                            handler.submitFetch(ev);
                                        });
                                    }
                                }
                            }catch(e){
                                console.log('fetchForm fallback attach error', e);
                            }
                        });

                        function showAjaxToast(type, message){
                            try{
                                console.log('[ajax toast]', type, message);
                                let container = document.getElementById('ajax-toast-container');
                                if (!container) {
                                    container = document.createElement('div');
                                    container.id = 'ajax-toast-container';
                                    container.setAttribute('aria-live','polite');
                                    container.className = 'fixed inset-0 flex items-end px-4 py-6 pointer-events-none sm:items-start sm:p-6';
                                    container.innerHTML = '<div class="w-full flex flex-col items-center space-y-4 sm:items-end" id="ajax-toast-list"></div>';
                                    document.body.appendChild(container);
                                }
                                const list = document.getElementById('ajax-toast-list');
                                const toast = document.createElement('div');
                                toast.className = 'max-w-sm w-full bg-white shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden';
                                toast.innerHTML = `<div class="p-4"><div class="flex items-start"><div class="flex-shrink-0">${type==='success'? '<svg class="h-6 w-6 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>' : '<svg class="h-6 w-6 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>'}</div><div class="ml-3 w-0 flex-1 pt-0.5"><p class="text-sm font-medium text-gray-900">${type==='success' ? 'Success' : 'Error'}</p><p class="mt-1 text-sm text-gray-500">${message}</p></div></div></div>`;
                                list.appendChild(toast);
                                setTimeout(()=>{ toast.remove(); }, 5000);
                            }catch(e){
                                try{ console.log('[ajax toast fallback]', type, message, e); }catch(_){}
                                try{ alert((type==='success'? 'Success: ' : 'Error: ') + message); }catch(_){}
                            }
                        }
                    </script>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
