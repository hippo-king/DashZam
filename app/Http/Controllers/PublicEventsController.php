<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PublicEventsController extends Controller
{
    public function index(Request $request)
    {
        $windowStart = now();
        $windowEnd = now()->addDay();

        $latest = User::query()
            ->whereNotNull('api_last_payload')
            ->orderByDesc('api_last_fetched_at')
            ->first();

        $items = [];
        if ($latest) {
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

        $now = now();
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

        return view('public.events', [
            'windowStart' => $windowStart,
            'windowEnd' => $windowEnd,
            'rinks' => $rinks,
            'hasData' => $latest !== null,
        ]);
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
