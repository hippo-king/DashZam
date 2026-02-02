<?php

namespace App\Http\Controllers;

use App\Models\User;
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

        $latest = User::query()
            ->whereNotNull('api_last_payload')
            ->orderByDesc('api_last_fetched_at')
            ->first();

        $items = [];
        if ($useMock) {
            $items = $this->buildMockItems($now);
        } elseif ($latest) {
            $payload = $latest->api_last_payload;

            if (is_string($payload)) {
                $decodedPayload = json_decode($payload, true);
                $payload = is_array($decodedPayload) ? $decodedPayload : null;
            }

            if (is_array($payload)) {
                $body = $payload['body'] ?? null;

                if (is_string($body)) {
                    $decoded = json_decode($body, true);
                    $body = is_array($decoded) ? $decoded : null;
                }

                if (is_array($body)) {
                    $items = $this->extractItemsFromBody($body);
                }
            }
        }

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
            $lastTakedownIndex = null;

            foreach ($rinkEvents as $index => $event) {
                if (str_contains(strtolower($event['title'] ?? ''), 'takedown')) {
                    if (!$lastTakedownIndex || $event['start']->greaterThan($rinkEvents[$lastTakedownIndex]['start'])) {
                        $lastTakedownIndex = $index;
                    }
                }
            }

            if ($lastTakedownIndex !== null) {
                $rinkEvents[$lastTakedownIndex]['is_close_rink'] = true;
                $rinks[$rinkName] = $rinkEvents;
            }
        }

        $liveEvents = [];
        foreach ($rinks as $rinkName => $rinkEvents) {
            $liveEvent = collect($rinkEvents)->first(function ($event) use ($now) {
                return $now->betweenIncluded($event['start'], $event['end']);
            });

            if (!$liveEvent) {
                $mockStart = $now->copy()->subMinutes(20);
                $mockEnd = $now->copy()->addMinutes(40);
                $liveEvent = [
                    'id' => 'mock-' . strtolower(str_replace(' ', '-', $rinkName)),
                    'resource_id' => $rinkName === 'Rink 2' ? 6 : 1,
                    'title' => $rinkName === 'Rink 2' ? 'Public Skating (Mock)' : 'Open Hockey (Mock)',
                    'start' => $mockStart,
                    'end' => $mockEnd,
                    'status' => 'Live',
                    'is_mock' => true,
                ];
            }

            $liveEvents[$rinkName] = $liveEvent;
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

    private function buildMockItems(Carbon $now): array
    {
        $base = $now->copy()->timezone('America/Los_Angeles')->floorMinutes(15);

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
                    'end' => $base->copy()->addMinutes(150)->toIso8601String(),
                ],
            ],
            [
                'id' => 'mock-r1-next',
                'attributes' => [
                    'resource_id' => 1,
                    'desc' => 'Jr. Chiefs Practice (CH, 1)',
                    'start' => $base->copy()->addMinutes(60)->toIso8601String(),
                    'end' => $base->copy()->addMinutes(120)->toIso8601String(),
                ],
            ],
            [
                'id' => 'mock-r1-close',
                'attributes' => [
                    'resource_id' => 1,
                    'desc' => 'Takedown',
                    'start' => $base->copy()->addDay()->startOfDay()->addMinutes(15)->toIso8601String(),
                    'end' => $base->copy()->addDay()->startOfDay()->addMinutes(45)->toIso8601String(),
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
                    'start' => $base->copy()->addHours(6)->toIso8601String(),
                    'end' => $base->copy()->addHours(6)->addMinutes(15)->toIso8601String(),
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
}
