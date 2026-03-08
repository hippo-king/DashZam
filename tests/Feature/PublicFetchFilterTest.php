<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Carbon\Carbon;

class PublicFetchFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_fetch_builds_same_url_as_manual_fetch(): void
    {
        config(['app.timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2026-03-01 12:00:00', 'UTC'));

        // user has a base url and a test endpoint that already includes a hard-coded
        // filter string; clicking the "Fetch Data" link should not double-append
        // the command's own date filter on top of the manually supplied query.
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

        // hit the public fetch route; PublicFetchController will invoke the
        // artisan command which iterates over users including the one above.
        // unauthenticated requests are redirected back, so expect 302.
        $this->post('/events/fetch')->assertStatus(302);

        $this->assertNotEmpty($requests, 'expected at least one outgoing HTTP request');

        // debug dump requests
        dump($requests);

        // find events call with filter parameters
        $eventRequest = null;
        foreach ($requests as $req) {
            if (str_contains($req->url(), 'filter')) {
                $eventRequest = $req;
                break;
            }
        }
        $this->assertNotNull($eventRequest, 'no requests contained filter parameters');
        $url = $eventRequest->url();
        $decoded = urldecode($url);

        // ensure the manually entered filter remains in the final URL
        $this->assertStringContainsString('filter[start__gt]=2026-03-01T00:00:00', $decoded);
        $this->assertStringContainsString('filter[end__lt]=2026-03-02T00:00:00', $decoded);

        // dynamic range should also be merged via &
        $yesterday = Carbon::now()->subDay()->startOfDay()->format('Y-m-d\TH:i:s');
        $tomorrow = Carbon::now()->addDay()->endOfDay()->format('Y-m-d\TH:i:s');

        $this->assertStringContainsString('filter[start__gt]=' . $yesterday, $decoded);
        $this->assertStringContainsString('filter[end__lt]=' . $tomorrow, $decoded);

        // make sure only one question mark is present, i.e. the custom query and
        // appended query were joined with & instead of ?
        $this->assertEquals(1, substr_count($decoded, '?'), 'URL should contain exactly one "?"');
    }
}
