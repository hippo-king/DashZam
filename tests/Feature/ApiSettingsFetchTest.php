<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Carbon\Carbon;

class ApiSettingsFetchTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_fetch_uses_yesterday_to_tomorrow_filter(): void
    {
        config(['app.timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2026-03-01 12:00:00', 'UTC'));

        // provide a valid token so ensureApiToken() short-circuits
        $user = User::factory()->create([
            'api_base_url' => 'https://api.test',
            'api_client_id' => 'cid',
            'api_client_secret' => 'csecret',
            'api_token' => 'abcd',
            'api_token_expires_at' => Carbon::now()->addDay(),
        ]);

        // make sure the manual-fetch lock is free
        \Illuminate\Support\Facades\Cache::forget('dash:manual_fetch_lock');

        $requests = [];
        Http::fake([
            '*' => function ($request) use (&$requests) {
                $requests[] = $request;
                return Http::response(['data' => []], 200);
            },
        ]);

        $this->actingAs($user)
            ->post(route('api-settings.fetch'))
            ->assertStatus(302);

        $this->assertNotEmpty($requests);
        $url = $requests[0]->url();
        $decoded = urldecode($url);

        $yesterday = Carbon::now()->subDay()->startOfDay()->format('Y-m-d\TH:i:s');
        $tomorrow  = Carbon::now()->addDay()->endOfDay()->format('Y-m-d\TH:i:s');

        $this->assertStringContainsString('filter[start__gt]=' . $yesterday, $decoded);
        $this->assertStringContainsString('filter[end__lt]=' . $tomorrow, $decoded);
    }

    public function test_manual_fetch_merges_existing_query(): void
    {
        config(['app.timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2026-03-01 12:00:00', 'UTC'));

        $user = User::factory()->create([
            'api_base_url' => 'https://api.test',
            'api_test_endpoint' => '/events?filter[start__gt]=2026-03-01T00:00:00&filter[end__lt]=2026-03-02T00:00:00',
            'api_client_id' => 'cid',
            'api_client_secret' => 'csecret',
        ]);

        $requests = [];
        Http::fake([
            'https://api.test/auth/token' => function ($request) use (&$requests) {
                $requests[] = $request;
                return Http::response(['access_token' => 'user-token'], 200);
            },
            '*' => function ($request) use (&$requests) {
                $requests[] = $request;
                return Http::response(['data' => []], 200);
            },
        ]);

        \Illuminate\Support\Facades\Cache::forget('dash:manual_fetch_lock');

        $this->actingAs($user)
            ->post(route('api-settings.fetch'))
            ->assertStatus(302);

        $this->assertNotEmpty($requests);
        // find the actual events request (skip the auth token call)
        $eventReq = null;
        foreach ($requests as $req) {
            if (str_contains($req->url(), 'filter')) {
                $eventReq = $req;
                break;
            }
        }
        $this->assertNotNull($eventReq, 'expected an event request');
        $url = $eventReq->url();
        $decoded = urldecode($url);

        // should contain both the hard‑coded query and the dynamically generated one
        $this->assertStringContainsString('filter[start__gt]=2026-03-01T00:00:00', $decoded);
        $this->assertStringContainsString('filter[end__lt]=2026-03-02T00:00:00', $decoded);

        $yesterday = Carbon::now()->subDay()->startOfDay()->format('Y-m-d\TH:i:s');
        $tomorrow  = Carbon::now()->addDay()->endOfDay()->format('Y-m-d\TH:i:s');

        $this->assertStringContainsString('filter[start__gt]=' . $yesterday, $decoded);
        $this->assertStringContainsString('filter[end__lt]=' . $tomorrow, $decoded);
        $this->assertEquals(1, substr_count($decoded, '?'), 'URL should have only one question mark');
    }
}
