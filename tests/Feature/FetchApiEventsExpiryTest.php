<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Carbon\Carbon;

class FetchApiEventsExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_respects_expires_in_from_auth(): void
    {
        Cache::flush();

        putenv('DASH_API_ID=app-id');
        putenv('DASH_API_SECRET=app-secret');
        putenv('DASH_API_BASE_URL=https://api.test');

        Http::fake([
            'https://api.test/auth/token' => Http::response(['access_token' => 'exp-token', 'expires_in' => 120], 200),
            '*' => Http::response(['data' => []], 200),
        ]);

        $user = User::factory()->create(['api_base_url' => 'https://api.test']);

        $before = Carbon::now();

        $this->artisan('events:fetch')->assertExitCode(0);

        $this->assertSame('exp-token', Cache::get('dash:app_token'));

        $expiresAt = Cache::get('dash:app_token_expires_at');
        $this->assertNotNull($expiresAt, 'expires_at metadata should be stored');

        $parsed = Carbon::parse($expiresAt);
        $diff = abs($parsed->getTimestamp() - $before->getTimestamp());

        // allow a small margin around the 120s TTL
        $this->assertGreaterThanOrEqual(115, $diff);
        $this->assertLessThanOrEqual(125, $diff);
    }
}
