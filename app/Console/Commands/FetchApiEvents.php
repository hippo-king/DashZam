<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class FetchApiEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch events from configured API for users and store payloads';

    public function handle(): int
    {
        $users = User::query()->whereNotNull('api_base_url')->get();

        foreach ($users as $user) {
            $this->info('Fetching for user: ' . $user->id);

            if (!$user->api_base_url) {
                $this->warn('  no api_base_url, skipping');
                continue;
            }

            // Ensure token if credentials are present
            if ($user->api_client_id && $user->api_client_secret) {
                $tokenError = $this->ensureApiToken($user);
                if ($tokenError) {
                    $this->error('  token error: ' . $tokenError);
                    continue;
                }
            }

            $baseUrl = rtrim($user->api_base_url, '/');
            $start = now()->startOfDay()->format('Y-m-d\TH:i:s');
            $end = now()->endOfDay()->format('Y-m-d\TH:i:s');
            $query = 'filter[start__gt]=' . $start . '&filter[end__lt]=' . $end;
            $url = $baseUrl;

            if ($user->api_test_endpoint) {
                $url .= '/' . ltrim($user->api_test_endpoint, '/');
            }

            $url .= '?' . $query;

            $client = Http::timeout(15)->accept('application/vnd.api+json');
            if ($user->api_token) {
                $client = $client->withToken($user->api_token);
            }

            try {
                /** @var Response $response */
                $response = $client->get($url);
            } catch (\Throwable $exception) {
                $this->error('  failed to reach API: ' . $exception->getMessage());
                continue;
            }

            $body = $response->json();

            if (is_array($body)) {
                // Reuse controller-style processing: dedupe and split
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

            $this->info('  fetched status: ' . $response->status());
        }

        return 0;
    }

    private function ensureApiToken($user): ?string
    {
        if (!$user->api_client_id || !$user->api_client_secret) {
            return 'Missing client id/secret';
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
            $response = Http::timeout(15)
                ->acceptJson()
                ->asForm()
                ->post($authUrl, $payload);
        } catch (\Throwable $exception) {
            return 'Failed to authenticate: ' . $exception->getMessage();
        }

        if (!$response->ok()) {
            $message = $response->json('message') ?? $response->body();
            return 'Authentication failed: ' . $message;
        }

        $token = $response->json('access_token');

        if (!$token) {
            return 'Authentication failed: access_token missing';
        }

        $user->api_token = $token;
        $user->api_token_expires_at = now()->addDay();
        $user->save();

        return null;
    }

    private function dataIsEventList(array $data): bool
    {
        if (empty($data)) return false;
        return isset($data[0]) && is_array($data[0]) && (isset($data[0]['attributes']) || isset($data[0]['start']) || isset($data[0]['id']));
    }

    private function dedupeEvents(array $events): array
    {
        $seen = [];
        $out = [];
        foreach ($events as $e) {
            $key = json_encode([$e['id'] ?? null, $e['attributes']['start'] ?? ($e['start'] ?? null), $e['attributes']['end'] ?? ($e['end'] ?? null)]);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = $e;
        }
        return $out;
    }

    private function partitionEvents(array $events): array
    {
        $rink = [];
        $conference = [];
        foreach ($events as $e) {
            $title = strtolower($e['attributes']['desc'] ?? $e['attributes']['title'] ?? $e['title'] ?? '');
            if (str_contains($title, 'conference') || str_contains($title, 'meeting')) {
                $conference[] = $e;
            } else {
                $rink[] = $e;
            }
        }

        return [$rink, $conference];
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
            if (!is_array($item)) continue;
            $events = $item['events'] ?? ($item['attributes']['events'] ?? null);
            if (!is_array($events)) continue;
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
            if (!is_array($item)) continue;
            $events = $item['events'] ?? ($item['attributes']['events'] ?? null);
            if (!is_array($events)) continue;
            [$rinkEvents, $conferenceEvents] = $this->partitionEvents($events);
            if (isset($item['events']) && is_array($item['events'])) {
                $item['events'] = $rinkEvents;
            } elseif (isset($item['attributes']) && is_array($item['attributes'])) {
                $item['attributes']['events'] = $rinkEvents;
            }
            $body['data'][$dataIndex] = $item;
            if (!empty($conferenceEvents)) {
                $body['conference_events'] = array_merge($body['conference_events'] ?? [], $conferenceEvents);
            }
        }

        return $body;
    }
}
