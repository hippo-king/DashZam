<x-guest-layout>
    <div x-data="{ navOpen: true }" class="sticky top-0 z-10 bg-white/90 backdrop-blur">
        <nav x-show="navOpen" class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <div class="text-lg font-semibold text-gray-900">
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
        <div class="mx-auto max-w-6xl sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900">{{ __('24-Hour Events') }}</h1>
                            <p class="text-sm text-gray-600">
                                {{ $windowStart->timezone('America/Los_Angeles')->toDayDateTimeString() }}
                                —
                                {{ $windowEnd->timezone('America/Los_Angeles')->toDayDateTimeString() }}
                                <span class="text-xs text-gray-400">{{ __('PT') }}</span>
                            </p>
                        </div>
                        <span class="text-xs text-gray-500">
                            {{ __('Live view shows ongoing and upcoming events.') }}
                        </span>
                    </div>

                    <div class="mt-6">
                        @if (!$hasData)
                            <div class="rounded-lg border border-gray-200 bg-white p-6 text-sm text-gray-600 shadow-sm">
                                {{ __('No events are available yet. This list will show ongoing and upcoming events within the next 24 hours.') }}
                            </div>
                        @else
                            <div class="overflow-x-auto" style="display:flex; gap:16px; flex-wrap:nowrap; justify-content:center;">
                                @foreach ($rinks as $rinkName => $events)
                                    <div class="rounded-lg border border-gray-200 bg-white" style="min-width:420px; max-width:900px; flex:1 1 0%;">
                                        <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold text-gray-700">
                                            {{ $rinkName }}
                                        </div>
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
                                                            $isResurface = str_contains(strtolower($title), 'takedown');
                                                            $displayTitle = $isResurface ? __('Ice Resurfacing') : $title;
                                                        @endphp
                                                        <div class="rounded-lg p-4 shadow-sm {{ $isResurface ? 'bg-gray-100 border border-gray-200' : 'bg-gray-50' }}">
                                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                                <div>
                                                                    <h2 class="text-base font-semibold text-gray-900">{{ $displayTitle }}</h2>
                                                                    <p class="text-sm text-gray-600">
                                                                        {{ $event['start']->toDayDateTimeString() }}
                                                                        —
                                                                        {{ $event['end']->toDayDateTimeString() }}
                                                                        <span class="text-xs text-gray-400">{{ __('PT') }}</span>
                                                                    </p>
                                                                </div>
                                                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $isResurface ? 'bg-slate-200 text-slate-700' : 'bg-emerald-100 text-emerald-700' }}">
                                                                    {{ $event['status'] ?? __('Upcoming') }}
                                                                </span>
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
</x-guest-layout>
