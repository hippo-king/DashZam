<x-guest-layout>
    <div x-data="{ navOpen: true }" x-init="navOpen = localStorage.getItem('navOpen') !== '0'" class="sticky top-0 z-10 bg-white/90 backdrop-blur">
        <nav x-show="navOpen" class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-screen-2xl items-center justify-between px-4 py-2 sm:px-6 lg:px-8">
                <div class="text-xl font-semibold text-gray-100">
                    {{ __('Events') }}
                </div>
                <div class="flex items-center gap-4">
                    <a href="{{ route('events.index') }}" class="text-sm font-medium text-gray-700 hover:text-gray-900">
                        {{ __('Live View') }}
                    </a>
                    <a href="{{ route('events.timeline') }}" target="_blank" rel="noopener noreferrer" class="text-sm font-medium text-gray-700 hover:text-gray-900">
                        {{ __('Printable Copy') }}
                    </a>
                    <form id="public-fetch-form" action="{{ route('events.fetch') }}" method="POST" class="inline">
                        @csrf
                        <button id="public-fetch-btn" type="submit" class="text-sm font-medium text-gray-700 hover:text-gray-900">
                            {{ __('Fetch Data') }}
                        </button>
                    </form>
                    @auth
                        <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-gray-700 hover:text-gray-900">
                            {{ Auth::user()->name }}
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">
                            {{ __('Sign in') }}
                        </a>
                    @endauth
                    <button type="button" class="text-xs font-semibold text-gray-500 hover:text-gray-700" @click="navOpen = false; localStorage.setItem('navOpen', '0')">
                        {{ __('Hide') }}
                    </button>
                </div>
            </div>
        </nav>
                <script>
                    document.addEventListener('DOMContentLoaded', function(){
                        try{
                            // Discrete toast implementation for guest pages. Mirrors
                            // the `showAjaxToast` used in API settings so notifications
                            // look the same for authenticated and public views.
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
                                    try{ console.log('[ajax toast fallback]', type, message, e); }catch(_){ }
                                    try{ alert((type==='success'? 'Success: ' : 'Error: ') + message); }catch(_){ }
                                }
                            }

                            const form = document.getElementById('public-fetch-form');
                            if (!form) return;
                            const btn = document.getElementById('public-fetch-btn');

                            form.addEventListener('submit', async function(e){
                                e.preventDefault();
                                const now = Date.now();
                                const cooldown = window.dashFetchCooldown || 0;
                                if (now < cooldown) {
                                    const wait = Math.ceil((cooldown - now) / 1000);
                                    if (typeof showAjaxToast === 'function') showAjaxToast('error', `Please wait ${wait}s before fetching again.`);
                                    else if (typeof showNavToast === 'function') showNavToast('error', `Please wait ${wait}s before fetching again.`);
                                    else alert(`Please wait ${wait}s before fetching again.`);
                                    return;
                                }

                                try{
                                    if (btn) btn.disabled = true;
                                    const token = form.querySelector('input[name="_token"]').value;
                                    const resp = await fetch(form.action, {
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
                                            if (typeof showAjaxToast === 'function') showAjaxToast('error', `Too many requests — retry in ${waitSec}s.`);
                                            else if (typeof showNavToast === 'function') showNavToast('error', `Too many requests — retry in ${waitSec}s.`);
                                            else alert(`Too many requests — retry in ${waitSec}s.`);
                                            return;
                                        }

                                        const text = await resp.text().catch(()=>null);
                                        throw new Error(text || resp.statusText || 'Fetch failed');
                                    }

                                    // success: set short cooldown, show toast, and reload after
                                    // the toast has faded so the UX feels smooth.
                                    window.dashFetchCooldown = Date.now() + (30 * 1000);
                                    if (typeof showAjaxToast === 'function') showAjaxToast('success', 'API response fetched.');
                                    else if (typeof showNavToast === 'function') showNavToast('success', 'API response fetched.');
                                    else alert('Success: API response fetched.');
                                    // Wait for the 5s toast duration + small buffer before reload
                                    setTimeout(()=> location.reload(), 5200);
                                }catch(err){
                                    const msg = err?.message || 'Failed to fetch API.';
                                    if (typeof showAjaxToast === 'function') showAjaxToast('error', msg);
                                    else if (typeof showNavToast === 'function') showNavToast('error', msg);
                                    else alert('Error: ' + msg);
                                    window.dashFetchCooldown = Date.now() + (10 * 1000);
                                }finally{
                                    if (btn) btn.disabled = false;
                                }
                            });
                        }catch(e){
                            console.log('public fetch attach error', e);
                        }
                    });
                </script>
        <div x-show="!navOpen" class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-screen-2xl items-center justify-end px-4 py-2 sm:px-6 lg:px-8">
                <button type="button" class="text-xs font-semibold text-gray-500 hover:text-gray-700" @click="navOpen = true; localStorage.setItem('navOpen', '1')">
                    {{ __('Show navigation') }}
                </button>
            </div>
        </div>
    </div>

    <div class="py-6">
        <div class="mx-auto  sm:px-6 lg:px-2">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex flex-col gap-1">
                                <p class="text-sm font-semibold uppercase tracking-wide text-gray-400">
                                    {{ __('Current Time') }}
                                </p>
                                <p id="current-clock" class="text-3xl font-semibold text-gray-100">
                                    {{ now()->timezone('America/Los_Angeles')->format('l, M j · g:i:s A') }}
                                </p>
                                <p class="text-sm text-gray-400">
                                    {{ __('PT') }}
                                </p>
                            </div>
                        </div>
                        <div class="text-sm text-gray-400">
                            <p>{{ __('Live view shows ongoing and upcoming events.') }}</p>
                            @if ($lastUpdatedAt)
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ __('Last updated:') }}
                                    {{ $lastUpdatedAt->timezone('America/Los_Angeles')->format('M j, g:i A') }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-6">
                        @if (!$hasData)
                            <div class="rounded-lg border border-gray-200 bg-white p-6 text-sm text-gray-600 shadow-sm">
                                {{ __('No more events today, this schedule will refresh at midnight.') }}
                            </div>
                        @else
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:hidden">
                                @foreach ($rinks as $rinkName => $events)
                                    @php
                                        $liveEvent = $liveEvents[$rinkName] ?? null;
                                    @endphp
                                    @if ($liveEvent)
                                        @php
                                            $liveStart = $liveEvent['start'] ?? null;
                                            $liveEnd = $liveEvent['end'] ?? null;
                                            $progress = 0;
                                            $pulseDuration = 1.6;
                                            $barClass = 'bg-emerald-500';
                                            $barPulse = false;
                                            $liveTitle = $liveEvent['title'] ?? __('Live Event');
                                            $isLiveResurface = str_contains(strtolower($liveTitle), 'takedown');
                                            $isCloseRink = !empty($liveEvent['is_close_rink']);
                                            $liveLockerRooms = [];
                                            $requiresBravesCuts = false;

                                            if ($isCloseRink) {
                                                $liveTitle = __('Close Rink');
                                            } elseif ($isLiveResurface) {
                                                $liveTitle = __('Ice Resurfacing');
                                            } elseif (preg_match_all('/\(([^)]+)\)/', $liveTitle, $matches)) {
                                                $liveLockerRooms = \App\Support\LockerRoomParser::extractFromTitle($liveTitle);
                                                $liveTitle = trim(preg_replace('/\s*\([^)]*\)\s*/', ' ', $liveTitle));
                                                $liveTitle = preg_replace('/\s{2,}/', ' ', $liveTitle);
                                            }

                                            if (!$isCloseRink && !$isLiveResurface) {
                                                $normalizedTitle = strtolower($liveTitle);
                                                $requiresBravesCuts = str_contains($normalizedTitle, 'spokane braves vs')
                                                    && in_array('BR', $liveLockerRooms, true)
                                                    && in_array('CH', $liveLockerRooms, true);
                                            }

                                            if ($liveStart && $liveEnd) {
                                                $start = $liveStart->copy()->timezone('America/Los_Angeles');
                                                $end = $liveEnd->copy()->timezone('America/Los_Angeles');
                                                $nowTime = now()->timezone('America/Los_Angeles');
                                                $duration = max(1, $end->getTimestamp() - $start->getTimestamp());
                                                $elapsed = min($duration, max(0, $nowTime->getTimestamp() - $start->getTimestamp()));
                                                $progress = (int) round(min(100, max(0, ($elapsed / $duration) * 100)));
                                                if ($progress >= 90) {
                                                    $barClass = 'bg-red-500';
                                                    $barPulse = false;
                                                } elseif ($progress >= 75) {
                                                    $barClass = 'bg-orange-500';
                                                } elseif ($progress >= 51) {
                                                    $barClass = 'bg-yellow-400';
                                                } else {
                                                    $barClass = 'bg-emerald-500';
                                                }
                                                $pulseDuration = max(0.6, 1.8 - (1.2 * ($progress / 100)));
                                            }
                                        @endphp
                                        <div class="rounded-lg border border-red-300 bg-red-50/80 px-4 py-3 ring-2 ring-red-300/60 shadow-sm">
                                            <div class="flex flex-col gap-2">
                                                <div class="flex items-center gap-3">
                                                    <span class="inline-flex items-center gap-2 rounded-full bg-red-200 px-4 py-1.5 text-sm font-semibold text-red-900">
                                                        <span class="live-pulse" aria-hidden="true"></span>
                                                        {{ $rinkName }} · {{ __('Live') }}
                                                    </span>
                                                    @if (!empty($liveEvent['logo']))
                                                        <img src="{{ $liveEvent['logo'] }}" alt="Logo" class="h-10 w-10 object-contain bg-white rounded-md border border-gray-200 p-1">
                                                    @endif
                                                    <h2 class="text-xl font-semibold text-gray-100 text-center flex-1 liveTitle">
                                                        {{ $liveTitle }}
                                                        @if (!empty($liveEvent['is_mock']))
                                                            <span class="text-xs font-semibold text-emerald-700">{{ __('(Mock)') }}</span>
                                                        @endif
                                                    </h2>
                                                </div>
                                                <div class="flex w-full items-center justify-between gap-3 text-base text-gray-200">
                                                    <span class=" liveTime">
                                                        {{ $liveEvent['start']->timezone('America/Los_Angeles')->format('g:i A') }}
                                                        @unless ($isCloseRink)
                                                            —
                                                            {{ $liveEvent['end']->timezone('America/Los_Angeles')->format('g:i A') }}
                                                        @endunless
                                                    </span>
                                                    @if (!$isCloseRink && count($liveLockerRooms))
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-400">
                                                                {{ __('Lockers') }}
                                                            </span>
                                                            @foreach ($liveLockerRooms as $room)
                                                                <span class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-1 text-sm font-semibold text-gray-800">
                                                                    {{ $room }}
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                                @if ($requiresBravesCuts)
                                                    <div class="w-full rounded-md border border-amber-300 bg-amber-100/80 px-3 py-2 text-sm font-semibold text-amber-900">
                                                        {{ __('Ice resurfacing required between each period.') }}
                                                    </div>
                                                @endif
                                                <div class="w-full">
                                                    <div class="relative h-2 w-full overflow-hidden rounded-full bg-emerald-100">
                                                        <div class="h-full rounded-full {{ $barClass }} animate-pulse" style="width: {{ $progress }}%; animation-duration: {{ $pulseDuration }}s;"></div>
                                                        @if ($requiresBravesCuts)
                                                            <span class="absolute inset-y-0 left-[25%] w-1 bg-amber-400/80 animate-pulse"></span>
                                                            <span class="absolute inset-y-0 left-1/2 w-1 -translate-x-1/2 bg-amber-400/80 animate-pulse"></span>
                                                            <span class="absolute inset-y-0 left-[75%] w-1 bg-amber-400/80 animate-pulse"></span>
                                                        @endif
                                                    </div>
                                                    <p class="mt-1 text-sm font-semibold text-green-500">
                                                        {{ $progress }}% {{ __('complete') }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                @foreach ($rinks as $rinkName => $events)
                                    <div class="rounded-lg border border-gray-200 bg-white w-full">
                                            <div class="rounded-t-lg border-b px-4 py-3 text-center text-base font-semibold {{ $rinkName === 'Rink 1' ? '  border-red-300 bg-red-900 text-red-100' : 'border-blue-300 bg-blue-900 text-blue-100' }}">
                                            {{ $rinkName }}
                                        </div>
                                        @php
                                            $liveEvent = $liveEvents[$rinkName] ?? null;
                                        @endphp
                                        @if ($liveEvent)
                                            <div class="hidden sm:block">
                                            @php
                                                $liveStart = $liveEvent['start'] ?? null;
                                                $liveEnd = $liveEvent['end'] ?? null;
                                                $progress = 0;
                                                $pulseDuration = 1.6;
                                                $barClass = 'bg-emerald-500';
                                                $barPulse = false;
                                                $liveTitle = $liveEvent['title'] ?? __('Live Event');
                                                $isLiveResurface = str_contains(strtolower($liveTitle), 'takedown');
                                                $isCloseRink = !empty($liveEvent['is_close_rink']);
                                                $liveLockerRooms = [];
                                                $requiresBravesCuts = false;

                                                if ($isCloseRink) {
                                                    $liveTitle = __('Close Rink');
                                                } elseif ($isLiveResurface) {
                                                    $liveTitle = __('Ice Resurfacing');
                                                } elseif (preg_match_all('/\(([^)]+)\)/', $liveTitle, $matches)) {
                                                    $liveLockerRooms = \App\Support\LockerRoomParser::extractFromTitle($liveTitle);
                                                    $liveTitle = trim(preg_replace('/\s*\([^)]*\)\s*/', ' ', $liveTitle));
                                                    $liveTitle = preg_replace('/\s{2,}/', ' ', $liveTitle);
                                                }

                                                if (!$isCloseRink && !$isLiveResurface) {
                                                    $normalizedTitle = strtolower($liveTitle);
                                                    $requiresBravesCuts = str_contains($normalizedTitle, 'spokane braves vs')
                                                        && in_array('BR', $liveLockerRooms, true)
                                                        && in_array('CH', $liveLockerRooms, true);
                                                }

                                                if ($liveStart && $liveEnd) {
                                                    $start = $liveStart->copy()->timezone('America/Los_Angeles');
                                                    $end = $liveEnd->copy()->timezone('America/Los_Angeles');
                                                    $nowTime = now()->timezone('America/Los_Angeles');
                                                    $duration = max(1, $end->getTimestamp() - $start->getTimestamp());
                                                    $elapsed = min($duration, max(0, $nowTime->getTimestamp() - $start->getTimestamp()));
                                                    $progress = (int) round(min(100, max(0, ($elapsed / $duration) * 100)));
                                                    if ($progress >= 90) {
                                                        $barClass = 'bg-red-500';
                                                        $barPulse = false;
                                                    } elseif ($progress >= 75) {
                                                        $barClass = 'bg-orange-500';
                                                    } elseif ($progress >= 51) {
                                                        $barClass = 'bg-yellow-400';
                                                    } else {
                                                        $barClass = 'bg-emerald-500';
                                                    }
                                                    $pulseDuration = max(0.6, 1.8 - (1.2 * ($progress / 100)));
                                                }
                                            @endphp
                                            <div class="border-b border-red-300 bg-red-50/80 px-2 py-1 shadow-md">
                                                <div class="flex flex-col gap-2 liveContainer">
                                                    <div class="flex items-center gap-3">
                                                        <span class="inline-flex items-center gap-2 rounded-full bg-red-200 px-4 py-1.5 text-sm font-semibold text-red-900">
                                                            <span class="live-pulse" aria-hidden="true"></span>
                                                            {{ __('Live') }}
                                                        </span>
                                                        @if (!empty($liveEvent['logo']))
                                                            <img src="{{ $liveEvent['logo'] }}" alt="Logo" class="h-10 w-10 object-contain bg-white rounded-md border border-gray-200 p-1">
                                                        @endif
                                                        <h2 class="text-xl font-semibold text-gray-100 liveTitle text-center flex-1">
                                                            {{ $liveTitle }}
                                                            @if (!empty($liveEvent['is_mock']))
                                                                <span class="text-xs font-semibold text-emerald-700">{{ __('(Mock)') }}</span>
                                                            @endif
                                                        </h2>
                                                    </div>
                                                    <div class="flex w-full items-center justify-between gap-3">
                                                        <span class=" liveTime">
                                                            {{ $liveEvent['start']->timezone('America/Los_Angeles')->format('g:i A') }}
                                                            @unless ($isCloseRink)
                                                                —
                                                                {{ $liveEvent['end']->timezone('America/Los_Angeles')->format('g:i A') }}
                                                            @endunless
                                                        </span>
                                                        @if (!$isCloseRink && count($liveLockerRooms))
                                                            <div class="flex items-center gap-2">
                                                                <span class="text-sm font-semibold uppercase tracking-wide text-gray-400">
                                                                    {{ __('Lockers') }}
                                                                </span>
                                                                @foreach ($liveLockerRooms as $room)
                                                                    <span class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-1 text-sm font-semibold text-gray-800">
                                                                        {{ $room }}
                                                                    </span>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                    @if ($requiresBravesCuts)
                                                        <div class="w-full rounded-md border border-amber-300 bg-amber-100/80 px-3 py-2 text-sm font-semibold text-amber-900">
                                                            {{ __('Ice resurfacing required between each period.') }}
                                                        </div>
                                                    @endif
                                                    <div class="w-full">
                                                        <div class="relative h-2 w-full overflow-hidden rounded-full bg-emerald-100">
                                                            <div class="h-full rounded-full {{ $barClass }} animate-pulse" style="width: {{ $progress }}%; animation-duration: {{ $pulseDuration }}s;"></div>
                                                            @if ($requiresBravesCuts)
                                                                <span class="absolute inset-y-0 left-[25%] w-1 bg-amber-400/80 animate-pulse"></span>
                                                                <span class="absolute inset-y-0 left-1/2 w-1 -translate-x-1/2 bg-amber-400/80 animate-pulse"></span>
                                                                <span class="absolute inset-y-0 left-[75%] w-1 bg-amber-400/80 animate-pulse"></span>
                                                            @endif
                                                        </div>
                                                        <p class="mt-1 text-sm font-semibold liveProgress">
                                                            {{ $progress }}% {{ __('complete') }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            </div>
                                        @endif
                                        <div class="h-[65vh] overflow-auto p-4">
                                            @if (empty($events))
                                                <p class="text-sm text-gray-600">
                                                    {{ __('No more events today, this schedule will refresh at midnight.') }}
                                                </p>
                                            @else
                                                <div class="space-y-4">
                                                    @php
                                                        $previousDateLabel = null;
                                                        $windowDayStart = $windowStart->copy()->timezone('America/Los_Angeles')->startOfDay();
                                                        $midnightBoundary = $windowDayStart->copy()->addDay();
                                                        $midnightInserted = false;
                                                    @endphp
                                                    @foreach ($events as $event)
                                                        @php
                                                            $eventStart = $event['start']->timezone('America/Los_Angeles');
                                                            $eventDateLabel = $eventStart->format('Y-m-d');
                                                        @endphp
                                                        @if (!$midnightInserted && $eventStart->greaterThanOrEqualTo($midnightBoundary))
                                                            <div class="flex items-center gap-3">
                                                                <div class="h-px flex-1 bg-gray-300"></div>
                                                                <span class="text-xs font-semibold uppercase tracking-wide text-gray-600">
                                                                    {{ $eventStart->format('M j, Y') }}
                                                                </span>
                                                                <div class="h-px flex-1 bg-gray-300"></div>
                                                            </div>
                                                            @php
                                                                $midnightInserted = true;
                                                            @endphp
                                                        @endif
                                                        @if ($previousDateLabel !== null && $eventDateLabel !== $previousDateLabel && $midnightInserted === false)
                                                            <div class="flex items-center gap-3">
                                                                <div class="h-px flex-1 bg-gray-300"></div>
                                                                <span class="text-xs font-semibold uppercase tracking-wide text-gray-600">
                                                                    {{ $eventStart->format('M j, Y') }}
                                                                </span>
                                                                <div class="h-px flex-1 bg-gray-300"></div>
                                                            </div>
                                                            @php
                                                                $midnightInserted = true;
                                                            @endphp
                                                        @endif
                                                        @php
                                                            $previousDateLabel = $eventDateLabel;
                                                        @endphp
                                                        @php
                                                            $title = $event['title'] ?? __('Event');
                                                            $lockerRooms = null;
                                                            $lockerRoomList = [];

                                                            if (preg_match_all('/\(([^)]+)\)/', $title, $matches)) {
                                                                $lockerRooms = collect($matches[1])
                                                                    ->map(fn ($room) => trim($room))
                                                                    ->filter()
                                                                    ->implode(', ');
                                                                $lockerRoomList = \App\Support\LockerRoomParser::extractFromTitle($title);
                                                                $title = trim(preg_replace('/\s*\([^)]*\)\s*/', ' ', $title));
                                                                $title = preg_replace('/\s{2,}/', ' ', $title);
                                                            }

                                                            $isResurface = str_contains(strtolower($title), 'takedown');
                                                            $isCloseRink = !empty($event['is_close_rink']);
                                                            $isAlert = $isCloseRink || $isResurface;
                                                            if ($isCloseRink) {
                                                                $displayTitle = __('Close Rink');
                                                            } elseif ($isResurface) {
                                                                $displayTitle = __('Ice Resurfacing');
                                                            } else {
                                                                $displayTitle = $title;
                                                            }
                                                        @endphp
                                                            <div class="rounded-lg p-4 shadow-sm {{ $isCloseRink ? 'bg-amber-100/80 border border-amber-300 ring-2 ring-amber-300/70' : ($isResurface ? 'bg-amber-50/80 border border-amber-200 ring-1 ring-amber-200/70' : 'bg-gray-50') }}">
                                                            <div class="flex items-center justify-between gap-3">
                                                                @if (!empty($event['logo']) && !$isResurface && !$isCloseRink)
                                                                    <img src="{{ $event['logo'] }}" alt="Logo" class="h-12 w-12 flex-shrink-0 object-contain bg-white rounded-md border border-gray-200 p-1.5">
                                                                @endif
                                                                <div class="min-w-0 flex-1">
                                                                    <h2 class="text-base font-semibold {{ $isAlert ? 'text-amber-900' : 'text-gray-900' }}">
                                                                        {{ $displayTitle }}
                                                                    </h2>
                                                                    <p class="text-sm {{ $isAlert ? 'text-amber-800' : 'text-gray-600' }}">
                                                                        <span class="font-semibold {{ $isAlert ? 'text-amber-900' : 'text-gray-900' }}">
                                                                            {{ $event['start']->timezone('America/Los_Angeles')->format('g:i A') }}
                                                                        </span>
                                                                        @unless ($isCloseRink)
                                                                            —
                                                                            <span class="font-semibold {{ $isAlert ? 'text-amber-900' : 'text-gray-900' }}">
                                                                                {{ $event['end']->timezone('America/Los_Angeles')->format('g:i A') }}
                                                                            </span>
                                                                        @endunless
                                                                    </p>
                                                                </div>
                                                                <div class="flex flex-none items-center gap-2">
                                                                    @if ($loop->first)
                                                                        <span class="inline-flex items-center rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700">
                                                                            {{ __('Next') }}
                                                                        </span>
                                                                    @endif
                                                                    @if (!$isResurface && !$isCloseRink && count($lockerRoomList))
                                                                        <div class="flex items-center gap-2 ">
                                                                            @foreach ($lockerRoomList as $room)
                                                                                <span class="inline-flex p-4 items-center justify-center rounded-md border border-gray-300 bg-white text-sm font-semibold text-gray-800">
                                                                                    {{ $room }}
                                                                                </span>
                                                                            @endforeach
                                                                        </div>
                                                                    @endif
                                                                    @if ($isCloseRink)
                                                                        <span class="inline-flex items-center gap-2 rounded-full bg-amber-200 px-3 py-1 text-xs font-semibold text-amber-900">
                                                                            <span aria-hidden="true">🔒</span>
                                                                            {{ __('Close Rink') }}
                                                                        </span>
                                                                    @elseif ($isResurface)
                                                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                                                                            {{ __('Resurface') }}
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <p class="mt-4 text-xs text-gray-500">
                        {{ __('This view will soon include live view details based on tournament rules.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function () {
            const clock = document.getElementById('current-clock');
            if (!clock) {
                return;
            }

            const formatter = new Intl.DateTimeFormat('en-US', {
                weekday: 'long',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true,
                timeZone: 'America/Los_Angeles',
            });

            const updateClock = () => {
                clock.textContent = formatter.format(new Date());
            };

            const scheduleRefresh = () => {
                const now = new Date();
                const delay = ((60 - now.getSeconds()) * 1000) + 5000 - now.getMilliseconds();
                setTimeout(() => {
                    window.location.reload();
                }, Math.max(0, delay));
            };

            updateClock();
            setInterval(updateClock, 1000);
            scheduleRefresh();
        })();
    </script>
</x-guest-layout>
