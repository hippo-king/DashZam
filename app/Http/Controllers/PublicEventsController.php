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
        $windowStart = now();
        $windowEnd = now()->addDay();

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
        })->filter()->filter(function ($event) use ($windowStart, $windowEnd) {
            return $event['end']->greaterThanOrEqualTo($windowStart) && $event['start']->lessThanOrEqualTo($windowEnd);
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

        $timeline = [];
        $occupiedSlots = [];

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
                $clampedEnd = $end->greaterThan($dayEnd) ? $dayEnd->copy() : $end->copy();

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
                foreach ($slotRange as $slotIndex) {
                    $occupiedSlots[$slotIndex] = true;
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

        return view('public.timeline', [
            'dayStart' => $dayStart,
            'dayEnd' => $dayEnd,
            'rinks' => $rinks,
            'timeline' => $timeline,
            'occupiedSlots' => $occupiedSlotList,
            'hasData' => $useMock || $latest !== null,
            'lastUpdatedAt' => $useMock ? $now : $latest?->api_last_fetched_at,
        ]);
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
                    'end' => $newBase = $newBase->copy()->addMinutes(120),
                ],
            ],
            [
                'id' => 'mock-r1-close',
                'attributes' => [
                    'resource_id' => 1,
                    'desc' => 'Takedown',
                    'start' => $newBase->copy(),
                    'end' => $newBase->copy()->addDay()->startOfDay()->addMinutes(45)->toIso8601String(),
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
