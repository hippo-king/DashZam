<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
            return back()->withErrors([
                'api_base_url' => 'Failed to reach the API: ' . $exception->getMessage(),
            ]);
        }

        $payload = [
            'requested_at' => now()->toIso8601String(),
            'url' => $url,
            'status' => $response->status(),
            'ok' => $response->ok(),
            'headers' => $response->headers(),
            'content_type' => $response->header('Content-Type'),
            'body' => $response->json() ?? $response->body(),
        ];

        $user->api_last_payload = $payload;
        $user->api_last_fetched_at = now();
        $user->save();

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
}
