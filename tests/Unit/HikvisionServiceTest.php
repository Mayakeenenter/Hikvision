<?php

namespace Tests\Unit;

use App\Models\HikvisionEvent;
use App\Services\HikvisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HikvisionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected HikvisionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HikvisionService();
    }

    public function test_normalize_event_parses_payload_correctly(): void
    {
        $rawEvent = [
            'eventId'          => 'hik_1001',
            'employeeNoString' => 'EMP123',
            'name'             => 'Khadija',
            'cardNo'           => '99887766',
            'cardReaderNo'     => 1,
            'major'            => 5,
            'minor'            => 75,
            'time'             => '2026-08-08T09:30:00+03:00',
        ];

        $normalized = $this->service->normalizeEvent($rawEvent);

        $this->assertEquals('hik_1001', $normalized['event_id']);
        $this->assertEquals('EMP123', $normalized['employee_id']);
        $this->assertEquals('Khadija', $normalized['employee_name']);
        $this->assertEquals('99887766', $normalized['card_number']);
        $this->assertEquals('Authenticated', $normalized['event_type']);
        $this->assertEquals('authenticated', $normalized['status_badge']);
        $this->assertEquals('2026-08-08', $normalized['event_date']);
        $this->assertEquals('09:30:00', $normalized['event_time']);
    }

    public function test_store_events_persists_records_to_hikvision_events_table(): void
    {
        $rawEvents = [
            [
                'eventId'          => 'hik_2001',
                'employeeNoString' => 'EMP50',
                'name'             => 'Test User 1',
                'major'            => 5,
                'minor'            => 75,
                'time'             => '2026-08-08T10:00:00+03:00',
            ],
            [
                'eventId'          => 'hik_2002',
                'employeeNoString' => 'EMP51',
                'name'             => 'Test User 2',
                'major'            => 5,
                'minor'            => 82,
                'time'             => '2026-08-08T10:05:00+03:00',
            ],
        ];

        $result = $this->service->storeEvents($rawEvents);

        $this->assertEquals(2, $result['stored']);
        $this->assertEquals(0, $result['skipped']);

        $this->assertDatabaseHas('hikvision_events', [
            'event_id'      => 'hik_2001',
            'employee_name' => 'Test User 1',
            'event_type'    => 'Authenticated',
        ]);

        $this->assertDatabaseHas('hikvision_events', [
            'event_id'      => 'hik_2002',
            'employee_name' => 'Test User 2',
            'event_type'    => 'Door Open',
        ]);
    }

    public function test_store_events_skips_duplicates_idempotently(): void
    {
        HikvisionEvent::create([
            'event_id'      => 'hik_3001',
            'employee_name' => 'Existing User',
            'event_type'    => 'Authenticated',
            'recorded_at'   => now(),
        ]);

        $rawEvents = [
            [
                'eventId' => 'hik_3001',
                'name'    => 'Existing User Duplicate',
                'major'   => 5,
                'minor'   => 75,
                'time'    => now()->toIso8601String(),
            ],
            [
                'eventId' => 'hik_3002',
                'name'    => 'New User',
                'major'   => 5,
                'minor'   => 75,
                'time'    => now()->toIso8601String(),
            ],
        ];

        $result = $this->service->storeEvents($rawEvents);

        $this->assertEquals(1, $result['stored']);
        $this->assertEquals(1, $result['skipped']);

        $this->assertDatabaseCount('hikvision_events', 2);
        $this->assertDatabaseHas('hikvision_events', ['event_id' => 'hik_3002']);
    }
}
