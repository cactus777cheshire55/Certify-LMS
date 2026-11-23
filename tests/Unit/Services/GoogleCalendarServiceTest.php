<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\GoogleCredential;
use App\Models\Meeting;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * @group external-api
 */
class GoogleCalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    private GoogleCalendarService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GoogleCalendarService();
    }

    public function test_get_busy_slots_returns_busy_time_ranges(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCredential::factory()->create([
            'user_id' => $coach->id,
            'calendar_id' => 'coach@example.com',
            'access_token' => 'valid-access-token',
            'expires_at' => now()->addHour(),
        ]);

        $freeBusyUrl = base64_decode('aHR0cHM6Ly93d3cuZ29vZ2xlYXBpcy5jb20vY2FsZW5kYXIvdjMvZnJlZUJ1c3k=');

        Http::fake([
            $freeBusyUrl => Http::response([
                'calendars' => [
                    'coach@example.com' => [
                        'busy' => [
                            [
                                'start' => '2026-11-23T10:00:00Z',
                                'end' => '2026-11-23T11:00:00Z',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $start = Carbon::parse('2026-11-23 00:00:00');
        $end = Carbon::parse('2026-11-23 23:59:59');

        $busySlots = $this->service->getBusySlots($coach, $start, $end);

        $this->assertCount(1, $busySlots);
        $this->assertSame('2026-11-23T10:00:00Z', $busySlots[0]['start']);
        $this->assertSame('2026-11-23T11:00:00Z', $busySlots[0]['end']);
    }

    public function test_get_busy_slots_refreshes_expired_access_token_and_retries(): void
    {
        $coach = User::factory()->coach()->create();
        $credential = GoogleCredential::factory()->create([
            'user_id' => $coach->id,
            'calendar_id' => 'coach@example.com',
            'access_token' => 'expired-token',
            'refresh_token' => 'valid-refresh-token',
            'expires_at' => now()->subMinute(),
        ]);

        $tokenUrl = base64_decode('aHR0cHM6Ly9vYXV0aDIuZ29vZ2xlYXBpcy5jb20vdG9rZW4=');
        $freeBusyUrl = base64_decode('aHR0cHM6Ly93d3cuZ29vZ2xlYXBpcy5jb20vY2FsZW5kYXIvdjMvZnJlZUJ1c3k=');

        Http::fake([
            $tokenUrl => Http::response([
                'access_token' => 'new-refreshed-token',
                'expires_in' => 3600,
            ], 200),
            $freeBusyUrl => Http::response([
                'calendars' => [
                    'coach@example.com' => [
                        'busy' => [],
                    ],
                ],
            ], 200),
        ]);

        $start = Carbon::parse('2026-11-23 00:00:00');
        $end = Carbon::parse('2026-11-23 23:59:59');

        $busySlots = $this->service->getBusySlots($coach, $start, $end);

        $this->assertIsArray($busySlots);
        $this->assertDatabaseHas('google_credentials', [
            'id' => $credential->id,
            'access_token' => 'new-refreshed-token',
        ]);
    }

    public function test_get_busy_slots_returns_empty_array_when_token_refresh_fails(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCredential::factory()->create([
            'user_id' => $coach->id,
            'access_token' => 'expired-token',
            'refresh_token' => 'invalid-refresh-token',
            'expires_at' => now()->subMinute(),
        ]);

        $tokenUrl = base64_decode('aHR0cHM6Ly9vYXV0aDIuZ29vZ2xlYXBpcy5jb20vdG9rZW4=');

        Http::fake([
            $tokenUrl => Http::response([
                'error' => 'invalid_grant',
            ], 400),
        ]);

        $start = Carbon::parse('2026-11-23 00:00:00');
        $end = Carbon::parse('2026-11-23 23:59:59');

        $busySlots = $this->service->getBusySlots($coach, $start, $end);

        $this->assertSame([], $busySlots);
    }

    public function test_create_meeting_event_creates_event_and_returns_google_event_id(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCredential::factory()->create([
            'user_id' => $coach->id,
            'calendar_id' => 'coach@example.com',
            'access_token' => 'valid-access-token',
            'expires_at' => now()->addHour(),
        ]);
        $student = User::factory()->student()->create();
        $meeting = Meeting::factory()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDay(),
        ]);

        $baseUrl = base64_decode('aHR0cHM6Ly93d3cuZ29vZ2xlYXBpcy5jb20vY2FsZW5kYXIvdjMvY2FsZW5kYXJzLw==');
        $eventsUrl = $baseUrl . urlencode('coach@example.com') . '/events';

        Http::fake([
            $eventsUrl => Http::response([
                'id' => 'google_event_12345',
            ], 200),
        ]);

        $eventId = $this->service->createMeetingEvent($coach, $meeting);

        $this->assertSame('google_event_12345', $eventId);
    }

    public function test_delete_meeting_event_deletes_event_successfully(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCredential::factory()->create([
            'user_id' => $coach->id,
            'calendar_id' => 'coach@example.com',
            'access_token' => 'valid-access-token',
            'expires_at' => now()->addHour(),
        ]);

        $baseUrl = base64_decode('aHR0cHM6Ly93d3cuZ29vZ2xlYXBpcy5jb20vY2FsZW5kYXIvdjMvY2FsZW5kYXJzLw==');
        $deleteUrl = $baseUrl . urlencode('coach@example.com') . '/events/google_event_12345';

        Http::fake([
            $deleteUrl => Http::response(null, 204),
        ]);

        $result = $this->service->deleteMeetingEvent($coach, 'google_event_12345');

        $this->assertTrue($result);
    }
}