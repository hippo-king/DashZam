<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Carbon\Carbon;

class EventsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_past_events_are_excluded()
    {
        config(['app.timezone' => 'America/Los_Angeles']);
        Carbon::setTestNow(Carbon::parse('2026-02-06 12:00:00', 'America/Los_Angeles'));

        $items = [
            [
                'id' => 'past1',
                'attributes' => [
                    'resource_id' => 1,
                    'desc' => 'Past Event',
                    'start' => Carbon::parse('2026-02-06 08:00:00', 'America/Los_Angeles')->toIso8601String(),
                    'end' => Carbon::parse('2026-02-06 09:00:00', 'America/Los_Angeles')->toIso8601String(),
                ],
            ],
            [
                'id' => 'up1',
                'attributes' => [
                    'resource_id' => 1,
                    'desc' => 'Upcoming Event',
                    'start' => Carbon::parse('2026-02-06 13:00:00', 'America/Los_Angeles')->toIso8601String(),
                    'end' => Carbon::parse('2026-02-06 14:00:00', 'America/Los_Angeles')->toIso8601String(),
                ],
            ],
        ];

        User::factory()->create([
            'api_last_payload' => json_encode(['body' => $items]),
            'api_last_fetched_at' => now(),
        ]);

        $response = $this->get('/events');

        $response->assertStatus(200);
        $response->assertSee('Upcoming Event');
        $response->assertDontSee('Past Event');
    }

    public function test_overnight_close_rink_is_included()
    {
        config(['app.timezone' => 'America/Los_Angeles']);
        Carbon::setTestNow(Carbon::parse('2026-02-06 21:00:00', 'America/Los_Angeles'));

        $items = [
            [
                'id' => 'evening',
                'attributes' => [
                    'resource_id' => 1,
                    'desc' => 'Evening Event',
                    'start' => Carbon::parse('2026-02-06 20:00:00', 'America/Los_Angeles')->toIso8601String(),
                    'end' => Carbon::parse('2026-02-06 22:00:00', 'America/Los_Angeles')->toIso8601String(),
                ],
            ],
            [
                'id' => 'early',
                'attributes' => [
                    'resource_id' => 1,
                    'desc' => 'Early Morning Event',
                    'start' => Carbon::parse('2026-02-07 03:00:00', 'America/Los_Angeles')->toIso8601String(),
                    'end' => Carbon::parse('2026-02-07 05:00:00', 'America/Los_Angeles')->toIso8601String(),
                ],
            ],
        ];

        User::factory()->create([
            'api_last_payload' => json_encode(['body' => $items]),
            'api_last_fetched_at' => now(),
        ]);

        $response = $this->get('/events');

        $response->assertStatus(200);
        $response->assertSee('Close Rink');
    }
}
