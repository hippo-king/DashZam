<x-guest-layout>
    <div class="py-8 print:py-0">
        <div class="mx-auto w-full max-w-none px-2 sm:px-4 lg:px-6">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between no-print">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">{{ __('Driver Schedule') }}</h1>
                    <p class="text-sm text-gray-500">
                        {{ __('Printable 24-hour timeline for today’s rink events and resurfacing.') }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('events.index') }}" class="text-sm font-semibold text-gray-700 hover:text-gray-900">
                        {{ __('Back to Live View') }}
                    </a>
                    <button type="button" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800" onclick="window.print()">
                        {{ __('Print') }}
                    </button>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg print:shadow-none print:border">
                <div class="p-6 print:p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-gray-400">
                                {{ __('Schedule Date') }}
                            </p>
                            <p class="text-2xl font-semibold text-gray-900">
                                {{ $dayStart->format('l, M j, Y') }}
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ $dayStart->timezone(config('app.timezone'))->format('g:i A') }} — {{ $dayEnd->timezone(config('app.timezone'))->format('g:i A') }} {{ __('(PT)') }}
                            </p>
                        </div>
                        <div class="text-sm text-gray-400">
                            @if ($lastUpdatedAt)
                                <p class="text-xs text-gray-500">
                                    {{ __('Last updated:') }}
                                    {{ $lastUpdatedAt->timezone(config('app.timezone'))->format('M j, g:i A') }}
                                </p>
                            @endif
                        </div>
                    </div>

                    @if (!$hasData)
                        <div class="mt-6 rounded-lg border border-gray-200 bg-white p-6 text-sm text-gray-600 shadow-sm">
                            {{ __('No events are available yet. This timeline will show today’s events in 15-minute increments.') }}
                        </div>
                    @else
                        @php
                            $rinkCount = count($timeline);
                            $slots = $occupiedSlots ?? [];
                        @endphp
                        <div class="mt-6">
                            <div class="w-full">
                                <div class="grid" style="grid-template-columns: 70px repeat({{ $rinkCount }}, minmax(180px, 1fr));">
                                    <div></div>
                                    @foreach ($timeline as $rinkName => $events)
                                        <div class="border border-gray-200 bg-gray-100 py-2 text-center text-sm font-semibold text-gray-800">
                                            {{ $rinkName }}
                                        </div>
                                    @endforeach
                                </div>

                                <div class="grid border border-gray-200 timeline-grid" style="grid-template-columns: 70px repeat({{ $rinkCount }}, minmax(180px, 1fr)); grid-template-rows: repeat({{ max(1, count($slots)) }}, minmax(18px, 1fr));">
                                    @foreach ($slots as $slotIndex)
                                        @php
                                            $slotTime = $dayStart->copy()->addMinutes(($slotIndex - 1) * 15);
                                            $isHour = $slotTime->minute === 0;
                                            $isMidnight = $slotTime->format('H:i') === '00:00';
                                            $label = $isHour ? $slotTime->format('g A') : $slotTime->format('g:i');
                                            $rowIndex = $loop->index + 1;
                                        @endphp
                                        <div class="border-b border-gray-200 pr-2 text-right text-[10px] timeline-time {{ $isMidnight ? 'font-semibold text-gray-900' : ($isHour ? 'font-semibold text-gray-700' : 'text-gray-400') }}" style="grid-column: 1; grid-row: {{ $rowIndex }};">
                                            {{ $isMidnight ? __('Midnight') : $label }}
                                        </div>
                                        <div class="border-b timeline-row {{ $isMidnight ? 'border-gray-400 bg-gray-50' : 'border-gray-100' }}" style="grid-column: 2 / span {{ $rinkCount }}; grid-row: {{ $rowIndex }};"></div>
                                    @endforeach

                                    @foreach ($timeline as $rinkName => $events)
                                        @php
                                            $colIndex = $loop->index + 2;
                                        @endphp
                                        @foreach ($events as $event)
                                            @php
                                                $eventClass = $event['is_close_rink']
                                                    ? 'bg-amber-100 border-amber-300 text-amber-900'
                                                    : ($event['is_resurface']
                                                        ? 'bg-amber-50 border-amber-200 text-amber-900'
                                                        : 'bg-emerald-50 border-emerald-200 text-emerald-900');
                                                $startIndex = array_search($event['slot_start'], $slots, true);
                                                $endIndex = array_search($event['slot_end'] - 1, $slots, true);
                                                $startRow = $startIndex === false ? 1 : $startIndex + 1;
                                                $endRow = $endIndex === false ? max(2, $startRow + 1) : $endIndex + 2;
                                            @endphp
                                            <div class="mx-2 my-0.5 rounded-md border px-2 py-1 text-xs leading-tight shadow-sm print:shadow-none timeline-event {{ $eventClass }}" style="grid-column: {{ $colIndex }}; grid-row: {{ $startRow }} / {{ $endRow }};">
                                                <div class="font-semibold">
                                                    {{ $event['title'] }}
                                                </div>
                                                <div class="text-[11px]">
                                                    {{ $event['start']->timezone(config('app.timezone'))->format('g:i A') }}
                                                    —
                                                    {{ $event['end']->timezone(config('app.timezone'))->format('g:i A') }}
                                                </div>
                                                @if (!$event['is_resurface'] && !$event['is_close_rink'] && count($event['locker_rooms']))
                                                    <div class="mt-1 text-[10px] uppercase tracking-wide text-gray-500">
                                                        {{ __('Lockers:') }} {{ implode(', ', $event['locker_rooms']) }}
                                                    </div>
                                                @endif
                                                @if ($event['requires_braves_cuts'])
                                                    <div class="mt-1 text-[10px] font-semibold text-amber-700">
                                                        {{ __('Resurface between periods') }}
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-4 text-xs text-gray-600">
                            <span class="inline-flex items-center gap-2">
                                <span class="inline-block h-3 w-3 rounded border border-emerald-200 bg-emerald-50"></span>
                                {{ __('Event') }}
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <span class="inline-block h-3 w-3 rounded border border-amber-200 bg-amber-50"></span>
                                {{ __('Ice Resurfacing') }}
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <span class="inline-block h-3 w-3 rounded border border-amber-300 bg-amber-100"></span>
                                {{ __('Close Rink') }}
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <style>
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                color: #000;
                background: #fff;
            }

            .print\:shadow-none {
                box-shadow: none !important;
            }

            @page {
                /* Prefer portrait for printable copy; fallback to portrait sizing */
                size: A4 portrait;
                margin: 0.5in;
            }

            .print-scale-64 {
                transform: scale(0.64);
                transform-origin: top left;
                width: 156.25%;
            }


            /* Make the timeline rows taller for readability on paper */
            .timeline-grid {
                grid-template-rows: repeat(auto-fill, minmax(20px, 1fr)) !important;
            }

            .timeline-time {
                font-size: 11px !important;
                padding-right: 6px !important;
            }

            .timeline-row {
                border-bottom-color: #e5e7eb !important;
            }

            /* Larger, more readable event blocks for printing */
            body, .timeline-event, .timeline-time {
                font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            }

            .timeline-event {
                font-size: 12px !important;
                line-height: 1.25 !important;
                padding: 6px 8px !important;
                margin: 4px 6px !important;
            }
        }
    </style>
</x-guest-layout>
