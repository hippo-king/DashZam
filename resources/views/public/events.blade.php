<x-guest-layout>
    <div x-data="{ navOpen: true }" class="sticky top-0 z-10 bg-white/90 backdrop-blur">
        <nav x-show="navOpen" class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <div class="text-xl font-semibold text-gray-100">
                    {{ __('Events') }}
                </div>
                <div class="flex items-center gap-4">
                    <a href="{{ route('events.index') }}" class="text-sm font-medium text-gray-700 hover:text-gray-900">
                        {{ __('24-Hour View') }}
                    </a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-gray-700 hover:text-gray-900">
                            {{ Auth::user()->name }}
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">
                            {{ __('Sign in') }}
                        </a>
                    @endauth
                    <button type="button" class="text-xs font-semibold text-gray-500 hover:text-gray-700" @click="navOpen = false">
                        {{ __('Hide') }}
                    </button>
                </div>
            </div>
        </nav>
        <div x-show="!navOpen" class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-end px-4 py-2 sm:px-6 lg:px-8">
                <button type="button" class="text-xs font-semibold text-gray-500 hover:text-gray-700" @click="navOpen = true">
                    {{ __('Show navigation') }}
                </button>
            </div>
        </div>
    </div>

    <div class="py-10">
        <div class="mx-auto max-w-screen-2xl sm:px-6 lg:px-8">
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
                                {{ __('No events are available yet. This list will show ongoing and upcoming events within the next 24 hours.') }}
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
                                            $barClass = 'bg-emerald-500';
                                            $barPulse = false;
                                            $liveTitle = $liveEvent['title'] ?? __('Live Event');
                                            $isLiveResurface = str_contains(strtolower($liveTitle), 'takedown');
                                            $isCloseRink = !empty($liveEvent['is_close_rink']);
                                            $liveLockerRooms = [];

                                            if ($isCloseRink) {
                                                $liveTitle = __('Close Rink');
                                            } elseif ($isLiveResurface) {
                                                $liveTitle = __('Ice Resurfacing');
                                            } elseif (preg_match_all('/\(([^)]+)\)/', $liveTitle, $matches)) {
                                                $liveLockerRooms = \App\Support\LockerRoomParser::extractFromTitle($liveTitle);
                                                $liveTitle = trim(preg_replace('/\s*\([^)]*\)\s*/', ' ', $liveTitle));
                                                $liveTitle = preg_replace('/\s{2,}/', ' ', $liveTitle);
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
                                            }
                                        @endphp
                                        <div class="rounded-lg border border-red-300 bg-red-50/80 px-4 py-3 ring-2 ring-red-300/60 shadow-sm">
                                            <div class="flex flex-col items-center gap-2 text-center">
                                                <span class="inline-flex items-center gap-2 rounded-full bg-red-200 px-4 py-1.5 text-sm font-semibold text-red-900">
                                                    <span class="live-pulse" aria-hidden="true"></span>
                                                    {{ $rinkName }} · {{ __('Live Now') }}
                                                </span>
                                                <h2 class="text-xl font-semibold text-gray-100">
                                                    {{ $liveTitle }}
                                                    @if (!empty($liveEvent['is_mock']))
                                                        <span class="text-xs font-semibold text-emerald-700">{{ __('(Mock)') }}</span>
                                                    @endif
                                                </h2>
                                                <div class="flex w-full items-center justify-between gap-3 text-base text-gray-200">
                                                    <span>
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
                                                <div class="w-full">
                                                    <div class="h-2 w-full overflow-hidden rounded-full bg-emerald-100">
                                                        <div class="h-full rounded-full {{ $barClass }}" style="width: {{ $progress }}%"></div>
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
                                                $barClass = 'bg-emerald-500';
                                                $barPulse = false;
                                                $liveTitle = $liveEvent['title'] ?? __('Live Event');
                                                $isLiveResurface = str_contains(strtolower($liveTitle), 'takedown');
                                                $isCloseRink = !empty($liveEvent['is_close_rink']);
                                                $liveLockerRooms = [];

                                                if ($isCloseRink) {
                                                    $liveTitle = __('Close Rink');
                                                } elseif ($isLiveResurface) {
                                                    $liveTitle = __('Ice Resurfacing');
                                                } elseif (preg_match_all('/\(([^)]+)\)/', $liveTitle, $matches)) {
                                                    $liveLockerRooms = \App\Support\LockerRoomParser::extractFromTitle($liveTitle);
                                                    $liveTitle = trim(preg_replace('/\s*\([^)]*\)\s*/', ' ', $liveTitle));
                                                    $liveTitle = preg_replace('/\s{2,}/', ' ', $liveTitle);
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
                                                }
                                            @endphp
                                            <div class="border-b border-red-300 bg-red-50/80 px-2 py-1 shadow-md">
                                                <div class="flex flex-col items-center gap-2 text-center liveContainer">
                                                    <span class="inline-flex items-center gap-2 rounded-full bg-red-200 px-4 py-1.5 text-sm font-semibold text-red-900">
                                                        <span class="live-pulse" aria-hidden="true"></span>
                                                        {{ __('Live Now') }}
                                                    </span>
                                                    <h2 class="text-xl font-semibold text-gray-100 liveTitle">
                                                        {{ $liveTitle }}
                                                        @if (!empty($liveEvent['is_mock']))
                                                            <span class="text-xs font-semibold text-emerald-700">{{ __('(Mock)') }}</span>
                                                        @endif
                                                    </h2>
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
                                                    <div class="w-full">
                                                        <div class="h-2 w-full overflow-hidden rounded-full bg-emerald-100">
                                                            <div class="h-full rounded-full {{ $barClass }}" style="width: {{ $progress }}%"></div>
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
                                                    {{ __('No events scheduled in this window.') }}
                                                </p>
                                            @else
                                                <div class="space-y-4">
                                                    @foreach ($events as $event)
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
                                                                <div class="min-w-0 flex-1">
                                                                    <h2 class="text-base font-semibold {{ $isResurface ? 'text-amber-900' : 'text-gray-900' }}">
                                                                        {{ $displayTitle }}
                                                                    </h2>
                                                                    <p class="text-sm {{ $isResurface ? 'text-amber-800' : 'text-gray-600' }}">
                                                                        <span class="font-semibold {{ $isResurface ? 'text-amber-900' : 'text-gray-900' }}">
                                                                            {{ $event['start']->timezone('America/Los_Angeles')->format('g:i A') }}
                                                                        </span>
                                                                        @unless ($isCloseRink)
                                                                            —
                                                                            <span class="font-semibold {{ $isResurface ? 'text-amber-900' : 'text-gray-900' }}">
                                                                                {{ $event['end']->timezone('America/Los_Angeles')->format('g:i A') }}
                                                                            </span>
                                                                        @endunless
                                                                    </p>
                                                                </div>
                                                                <div class="flex flex-none items-center gap-2">
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

            updateClock();
            setInterval(updateClock, 1000);
            setInterval(() => window.location.reload(), 60000);
        })();
    </script>
</x-guest-layout>
