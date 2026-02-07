<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\LockerRoomParser;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PublicEventsController extends Controller
{
    public function index(Request $request)
    {
        $now = now();
        $timezone = config('app.timezone');
        $windowStart = $now->copy()->timezone($timezone)->startOfDay();
        $windowEnd = $windowStart->copy()->addDay();

        $useMock = filter_var(env('EVENTS_USE_MOCK', false), FILTER_VALIDATE_BOOLEAN);

        $items = $this->loadItems($now, $useMock, $latest);

        $events = collect($items)->map(function ($item) use ($now) {
            $attrs = $item['attributes'] ?? $item;
            $startRaw = $attrs['start'] ?? null;
            $startDate = $attrs['start_date'] ?? null;
            $startTime = $attrs['event_start_time'] ?? null;
            if (!$startRaw && $startDate && $startTime) {
                $startRaw = substr($startDate, 0, 10) . 'T' . $startTime;
            }
            if (!$startRaw && $startDate) {
                $startRaw = $startDate;
            }

            $endRaw = $attrs['end'] ?? null;

            $start = $startRaw ? Carbon::parse($startRaw) : null;
            $end = $endRaw ? Carbon::parse($endRaw) : null;

            if (!$start || !$end) {
                return null;
            }

            $status = $now->betweenIncluded($start, $end) ? 'Live' : ($start->greaterThan($now) ? 'Upcoming' : 'Ended');

            return [
                'id' => $item['id'] ?? null,
                'resource_id' => $attrs['resource_id'] ?? null,
                'title' => $attrs['desc'] ?? $attrs['title'] ?? 'Event',
                'start' => $start,
                'end' => $end,
                'status' => $status,
                'is_close_rink' => false,
            ];
        })->filter()->filter(function ($event) use ($windowStart, $windowEnd, $now) {
            // Keep events that overlap the configured day window and have not already ended.
            return $event['end']->greaterThanOrEqualTo($windowStart)
                && $event['start']->lessThanOrEqualTo($windowEnd)
                && $event['end']->greaterThanOrEqualTo($now);
        })->sortBy(function ($event) use ($now) {
            $isLive = $now->betweenIncluded($event['start'], $event['end']);

            return sprintf('%d-%012d', $isLive ? 0 : 1, $event['start']->getTimestamp());
        })->values();

        $rinks = [
            'Rink 1' => [],
            'Rink 2' => [],
        ];

        foreach ($events as $event) {
            $resourceId = (int) ($event['resource_id'] ?? 1);
            $rinkKey = $resourceId === 6 ? 'Rink 2' : 'Rink 1';
            $rinks[$rinkKey][] = $event;
        }

        foreach ($rinks as $rinkName => $rinkEvents) {
            $lastEventIndex = null;

            foreach ($rinkEvents as $index => $event) {
                if ($lastEventIndex === null || $event['start']->greaterThan($rinkEvents[$lastEventIndex]['start'])) {
                    $lastEventIndex = $index;
                }
            }

            if ($lastEventIndex !== null) {
                $lastEventTitle = strtolower($rinkEvents[$lastEventIndex]['title'] ?? '');
                if (str_contains($lastEventTitle, 'takedown')) {
                    $rinkEvents[$lastEventIndex]['is_close_rink'] = true;
                }
                $hasCloseRink = collect($rinkEvents)->contains(function ($event) {
                    return !empty($event['is_close_rink']);
                });

                if (!$hasCloseRink) {
                    $lastEvent = $rinkEvents[$lastEventIndex];
                    $closeStart = $lastEvent['end']->copy();
                    $closeEnd = $closeStart->copy()->addMinutes(15);
                    $closeStatus = $now->betweenIncluded($closeStart, $closeEnd)
                        ? 'Live'
                        : ($closeStart->greaterThan($now) ? 'Upcoming' : 'Ended');

                    $rinkEvents[] = [
                        'id' => null,
                        'resource_id' => $lastEvent['resource_id'] ?? null,
                        'title' => 'Close Rink',
                        'start' => $closeStart,
                        'end' => $closeEnd,
                        'status' => $closeStatus,
                        'is_close_rink' => true,
                    ];
                }

                $rinks[$rinkName] = collect($rinkEvents)
                    ->sortBy('start')
                    ->values()
                    ->all();
            }
        }

        $rinks = $this->applyOvernightGapRule($rinks, $windowStart, $now);

        $liveEvents = [];
        foreach ($rinks as $rinkName => $rinkEvents) {
            $liveEvent = collect($rinkEvents)->first(function ($event) use ($now) {
                return $now->betweenIncluded($event['start'], $event['end']);
            });

            if ($liveEvent) {
                $liveEvents[$rinkName] = $liveEvent;
            }
        }

        foreach ($rinks as $rinkName => $rinkEvents) {
            $liveEvent = $liveEvents[$rinkName] ?? null;

            if (!$liveEvent || !empty($liveEvent['is_mock'])) {
                continue;
            }

            $rinks[$rinkName] = collect($rinkEvents)
                ->reject(function ($event) use ($liveEvent) {
                    return ($event['id'] ?? null) === ($liveEvent['id'] ?? null)
                        && $event['start']->equalTo($liveEvent['start'])
                        && $event['end']->equalTo($liveEvent['end']);
                })
                ->values()
                ->all();
        }

        // Ensure each rink ends with a Close Rink entry when missing.
        $rinks = $this->ensureCloseRink($rinks, $now);

        return view('public.events', [
            'windowStart' => $windowStart,
            'windowEnd' => $windowEnd,
            'rinks' => $rinks,
            'liveEvents' => $liveEvents,
            'hasData' => $useMock || $latest !== null,
            'lastUpdatedAt' => $useMock ? $now : $latest?->api_last_fetched_at,
        ]);
    }

    public function timeline(Request $request)
    {
        $now = now();
        $timezone = config('app.timezone');
        $dayStart = $now->copy()->timezone($timezone)->startOfDay();
        $dayEnd = $now->copy()->timezone($timezone)->endOfDay();

        $useMock = filter_var(env('EVENTS_USE_MOCK', false), FILTER_VALIDATE_BOOLEAN);
        $items = $this->loadItems($now, $useMock, $latest);

        $events = collect($items)->map(function ($item) use ($now) {
            $attrs = $item['attributes'] ?? $item;
            $startRaw = $attrs['start'] ?? null;
            $startDate = $attrs['start_date'] ?? null;
            $startTime = $attrs['event_start_time'] ?? null;
            if (!$startRaw && $startDate && $startTime) {
                $startRaw = substr($startDate, 0, 10) . 'T' . $startTime;
            }
            if (!$startRaw && $startDate) {
                $startRaw = $startDate;
            }

            $endRaw = $attrs['end'] ?? null;

            $start = $startRaw ? Carbon::parse($startRaw) : null;
            $end = $endRaw ? Carbon::parse($endRaw) : null;

            if (!$start || !$end) {
                return null;
            }

            $status = $now->betweenIncluded($start, $end) ? 'Live' : ($start->greaterThan($now) ? 'Upcoming' : 'Ended');

            return [
                'id' => $item['id'] ?? null,
                'resource_id' => $attrs['resource_id'] ?? null,
                'title' => $attrs['desc'] ?? $attrs['title'] ?? 'Event',
                'start' => $start,
                'end' => $end,
                'status' => $status,
                'is_close_rink' => false,
            ];
        })->filter()->filter(function ($event) use ($dayStart, $dayEnd, $timezone) {
            $start = $event['start']->copy()->timezone($timezone);
            $end = $event['end']->copy()->timezone($timezone);

            return $end->greaterThanOrEqualTo($dayStart) && $start->lessThanOrEqualTo($dayEnd);
        })->sortBy('start')->values();

        $rinks = [
            'Rink 1' => [],
            'Rink 2' => [],
        ];

        foreach ($events as $event) {
            $resourceId = (int) ($event['resource_id'] ?? 1);
            $rinkKey = $resourceId === 6 ? 'Rink 2' : 'Rink 1';
            $rinks[$rinkKey][] = $event;
        }

        foreach ($rinks as $rinkName => $rinkEvents) {
            $lastEventIndex = null;

            foreach ($rinkEvents as $index => $event) {
                if ($lastEventIndex === null || $event['start']->greaterThan($rinkEvents[$lastEventIndex]['start'])) {
                    $lastEventIndex = $index;
                }
            }

            if ($lastEventIndex !== null) {
                $lastEventTitle = strtolower($rinkEvents[$lastEventIndex]['title'] ?? '');
                if (str_contains($lastEventTitle, 'takedown')) {
                    $rinkEvents[$lastEventIndex]['is_close_rink'] = true;
                }
                $hasCloseRink = collect($rinkEvents)->contains(function ($event) {
                    return !empty($event['is_close_rink']);
                });

                if (!$hasCloseRink) {
                    $lastEvent = $rinkEvents[$lastEventIndex];
                    $closeStart = $lastEvent['end']->copy();
                    $closeEnd = $closeStart->copy()->addMinutes(15);
                    $closeStatus = $now->betweenIncluded($closeStart, $closeEnd)
                        ? 'Live'
                        : ($closeStart->greaterThan($now) ? 'Upcoming' : 'Ended');

                    $rinkEvents[] = [
                        'id' => null,
                        'resource_id' => $lastEvent['resource_id'] ?? null,
                        'title' => 'Close Rink',
                        'start' => $closeStart,
                        'end' => $closeEnd,
                        'status' => $closeStatus,
                        'is_close_rink' => true,
                    ];
                }

                $rinks[$rinkName] = collect($rinkEvents)
                    ->sortBy('start')
                    ->values()
                    ->all();
            }
        }

        // Ensure each rink ends with a Close Rink entry when missing so
        // the timeline end calculation includes those events.
        $rinks = $this->ensureCloseRink($rinks, $now);

        $timeline = [];
        $occupiedSlots = [];
        // Compute timeline end as the latest event end or the day end, whichever is later.
        $timelineEnd = $dayEnd->copy();
        foreach ($rinks as $rinkEvents) {
            foreach ($rinkEvents as $event) {
                $end = $event['end']->copy()->timezone($timezone);
                if ($end->greaterThan($timelineEnd)) {
                    $timelineEnd = $end->copy();
                }
            }
        }

        foreach ($rinks as $rinkName => $rinkEvents) {
            foreach ($rinkEvents as $event) {
                $title = $event['title'] ?? 'Event';
                $lockerRooms = [];

                if (preg_match_all('/\(([^)]+)\)/', $title, $matches)) {
                    $lockerRooms = LockerRoomParser::extractFromTitle($title);
                    $title = trim(preg_replace('/\s*\([^)]*\)\s*/', ' ', $title));
                    $title = preg_replace('/\s{2,}/', ' ', $title);
                }

                $isResurface = str_contains(strtolower($title), 'takedown');
                $isCloseRink = !empty($event['is_close_rink']);

                if ($isCloseRink) {
                    $displayTitle = 'Close Rink';
                } elseif ($isResurface) {
                    $displayTitle = 'Ice Resurfacing';
                } else {
                    $displayTitle = $title;
                }

                $requiresBravesCuts = false;

                if (!$isCloseRink && !$isResurface) {
                    $normalizedTitle = strtolower($displayTitle);
                    $requiresBravesCuts = str_contains($normalizedTitle, 'spokane braves vs')
                        && in_array('BR', $lockerRooms, true)
                        && in_array('CH', $lockerRooms, true);
                }

                $start = $event['start']->copy()->timezone($timezone);
                $end = $event['end']->copy()->timezone($timezone);

                $clampedStart = $start->lessThan($dayStart) ? $dayStart->copy() : $start->copy();
                $clampedEnd = $end->greaterThan($timelineEnd) ? $timelineEnd->copy() : $end->copy();

                if ($clampedEnd->lessThanOrEqualTo($dayStart) || $clampedStart->greaterThanOrEqualTo($dayEnd)) {
                    continue;
                }

                $startMinutes = $dayStart->diffInMinutes($clampedStart);
                $endMinutes = $dayStart->diffInMinutes($clampedEnd);

                $slotStart = intdiv($startMinutes, 15) + 1;
                $slotEnd = (int) ceil($endMinutes / 15) + 1;

                if ($slotEnd <= $slotStart) {
                    $slotEnd = $slotStart + 1;
                }

                $slotRange = range($slotStart, $slotEnd - 1);
                // Mark occupied slots only for events that start on this day and
                // are not rink closures or resurfacing. This ensures that
                // previous-day continuations and close/resurface events do not
                // prevent collapsing the early timeline start.
                $originalStart = $event['start']->copy()->timezone($timezone);
                if ($originalStart->greaterThanOrEqualTo($dayStart) && !$isCloseRink && !$isResurface) {
                    foreach ($slotRange as $slotIndex) {
                        $occupiedSlots[$slotIndex] = true;
                    }
                }

                $timeline[$rinkName][] = [
                    'id' => $event['id'] ?? null,
                    'title' => $displayTitle,
                    'start' => $start,
                    'end' => $end,
                    'locker_rooms' => $lockerRooms,
                    'requires_braves_cuts' => $requiresBravesCuts,
                    'is_resurface' => $isResurface,
                    'is_close_rink' => $isCloseRink,
                    'slot_start' => $slotStart,
                    'slot_end' => $slotEnd,
                ];
            }

            if (!isset($timeline[$rinkName])) {
                $timeline[$rinkName] = [];
            }
        }

        $occupiedSlotList = collect(array_keys($occupiedSlots))
            ->map(fn($slot) => (int) $slot)
            ->sort()
            ->values()
            ->all();

        // If a rink has no explicit Close Rink event, append an implied Close
        // Rink at the end of the timeline so the view shows a closing marker.
        foreach ($timeline as $rinkName => $events) {
            $hasClose = collect($events)->contains(fn($e) => !empty($e['is_close_rink']));
            if ($hasClose) {
                continue;
            }

            // Create an implied Close Rink occupying the last 15 minutes up to timelineEnd.
            $impliedEnd = $timelineEnd->copy();
            $impliedStart = $impliedEnd->copy()->subMinutes(15);

            // Clamp to dayStart if necessary
            if ($impliedEnd->lessThanOrEqualTo($dayStart)) {
                continue;
            }

            $clampedStart = $impliedStart->lessThan($dayStart) ? $dayStart->copy() : $impliedStart->copy();
            $clampedEnd = $impliedEnd->greaterThan($timelineEnd) ? $timelineEnd->copy() : $impliedEnd->copy();

            $startMinutes = $dayStart->diffInMinutes($clampedStart);
            $endMinutes = $dayStart->diffInMinutes($clampedEnd);

            $slotStart = intdiv($startMinutes, 15) + 1;
            $slotEnd = (int) ceil($endMinutes / 15) + 1;
            if ($slotEnd <= $slotStart) {
                $slotEnd = $slotStart + 1;
            }


            $slotRange = range($slotStart, $slotEnd - 1);
            // Do NOT mark implied close as occupied so it doesn't block collapsing

            $timeline[$rinkName][] = [
                'id' => null,
                'title' => 'Close Rink',
                'start' => $impliedStart->copy(),
                'end' => $impliedEnd->copy(),
                'locker_rooms' => [],
                'requires_braves_cuts' => false,
                'is_resurface' => false,
                'is_close_rink' => true,
                'is_implied_close' => true,
                'slot_start' => $slotStart,
                'slot_end' => $slotEnd,
            ];
        }

        // Recompute occupiedSlotList after adding implied closes.
        $occupiedSlotList = collect(array_keys($occupiedSlots))
            ->map(fn($slot) => (int) $slot)
            ->sort()
            ->values()
            ->all();

        // Detect if there are any early-morning events (midnight..6am) in the raw
        // payload so the view doesn't collapse that range when events exist.
        $timezone = config('app.timezone');
        $midnightBoundary = $dayStart->copy()->addDay()->timezone($timezone);
        $earlyEnd = $midnightBoundary->copy()->addHours(6);

        $allParsed = collect($items)->map(function ($item) use ($timezone) {
            $attrs = $item['attributes'] ?? $item;
            $startRaw = $attrs['start'] ?? null;
            $startDate = $attrs['start_date'] ?? null;
            $startTime = $attrs['event_start_time'] ?? null;
            if (!$startRaw && $startDate && $startTime) {
                $startRaw = substr($startDate, 0, 10) . 'T' . $startTime;
            }
            if (!$startRaw && $startDate) {
                $startRaw = $startDate;
            }

            $start = $startRaw ? Carbon::parse($startRaw) : null;
            if (!$start) {
                return null;
            }

            return [
                'start' => $start->copy()->timezone($timezone),
            ];
        })->filter();

        $hasEarlyMorningEvents = $allParsed->contains(function ($e) use ($midnightBoundary, $earlyEnd) {
            $s = $e['start'];
            return $s->greaterThanOrEqualTo($midnightBoundary) && $s->lessThan($earlyEnd);
        });

        // Determine which early-morning block to consider for collapsing.
        // Default: the midnight..6am window. However, if the timeline has no
        // occupied slots in that early range, collapse up to the first occupied
        // slot so the timeline effectively starts at the first event time.
        $slotsPerDay = (int) (24 * 60 / 15); // 96 slots per day
        $isNextDayWindow = $timelineEnd->greaterThan($dayEnd);

        if ($isNextDayWindow) {
            $collapseRangeStart = $slotsPerDay + 1; // first slot after current-day end
            $collapseRangeStartTime = $dayStart->copy()->addDay();
            // Find earliest occupied slot on the next day, if any.
            $nextDayOccupied = collect($occupiedSlotList)->filter(fn($s) => $s > $slotsPerDay)->values()->all();
            $earliestOccupied = count($nextDayOccupied) ? min($nextDayOccupied) : null;
            if ($earliestOccupied) {
                $collapseRangeEnd = $earliestOccupied - 1;
            } else {
                // Fallback to 6 hours if no occupied slot found.
                $collapseRangeEnd = $collapseRangeStart + 24 - 1; // 24 slots -> 6 hours
            }
            // collapseRangeEndTime is relative to the next-day midnight
            $collapseRangeEndTime = $collapseRangeStartTime->copy()->addMinutes(($collapseRangeEnd - $slotsPerDay) * 15);
        } else {
            $collapseRangeStart = 1;
            $collapseRangeStartTime = $dayStart->copy();
            $dayOccupied = collect($occupiedSlotList)->filter(fn($s) => $s >= 1 && $s <= $slotsPerDay)->values()->all();
            $earliestOccupied = count($dayOccupied) ? min($dayOccupied) : null;
            if ($earliestOccupied) {
                $collapseRangeEnd = $earliestOccupied - 1;
            } else {
                $collapseRangeEnd = $collapseRangeStart + 24 - 1; // default 6 hours
            }
            $collapseRangeEndTime = $collapseRangeStartTime->copy()->addMinutes($collapseRangeEnd * 15);
        }

        // Consider only events that should block collapsing: ignore Close Rink
        // and Ice Resurfacing events when deciding whether to collapse the early block.
        $blockingEventFound = false;
        foreach ($timeline as $rink => $evts) {
            foreach ($evts as $e) {
                if (!empty($e['is_close_rink']) || !empty($e['is_resurface'])) {
                    continue;
                }

                $eventStart = $e['start']->copy()->timezone($timezone);
                $eventEnd = $e['end']->copy()->timezone($timezone);

                // Treat only events that start inside the collapse window as
                // blocking. This ignores cross-midnight events that began
                // before the midnight boundary so the early block can collapse
                // when only a continuation of a previous-day event exists.
                if (
                    $eventStart->greaterThanOrEqualTo($collapseRangeStartTime)
                    && $eventStart->lessThan($collapseRangeEndTime)
                ) {
                    $blockingEventFound = true;
                    break 2;
                }
            }
        }

        // Collapse only when we have a valid collapse range (end >= start),
        // there are no events that start inside that window, and the raw
        // payload does not indicate early-morning events.
        $collapseEarly = isset($collapseRangeStart, $collapseRangeEnd)
            && $collapseRangeEnd >= $collapseRangeStart
            && !$blockingEventFound
            && !$hasEarlyMorningEvents;
        // If debug query param is present, return diagnostic JSON to help
        // reproduce collapse behavior without rendering the full view.
        if (request()->query('debug')) {
            $eventsInRange = [];
            foreach ($timeline as $rink => $evts) {
                foreach ($evts as $e) {
                    $eventStart = $e['start']->copy()->timezone($timezone);
                    $eventEnd = $e['end']->copy()->timezone($timezone);
                    if (
                        $eventStart->greaterThanOrEqualTo($collapseRangeStartTime)
                        && $eventStart->lessThan($collapseRangeEndTime)
                    ) {
                        $eventsInRange[] = [
                            'rink' => $rink,
                            'title' => $e['title'],
                            'slot_start' => $e['slot_start'],
                            'slot_end' => $e['slot_end'] - 1,
                            'start' => $eventStart->toDateTimeString(),
                            'end' => $eventEnd->toDateTimeString(),
                        ];
                    }
                }
            }

            return response()->json([
                'timelineEnd' => $timelineEnd->toDateTimeString(),
                'dayEnd' => $dayEnd->toDateTimeString(),
                'collapseEarly' => $collapseEarly,
                'collapseRangeStart' => $collapseRangeStart ?? null,
                'collapseRangeEnd' => $collapseRangeEnd ?? null,
                'occupiedSlots' => $occupiedSlotList,
                'slotCount' => $slotCount ?? (int) ceil($dayStart->diffInMinutes($timelineEnd) / 15),
                'items_count' => count($items ?? []),
                'events_in_collapse_range' => $eventsInRange,
            ]);
        }

        // Compute timelineStart: the earliest event start on the current day
        // (rounded down to the nearest 15-minute slot). Exclude Close Rink and
        // Ice Resurfacing events when searching for the earliest event so that
        // closures do not prevent collapsing the early timeline.
        $timelineStart = $dayStart->copy();
        $earliest = null;
        foreach ($timeline as $rink => $evts) {
            foreach ($evts as $e) {
                if (!empty($e['is_close_rink']) || !empty($e['is_resurface']) || !empty($e['is_implied_close'])) {
                    continue;
                }

                $s = $e['start']->copy()->timezone($timezone);
                if ($s->greaterThanOrEqualTo($dayStart) && $s->lessThanOrEqualTo($dayEnd)) {
                    if ($earliest === null || $s->lessThan($earliest)) {
                        $earliest = $s->copy();
                    }
                }
            }
        }

        if ($earliest !== null) {
            $minutesFromDayStart = $dayStart->diffInMinutes($earliest);
            $slotIndex = intdiv($minutesFromDayStart, 15);
            $timelineStart = $dayStart->copy()->addMinutes($slotIndex * 15);
        }

        // Build the contiguous slot range that the view should render from
        // `timelineStart` through `timelineEnd`. These slot indices are the
        // absolute 15-minute slots relative to `dayStart`.
        $timelineSlotStart = intdiv($dayStart->diffInMinutes($timelineStart), 15) + 1;
        $timelineSlotEnd = (int) ceil($dayStart->diffInMinutes($timelineEnd) / 15);
        if ($timelineSlotEnd < $timelineSlotStart) {
            $timelineSlotEnd = $timelineSlotStart;
        }

        $renderSlots = range($timelineSlotStart, $timelineSlotEnd);

        return view('public.timeline', [
            'dayStart' => $dayStart,
            'dayEnd' => $dayEnd,
            'timelineEnd' => $timelineEnd,
            'rinks' => $rinks,
            'timeline' => $timeline,
            // `occupiedSlots` here is the contiguous list of slots the view
            // should render (from `timelineStart` to `timelineEnd`). The
            // internal `$occupiedSlotList` used for diagnostics still exists
            // but the view renders the contiguous range so the UI begins at
            // the first non-closure event of the day.
            'occupiedSlots' => $renderSlots,
            'collapseEarly' => $collapseEarly,
            'collapseRangeStart' => $collapseRangeStart,
            'collapseRangeEnd' => $collapseRangeEnd,
            'timelineStart' => $timelineStart,
            'hasData' => $useMock || $latest !== null,
            'lastUpdatedAt' => $useMock ? $now : $latest?->api_last_fetched_at,
        ]);
    }

    /**
     * Ensure each rink has a closing 'Close Rink' event at the end of its last event.
     */
    private function ensureCloseRink(array $rinks, Carbon $now): array
    {
        foreach ($rinks as $rinkName => $rinkEvents) {
            if (empty($rinkEvents)) {
                continue;
            }

            $hasCloseRink = collect($rinkEvents)->contains(fn($e) => !empty($e['is_close_rink']));
            if ($hasCloseRink) {
                continue;
            }

            $last = collect($rinkEvents)->sortBy('start')->last();
            if (!$last) {
                continue;
            }

            $closeStart = $last['end']->copy();
            $closeEnd = $closeStart->copy()->addMinutes(15);
            $closeStatus = $now->betweenIncluded($closeStart, $closeEnd)
                ? 'Live'
                : ($closeStart->greaterThan($now) ? 'Upcoming' : 'Ended');

            $rinks[$rinkName][] = [
                'id' => null,
                'resource_id' => $last['resource_id'] ?? null,
                'title' => 'Close Rink',
                'start' => $closeStart,
                'end' => $closeEnd,
                'status' => $closeStatus,
                'is_close_rink' => true,
            ];
        }

        return $rinks;
    }

    private function loadItems(Carbon $now, bool $useMock, ?User &$latest = null): array
    {
        $latest = User::query()
            ->whereNotNull('api_last_payload')
            ->orderByDesc('api_last_fetched_at')
            ->first();

        if ($useMock) {
            return $this->buildMockItems($now);
        }

        if (!$latest) {
            return [];
        }

        $payload = $latest->api_last_payload;

        if (is_string($payload)) {
            $decodedPayload = json_decode($payload, true);
            $payload = is_array($decodedPayload) ? $decodedPayload : null;
        }

        if (!is_array($payload)) {
            return [];
        }

        $body = $payload['body'] ?? null;

        if (is_string($body)) {
            $decoded = json_decode($body, true);
            $body = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($body)) {
            return [];
        }

        return $this->extractItemsFromBody($body);
    }

    private function buildMockItems(Carbon $now): array
    {
        $base = $now->copy()->timezone('America/Los_Angeles')->floorMinutes(15);
        $newBase = $base;

        return [
            // [
            //     'id' => 'mock-r1-live',
            //     'attributes' => [
            //         'resource_id' => 1,
            //         'desc' => 'Public Skating',
            //         'start' => $base->copy()->subMinutes(15)->toIso8601String(),
            //         'end' => $base->copy()->addMinutes(60)->toIso8601String(),
            //     ],
            // ],
            [
                'id' => 'mock-r1-live',
                'attributes' => [
                    'resource_id' => 1,
                    'desc' => 'Spokane Braves (BR) vs Williams Lake (CH)',
                    'start' => $base->copy()->subMinutes(15)->toIso8601String(),
                    'end' => $newBase = $base->copy()->addMinutes(150),
                ],
            ],
            [
                'id' => 'mock-r1-close',
                'attributes' => [
                    'resource_id' => 1,
                    'desc' => 'Takedown',
                    'start' => $newBase->copy()->toIso8601String(),
                    'end' => $newBase->copy()->addMinutes(15)->toIso8601String(),
                ],
            ],
            [
                'id' => 'mock-r1-next',
                'attributes' => [
                    'resource_id' => 1,
                    'desc' => 'Jr. Chiefs Practice (CH, 1)',
                    'start' => $newBase->copy()->addMinutes(15)->toIso8601String(),
                    'end' => $newBase = $newBase->copy()->addMinutes(60),
                ],
            ],
            [
                'id' => 'mock-r1-close',
                'attributes' => [
                    'resource_id' => 1,
                    'desc' => 'Takedown',
                    'start' => $newBase->copy()->toIso8601String(),
                    'end' => $newBase->copy()->addMinutes(15)->toIso8601String(),
                ],
            ],
            [
                'id' => 'mock-r2-live',
                'attributes' => [
                    'resource_id' => 6,
                    'desc' => 'Open Hockey (5, 7)',
                    'start' => $base->copy()->subMinutes(15)->toIso8601String(),
                    'end' => $base->copy()->addMinutes(60)->toIso8601String(),
                ],
            ],
            [
                'id' => 'mock-r2-takedown1',
                'attributes' => [
                    'resource_id' => 6,
                    'desc' => 'Takedown',
                    'start' => $base->copy()->addHours(1)->toIso8601String(),
                    'end' => $base->copy()->addHours(1)->addMinutes(15)->toIso8601String(),
                ],
            ],
            [
                'id' => 'mock-r2-next',
                'attributes' => [
                    'resource_id' => 6,
                    'desc' => 'Jr. Chiefs Practice (6, 8)',
                    'start' => $base->copy()->addMinutes(75)->toIso8601String(),
                    'end' => $base->copy()->addMinutes(135)->toIso8601String(),
                ],
            ],
            [
                'id' => 'mock-r2-takedown',
                'attributes' => [
                    'resource_id' => 6,
                    'desc' => 'Takedown',
                    'start' => $base->copy()->addMinutes(135)->toIso8601String(),
                    'end' => $base->copy()->addMinutes(150)->toIso8601String(),
                ],
            ],
        ];
    }

    private function extractItemsFromBody(array $body): array
    {
        if (isset($body[0])) {
            return $body;
        }

        $keys = ['data', 'events', 'items', 'results', 'rows'];
        foreach ($keys as $key) {
            if (isset($body[$key]) && is_array($body[$key])) {
                $value = $body[$key];

                if (isset($value[0])) {
                    return $value;
                }

                foreach (['events', 'items', 'results', 'rows'] as $inner) {
                    if (isset($value[$inner]) && is_array($value[$inner]) && isset($value[$inner][0])) {
                        return $value[$inner];
                    }
                }
            }
        }

        return [];
    }

    private function applyOvernightGapRule(array $rinks, Carbon $windowStart, Carbon $now): array
    {
        $timezone = config('app.timezone');
        $dayStart = $windowStart->copy()->timezone($timezone)->startOfDay();
        $midnightBoundary = $dayStart->copy()->addDay();

        foreach ($rinks as $rinkName => $rinkEvents) {
            if (count($rinkEvents) < 2) {
                continue;
            }

            $sorted = collect($rinkEvents)->sortBy('start')->values();
            $lastBefore = $sorted->filter(function ($event) use ($midnightBoundary, $timezone) {
                return $event['start']->copy()->timezone($timezone)->lt($midnightBoundary);
            })->last();
            $firstAfter = $sorted->first(function ($event) use ($midnightBoundary, $timezone) {
                return $event['start']->copy()->timezone($timezone)->gte($midnightBoundary);
            });

            if (!$lastBefore || !$firstAfter) {
                continue;
            }

            $gapHours = $lastBefore['end']->diffInMinutes($firstAfter['start']) / 60;

            if ($gapHours <= 2) {
                continue;
            }

            $filtered = $sorted->filter(function ($event) use ($midnightBoundary, $timezone) {
                return $event['start']->copy()->timezone($timezone)->lt($midnightBoundary);
            })->values()->all();

            $hasCloseRink = collect($filtered)->contains(function ($event) {
                return !empty($event['is_close_rink']);
            });

            $lastTakedownIndex = null;
            foreach ($filtered as $index => $event) {
                $title = strtolower($event['title'] ?? '');
                if (str_contains($title, 'takedown')) {
                    $lastTakedownIndex = $index;
                }
            }

            if (!$hasCloseRink && $lastTakedownIndex !== null) {
                $filtered[$lastTakedownIndex]['is_close_rink'] = true;
            } elseif (!$hasCloseRink && !empty($filtered)) {
                $lastEvent = $filtered[array_key_last($filtered)];
                $closeStart = $lastEvent['end']->copy();
                $closeEnd = $closeStart->copy()->addMinutes(15);
                $closeStatus = $now->betweenIncluded($closeStart, $closeEnd)
                    ? 'Live'
                    : ($closeStart->greaterThan($now) ? 'Upcoming' : 'Ended');

                $filtered[] = [
                    'id' => null,
                    'resource_id' => $lastEvent['resource_id'] ?? null,
                    'title' => 'Close Rink',
                    'start' => $closeStart,
                    'end' => $closeEnd,
                    'status' => $closeStatus,
                    'is_close_rink' => true,
                ];
            }

            $rinks[$rinkName] = $filtered;
        }

        return $rinks;
    }
}
