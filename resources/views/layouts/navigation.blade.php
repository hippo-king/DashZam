<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('events.index')" :active="request()->routeIs('events.index')">
                        {{ __('Events') }}
                    </x-nav-link>
                    <x-nav-link :href="route('events.timeline')" :active="request()->routeIs('events.timeline')">
                        {{ __('Driver Timeline') }}
                    </x-nav-link>
                    <div class="flex items-center space-x-2">
                        <x-nav-link :href="route('api-settings.edit')" :active="request()->routeIs('api-settings.*')">
                            {{ __('API Settings') }}
                        </x-nav-link>
                        <button type="button" onclick="ajaxFetchApi(this)" title="Fetch API now" class="inline-flex items-center px-2 py-1 border border-transparent rounded text-sm text-gray-500 bg-white hover:bg-gray-50 hover:text-gray-700 focus:outline-none">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-3">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('events.index')" :active="request()->routeIs('events.index')">
                {{ __('Events') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('events.timeline')" :active="request()->routeIs('events.timeline')">
                {{ __('Driver Timeline') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('api-settings.edit')" :active="request()->routeIs('api-settings.*')">
                {{ __('API Settings') }}
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>

<script>
    async function ajaxFetchApi(btn){
        // throttle client-side: prevent rapid repeated clicks
        const now = Date.now();
        const cooldown = window.dashFetchCooldown || 0;
        if (now < cooldown) {
            const wait = Math.ceil((cooldown - now) / 1000);
            showNavToast('error', `Please wait ${wait}s before fetching again.`);
            return;
        }

        try{
            btn.disabled = true;
            const url = '{{ route('api-settings.fetch') }}';
            const token = document.querySelector('meta[name="csrf-token"]').content;

            const resp = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
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
                    showNavToast('error', `Too many requests — retry in ${waitSec}s.`);
                    btn.disabled = false;
                    return;
                }

                const text = await resp.text().catch(()=>null);
                throw new Error(text || resp.statusText || 'Fetch failed');
            }

            // set a short cooldown to avoid accidental rapid repeats
            window.dashFetchCooldown = Date.now() + (30 * 1000);
            showNavToast('success', 'API response fetched.');
            btn.disabled = false;
        }catch(err){
            showNavToast('error', err.message || 'Failed to fetch API');
            btn.disabled = false;
            // apply a small cooldown after errors to reduce spam
            window.dashFetchCooldown = Date.now() + (10 * 1000);
        }
    }

    function showNavToast(type, message){
        try{
            console.log('[nav toast]', type, message);
            let container = document.getElementById('nav-ajax-toast');
            if (!container){
                container = document.createElement('div');
                container.id = 'nav-ajax-toast';
                container.setAttribute('aria-live','polite');
                container.className = 'fixed inset-0 flex items-end px-4 py-6 pointer-events-none sm:items-start sm:p-6';
                const list = document.createElement('div');
                list.id = 'nav-ajax-toast-list';
                list.className = 'w-full flex flex-col items-center space-y-4 sm:items-end';
                container.appendChild(list);
                document.body.appendChild(container);
            }

            const list = document.getElementById('nav-ajax-toast-list');
            const toast = document.createElement('div');
            toast.className = 'max-w-sm w-full bg-white shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden';
            toast.innerHTML = `<div class="p-4"><div class="flex items-start"><div class="flex-shrink-0">${type==='success'? '<svg class="h-6 w-6 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>' : '<svg class="h-6 w-6 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>'}</div><div class="ml-3 w-0 flex-1 pt-0.5"><p class="text-sm font-medium text-gray-900">${type==='success' ? 'Success' : 'Error'}</p><p class="mt-1 text-sm text-gray-500">${message}</p></div></div></div>`;
            list.appendChild(toast);
            setTimeout(()=> toast.remove(), 5000);
        }catch(e){
            try{ console.log('[nav toast fallback]', type, message, e); }catch(_){}
    }

    // Expose the same toast under `showAjaxToast` so other views can call
    // the identical discrete notification used in API settings.
    function showAjaxToast(type, message){
        try{ showNavToast(type, message); }catch(e){
            try{ console.log('[showAjaxToast fallback]', type, message, e); }catch(_){}
            try{ alert((type==='success'? 'Success: ' : 'Error: ') + message); }catch(_){}
        }
    }
            try{ alert((type==='success'? 'Success: ' : 'Error: ') + message); }catch(_){}
        }
    }
</script>
