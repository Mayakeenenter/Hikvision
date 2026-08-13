<?php

namespace Tests\Unit;

use App\Models\HikvisionEvent;
use App\Services\ServerSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ServerSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_event_updates_synced_to_server_flag(): void
    {
        Http::fake([
            'https://keenenter.com/*' => Http::response(['success' => true, 'count' => 1], 200),
        ]);

        $event = HikvisionEvent::create([
            'event_id'         => 'srv_ev_01',
            'employee_id'      => '10',
            'employee_name'    => 'Server Test User',
            'event_type'       => 'Authenticated',
            'recorded_at'      => now(),
            'synced_to_server' => false,
        ]);

        $service = new ServerSyncService();
        $result  = $service->sendEvent($event);

        $this->assertTrue($result);
        $this->assertTrue($event->fresh()->synced_to_server);
    }

    public function test_sync_pending_events_processes_un_synced_records(): void
    {
        Http::fake([
            'https://keenenter.com/*' => Http::response(['success' => true, 'count' => 1], 200),
        ]);

        HikvisionEvent::create([
            'event_id'         => 'srv_ev_02',
            'employee_id'      => '11',
            'employee_name'    => 'Pending User 1',
            'recorded_at'      => now(),
            'synced_to_server' => false,
        ]);

        HikvisionEvent::create([
            'event_id'         => 'srv_ev_03',
            'employee_id'      => '12',
            'employee_name'    => 'Pending User 2',
            'synced_to_server' => true,
        ]);

        $service = new ServerSyncService();
        $result  = $service->syncPendingEvents();

        $this->assertEquals(1, $result['total']);
        $this->assertEquals(1, $result['sent']);
        $this->assertEquals(0, $result['failed']);
    }
}
