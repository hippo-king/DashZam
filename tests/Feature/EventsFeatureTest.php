<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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

    public function test_age_group_title_with_sayha_customer_displays_logo()
    {
        config(['app.timezone' => 'America/Los_Angeles']);
        Carbon::setTestNow(Carbon::parse('2026-02-06 12:00:00', 'America/Los_Angeles'));

        $items = [
            [
                'id' => 'sayha-12u',
                'attributes' => [
                    'resource_id' => 1,
                    'customer' => 'SAYHA',
                    'desc' => '12U',
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
        // Expect the SAYHA logo to appear (image source contains SAYHA_LOGO)
        $response->assertSee('SAYHA_LOGO', false);
    }

    public function test_event_with_customer_id_and_included_customer_displays_logo()
    {
        config(['app.timezone' => 'America/Los_Angeles']);
        Carbon::setTestNow(Carbon::parse('2026-02-06 12:00:00', 'America/Los_Angeles'));

        $body = [
            'data' => [
                [
                    'id' => 'ev-1',
                    'attributes' => [
                        'resource_id' => 1,
                        'customer_id' => 3,
                        'desc' => '12U',
                        'start' => Carbon::parse('2026-02-06 13:00:00', 'America/Los_Angeles')->toIso8601String(),
                        'end' => Carbon::parse('2026-02-06 14:00:00', 'America/Los_Angeles')->toIso8601String(),
                    ],
                ],
            ],
            'included' => [
                [
                    'type' => 'customer',
                    'id' => 3,
                    'attributes' => [
                        'name' => 'SAYHA',
                    ],
                ],
            ],
        ];

        User::factory()->create([
            'api_last_payload' => json_encode(['body' => $body]),
            'api_last_fetched_at' => now(),
        ]);

        $response = $this->get('/events');

        $response->assertStatus(200);
        $response->assertSee('SAYHA_LOGO', false);
    }

    public function test_event_with_customer_id_and_customers_array_displays_logo()
    {
        config(['app.timezone' => 'America/Los_Angeles']);
        Carbon::setTestNow(Carbon::parse('2026-02-06 12:00:00', 'America/Los_Angeles'));

        $body = [
            'items' => [
                [
                    'id' => 'ev-2',
                    'attributes' => [
                        'resource_id' => 1,
                        'customer_id' => 3,
                        'desc' => '14U',
                        'start' => Carbon::parse('2026-02-06 13:00:00', 'America/Los_Angeles')->toIso8601String(),
                        'end' => Carbon::parse('2026-02-06 14:00:00', 'America/Los_Angeles')->toIso8601String(),
                    ],
                ],
            ],
            'customers' => [
                [
                    'id' => 3,
                    'name' => 'SAYHA',
                ],
            ],
        ];

        User::factory()->create([
            'api_last_payload' => json_encode(['body' => $body]),
            'api_last_fetched_at' => now(),
        ]);

        $response = $this->get('/events');

        $response->assertStatus(200);
        $response->assertSee('SAYHA_LOGO', false);
    }

    public function test_customer_id_4_maps_to_spokane_braves()
    {
        config(['app.timezone' => 'America/Los_Angeles']);
        Carbon::setTestNow(Carbon::parse('2026-02-06 12:00:00', 'America/Los_Angeles'));

        $body = [
            'data' => [
                [
                    'id' => 'ev-braves',
                    'attributes' => [
                        'resource_id' => 1,
                        'customer_id' => 4,
                        'desc' => '12U',
                        'start' => Carbon::parse('2026-02-06 13:00:00', 'America/Los_Angeles')->toIso8601String(),
                        'end' => Carbon::parse('2026-02-06 14:00:00', 'America/Los_Angeles')->toIso8601String(),
                    ],
                ],
            ],
        ];

        User::factory()->create([
            'api_last_payload' => json_encode(['body' => $body]),
            'api_last_fetched_at' => now(),
        ]);

        $response = $this->get('/events');

        $response->assertStatus(200);
        $response->assertSee('BRAVES_LOGO', false);
    }

    public function test_unresolved_customer_id_is_logged()
    {
        Log::shouldReceive('warning')->once()->withArgs(function ($msg, $ctx) {
            return str_contains($msg, 'Unresolved customer_id')
                && isset($ctx['customer_id'])
                && $ctx['customer_id'] === 99;
        });

        config(['app.timezone' => 'America/Los_Angeles']);
        Carbon::setTestNow(Carbon::parse('2026-02-06 12:00:00', 'America/Los_Angeles'));

        $body = [
            'data' => [
                [
                    'id' => 'ev-unresolved',
                    'attributes' => [
                        'resource_id' => 1,
                        'customer_id' => 99,
                        'desc' => '12U',
                        'start' => Carbon::parse('2026-02-06 13:00:00', 'America/Los_Angeles')->toIso8601String(),
                        'end' => Carbon::parse('2026-02-06 14:00:00', 'America/Los_Angeles')->toIso8601String(),
                    ],
                ],
            ],
        ];

        User::factory()->create([
            'api_last_payload' => json_encode(['body' => $body]),
            'api_last_fetched_at' => now(),
        ]);

        $response = $this->get('/events');
        $response->assertStatus(200);
    }

    public function test_takedown_event_shows_no_logo_in_events_view()
    {
        config(['app.timezone' => 'America/Los_Angeles']);
        // make the takedown event live now
        Carbon::setTestNow(Carbon::parse('2026-02-06 13:05:00', 'America/Los_Angeles'));

        $items = [
            [
                'id' => 'takedown-live',
                'attributes' => [
                    'resource_id' => 1,
                    'customer' => 'SAYHA',
                    'desc' => 'Takedown',
                    'start' => Carbon::parse('2026-02-06 13:00:00', 'America/Los_Angeles')->toIso8601String(),
                    'end' => Carbon::parse('2026-02-06 13:15:00', 'America/Los_Angeles')->toIso8601String(),
                ],
            ],
        ];

        User::factory()->create([
            'api_last_payload' => json_encode(['body' => $items]),
            'api_last_fetched_at' => now(),
        ]);

        $response = $this->get('/events');

        $response->assertStatus(200);
        // Logo should NOT appear for resurfacing/takedown events
        $response->assertDontSee('SAYHA_LOGO', false);
        // zamboni indicator should be present and positioned along progress (~33%)
        $response->assertSee('zamboni', false);
        $response->assertSee('left: 33%', false);
    }

    public function test_takedown_event_has_no_logo_in_timeline()
    {
        config(['app.timezone' => 'America/Los_Angeles']);
        Carbon::setTestNow(Carbon::parse('2026-02-06 12:00:00', 'America/Los_Angeles'));

        $body = [
            'data' => [
                [
                    'id' => 'takedown-tl',
                    'attributes' => [
                        'resource_id' => 1,
                        'customer' => 'SAYHA',
                        'desc' => 'Takedown',
                        'start' => Carbon::parse('2026-02-06 13:00:00', 'America/Los_Angeles')->toIso8601String(),
                        'end' => Carbon::parse('2026-02-06 13:15:00', 'America/Los_Angeles')->toIso8601String(),
                    ],
                ],
            ],
        ];

        User::factory()->create([
            'api_last_payload' => json_encode(['body' => $body]),
            'api_last_fetched_at' => now(),
        ]);

        $response = $this->get(route('events.timeline'));

        $response->assertStatus(200);
        $response->assertDontSee('SAYHA_LOGO', false);
    }

    public function test_duplicate_public_skate_collapses_to_single_entry()
    {
        config(['app.timezone' => 'America/Los_Angeles']);
        Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00', 'America/Los_Angeles'));

        $start = Carbon::parse('2026-03-10 11:00:00', 'America/Los_Angeles')->toIso8601String();
        $end = Carbon::parse('2026-03-10 12:00:00', 'America/Los_Angeles')->toIso8601String();

        $items = [
            [
                'id' => 'ps-adult',
                'attributes' => [
                    'resource_id' => 1,
                    'desc' => 'Public Skate (Adult)',
                    'start' => $start,
                    'end' => $end,
                ],
            ],
            [
                'id' => 'ps-child',
                'attributes' => [
                    'resource_id' => 1,
                    'desc' => 'Public Skate (Children)',
                    'start' => $start,
                    'end' => $end,
                ],
            ],
        ];

        User::factory()->create([
            'api_last_payload' => json_encode(['body' => $items]),
            'api_last_fetched_at' => now(),
        ]);

        $response = $this->get('/events');
        $response->assertStatus(200);
        // should only see one of the two parenthetical variants
        $response->assertSee('Public Skate (Adult)');
        $response->assertDontSee('Public Skate (Children)');

        $response = $this->get(route('events.timeline'));
        $response->assertStatus(200);
        $response->assertSee('Public Skate (Adult)');
        $response->assertDontSee('Public Skate (Children)');
    }

    public function test_duplicate_takedown_events_are_removed()
    {
        config(['app.timezone' => 'America/Los_Angeles']);
        Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00', 'America/Los_Angeles'));

        $start = Carbon::parse('2026-03-10 14:00:00', 'America/Los_Angeles')->toIso8601String();
        $end = Carbon::parse('2026-03-10 14:15:00', 'America/Los_Angeles')->toIso8601String();

        $items = [
            [
                'id' => 't1',
                'attributes' => [
                    'resource_id' => 1,
                    'desc' => 'Takedown',
                    'start' => $start,
                    'end' => $end,
                ],
            ],
            [
                'id' => 't2',
                'attributes' => [
                    'resource_id' => 1,
                    'desc' => 'Takedown',
                    'start' => $start,
                    'end' => $end,
                ],
            ],
        ];

        User::factory()->create([
            'api_last_payload' => json_encode(['body' => $items]),
            'api_last_fetched_at' => now(),
        ]);

        $response = $this->get('/events');
        $response->assertStatus(200);
        // only a single Takedown should appear
        $this->assertEquals(1, substr_count($response->getContent(), 'Takedown'));

        $response = $this->get(route('events.timeline'));
        $response->assertStatus(200);
        $this->assertEquals(1, substr_count($response->getContent(), 'Takedown'));
    }
}
