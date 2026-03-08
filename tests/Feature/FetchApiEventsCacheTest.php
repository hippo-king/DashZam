<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Carbon\Carbon;

class FetchApiEventsCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_token_is_cached_and_reused(): void
    {
        Cache::flush();

        putenv('DASH_API_ID=app-id');
        putenv('DASH_API_SECRET=app-secret');
        putenv('DASH_API_BASE_URL=https://api.test');

        $calls = [];

        Http::fake([
            'https://api.test/auth/token' => function ($request) use (&$calls) {
                $calls[] = $request;
                return Http::response(['access_token' => 'app-token'], 200);
            },
            '*' => function ($request) use (&$calls) {
                $calls[] = $request;
                return Http::response(['data' => []], 200);
            },
        ]);

        $user = User::factory()->create(['api_base_url' => 'https://api.test']);

        $this->artisan('events:fetch')->assertExitCode(0);

        $this->assertSame('app-token', Cache::get('dash:app_token'));

        $this->assertGreaterThanOrEqual(2, count($calls));

        // Clear the recorded calls and run again — token should be reused (no auth POST)
        $calls = [];

        $this->artisan('events:fetch')->assertExitCode(0);

        $this->assertSame('app-token', Cache::get('dash:app_token'));

        $this->assertCount(1, $calls, 'Second run should only call the events endpoint, not the auth endpoint');
    }

    public function test_date_filter_spans_yesterday_and_tomorrow(): void
    {
        config(['app.timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2026-03-01 12:00:00', 'UTC'));

        // set up app-level credentials and fake a working auth endpoint
        putenv('DASH_API_ID=app-id');
        putenv('DASH_API_SECRET=app-secret');
        putenv('DASH_API_BASE_URL=https://api.test');

        $requests = [];
        Http::fake([
            'https://api.test/auth/token' => function ($request) use (&$requests) {
                $requests[] = $request;
                return Http::response(['access_token' => 'app-token'], 200);
            },
            '*' => function ($request) use (&$requests) {
                $requests[] = $request;
                return Http::response(['data' => []], 200);
            },
        ]);

        // we don't need to set a user token; environment flow will provide one
        $user = User::factory()->create([
            'api_base_url' => 'https://api.test',
        ]);

        $this->artisan('events:fetch')->assertExitCode(0);

        $this->assertNotEmpty($requests);
        // pick the first request whose URL contains the word "filter" (the auth
        // token call will not have it)
        $eventRequest = null;
        foreach ($requests as $req) {
            if (str_contains($req->url(), 'filter')) {
                $eventRequest = $req;
                break;
            }
        }
        $this->assertNotNull($eventRequest, 'expected an events endpoint request');

        $url = $eventRequest->url();
        // decode any encoded query parameters before asserting
        $decoded = urldecode($url);

        $yesterday = Carbon::now()->subDay()->startOfDay()->format('Y-m-d\TH:i:s');
        $tomorrow  = Carbon::now()->addDay()->endOfDay()->format('Y-m-d\TH:i:s');

        $this->assertStringContainsString('filter[start__gt]=' . $yesterday, $decoded);
        $this->assertStringContainsString('filter[end__lt]=' . $tomorrow, $decoded);
    }
}
