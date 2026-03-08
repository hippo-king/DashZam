<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ApiSettingsController extends Controller
{
    public function edit(Request $request)
    {
        return view('api-settings.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'api_base_url' => ['nullable', 'url', 'max:255'],
            'api_test_endpoint' => ['nullable', 'string', 'max:255'],
            'api_client_id' => ['nullable', 'string', 'max:255'],
            'api_client_secret' => ['nullable', 'string', 'max:5000'],
            'api_token' => ['nullable', 'string', 'max:5000'],
            'clear_token' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        $user->api_base_url = $validated['api_base_url'] ?? null;
        $user->api_test_endpoint = $validated['api_test_endpoint'] ?? null;

        if ($request->filled('api_client_id')) {
            $user->api_client_id = $validated['api_client_id'];
        }

        if ($request->filled('api_client_secret')) {
            $user->api_client_secret = $validated['api_client_secret'];
        }

        if ($request->boolean('clear_token')) {
            $user->api_token = null;
        } elseif ($request->filled('api_token')) {
            $user->api_token = $validated['api_token'];
        }

        $user->save();

        return back()->with('status', 'API settings updated.');
    }

    public function fetch(Request $request)
    {
        // Prevent rapid/parallel manual fetches from spamming the remote API.
        // Acquire a short lock; if unavailable, return 429 with Retry-After.
        $lockKey = 'dash:manual_fetch_lock';
        $lockTtl = 30; // seconds - server-side cooldown
        $lock = Cache::lock($lockKey, $lockTtl);
        if (! $lock->get()) {
            return back()->withErrors([
                'api_base_url' => 'Another fetch is in progress. Please try again later.',
            ])->setStatusCode(429)->header('Retry-After', (string) $lockTtl);
        }

        $user = $request->user();

        if (!$user->api_base_url) {
            return back()->withErrors([
                'api_base_url' => 'Please set the API base URL before fetching data.',
            ]);
        }

        $tokenError = $this->ensureApiToken($user);

        if ($tokenError) {
            return back()->withErrors([
                'api_token' => $tokenError,
            ]);
        }

        $baseUrl = rtrim($user->api_base_url, '/');
        // span yesterday through tomorrow so that a single fetch covers full current day
        $start = now()->subDay()->startOfDay()->format('Y-m-d\TH:i:s');
        $end   = now()->addDay()->endOfDay()->format('Y-m-d\TH:i:s');
        $query = 'filter[start__gt]=' . $start . '&filter[end__lt]=' . $end;
        $url = $baseUrl;

        if ($user->api_test_endpoint) {
            $url .= '/' . ltrim($user->api_test_endpoint, '/');
        }

        $separator = str_contains($url, '?') ? '&' : '?';
        $url .= $separator . $query;

        $client = Http::timeout(15)->accept('application/vnd.api+json');

        if ($user->api_token) {
            $client = $client->withToken($user->api_token);
        }

        try {
            /** @var Response $response */
            $response = $client->get($url);
        } catch (\Throwable $exception) {
            $lock->release();
            return back()->withErrors([
                'api_base_url' => 'Failed to reach the API: ' . $exception->getMessage(),
            ])->setStatusCode(502);
        }

        $body = $response->json();

        if (is_array($body)) {
            $body = $this->filterDuplicateEvents($body);
            $body = $this->splitRinkAndConferenceEvents($body);
        }

        $payload = [
            'requested_at' => now()->toIso8601String(),
            'url' => $url,
            'status' => $response->status(),
            'ok' => $response->ok(),
            'headers' => $response->headers(),
            'content_type' => $response->header('Content-Type'),
            'body' => $body ?? $response->body(),
        ];

        $user->api_last_payload = $payload;
        $user->api_last_fetched_at = now();
        $user->save();
        // release the manual fetch lock
        $lock->release();

        return back()->with('status', 'API response fetched.');
    }

    private function ensureApiToken($user): ?string
    {
        if (!$user->api_client_id || !$user->api_client_secret) {
            return 'Please set the API client ID and secret to authenticate.';
        }

        if ($user->api_token && $user->api_token_expires_at && $user->api_token_expires_at->isFuture()) {
            return null;
        }

        $authUrl = rtrim($user->api_base_url, '/') . '/auth/token';
        $payload = [
            'client_id' => $user->api_client_id,
            'client_secret' => $user->api_client_secret,
            'grant_type' => 'client_credentials',
        ];

        try {
            /** @var Response $response */
            $response = Http::timeout(15)
                ->acceptJson()
                ->asForm()
                ->post($authUrl, $payload);
        } catch (\Throwable $exception) {
            return 'Failed to authenticate with the API: ' . $exception->getMessage();
        }

        if (!$response->ok()) {
            $message = $response->json('message') ?? $response->body();
            return 'Authentication failed: ' . $message;
        }

        $token = $response->json('access_token');

        if (!$token) {
            return 'Authentication failed: access_token missing from response.';
        }

        $user->api_token = $token;
        $user->api_token_expires_at = now()->addDay();
        $user->save();

        return null;
    }

    private function filterDuplicateEvents(array $body): array
    {
        if (!isset($body['data']) || !is_array($body['data'])) {
            return $body;
        }

        if ($this->dataIsEventList($body['data'])) {
            $body['data'] = $this->dedupeEvents($body['data']);
            return $body;
        }

        foreach ($body['data'] as $dataIndex => $item) {
            if (!is_array($item)) {
                continue;
            }

            $events = $item['events'] ?? ($item['attributes']['events'] ?? null);

            if (!is_array($events)) {
                continue;
            }

            $filtered = $this->dedupeEvents($events);

            if (isset($item['events']) && is_array($item['events'])) {
                $item['events'] = $filtered;
            } elseif (isset($item['attributes']) && is_array($item['attributes'])) {
                $item['attributes']['events'] = $filtered;
            }

            $body['data'][$dataIndex] = $item;
        }

        return $body;
    }

    private function splitRinkAndConferenceEvents(array $body): array
    {
        if (!isset($body['data']) || !is_array($body['data'])) {
            return $body;
        }

        if ($this->dataIsEventList($body['data'])) {
            [$rinkEvents, $conferenceEvents] = $this->partitionEvents($body['data']);
            $body['data'] = $rinkEvents;
            $body['conference_events'] = $conferenceEvents;

            return $body;
        }

        foreach ($body['data'] as $dataIndex => $item) {
            if (!is_array($item)) {
                continue;
            }

            $events = $item['events'] ?? ($item['attributes']['events'] ?? null);

            if (!is_array($events)) {
                continue;
            }

            [$rinkEvents, $conferenceEvents] = $this->partitionEvents($events);

            if (isset($item['events']) && is_array($item['events'])) {
                $item['events'] = $rinkEvents;
            } elseif (isset($item['attributes']) && is_array($item['attributes'])) {
                $item['attributes']['events'] = $rinkEvents;
            }

            $body['data'][$dataIndex] = $item;

            if (!empty($conferenceEvents)) {
                $body['conference_events'] = array_merge(
                    $body['conference_events'] ?? [],
                    $conferenceEvents
                );
            }
        }

        return $body;
    }

    private function dataIsEventList(array $data): bool
    {
        if ($data === []) {
            return false;
        }

        $first = $data[array_key_first($data)] ?? null;

        return is_array($first)
            && ($first['type'] ?? null) === 'events'
            && isset($first['attributes'])
            && is_array($first['attributes']);
    }

    private function dedupeEvents(array $events): array
    {
        $seen = [];
        $filtered = [];

        foreach ($events as $event) {
            if (!is_array($event)) {
                $filtered[] = $event;
                continue;
            }

            $attributes = is_array($event['attributes'] ?? null) ? $event['attributes'] : [];
            $desc = $attributes['desc'] ?? ($event['desc'] ?? ($event['title'] ?? ($event['name'] ?? '')));
            $start = $attributes['start'] ?? ($event['start'] ?? ($event['start_at'] ?? ($event['starts_at'] ?? null)));
            $end = $attributes['end'] ?? ($event['end'] ?? ($event['end_at'] ?? ($event['ends_at'] ?? null)));

            $normalizedDesc = strtolower(trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9\s]+/i', '', (string) $desc))));
            $timeKey = (string) $start . '|' . (string) $end;

            if ($normalizedDesc !== '') {
                $bucket = $seen[$timeKey] ?? [];

                foreach ($bucket as $existingDesc) {
                    if (
                        $normalizedDesc === $existingDesc
                        || str_contains($normalizedDesc, $existingDesc)
                        || str_contains($existingDesc, $normalizedDesc)
                    ) {
                        continue 2;
                    }
                }

                $bucket[] = $normalizedDesc;
                $seen[$timeKey] = $bucket;
            }

            $filtered[] = $event;
        }

        return $filtered;
    }

    private function partitionEvents(array $events): array
    {
        $rinkEvents = [];
        $conferenceEvents = [];

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $attributes = is_array($event['attributes'] ?? null) ? $event['attributes'] : [];
            $resourceId = $attributes['resource_id'] ?? ($event['resource_id'] ?? null);

            if (in_array($resourceId, [1, 6], true)) {
                $rinkEvents[] = $event;
            } elseif ($resourceId === 3 || $resourceId === '3') {
                $conferenceEvents[] = $event;
            }
        }

        return [$rinkEvents, $conferenceEvents];
    }
}
