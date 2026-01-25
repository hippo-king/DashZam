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
            'api_token' => ['nullable', 'string', 'max:5000'],
            'clear_token' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        $user->api_base_url = $validated['api_base_url'] ?? null;
        $user->api_test_endpoint = $validated['api_test_endpoint'] ?? null;

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

        $endpoint = $user->api_test_endpoint ?: '/';
        $url = rtrim($user->api_base_url, '/') . '/' . ltrim($endpoint, '/');

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
}
