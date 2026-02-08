<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

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
}
