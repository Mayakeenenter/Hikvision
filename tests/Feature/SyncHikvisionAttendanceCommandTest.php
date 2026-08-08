<?php

namespace Tests\Feature;

use App\Models\HikvisionEvent;
use App\Models\SyncLog;
use App\Services\BitrixAttendanceService;
use App\Services\HikvisionService;
use App\Services\ServerSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SyncHikvisionAttendanceCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_command_fetches_and_stores_data_to_hikvision_events(): void
    {
        $mockHikvision = $this->createMock(HikvisionService::class);
        $mockHikvision->method('checkDeviceInfo')->willReturn(true);
        $mockHikvision->expects($this->once())
            ->method('fetchAttendanceEvents')
            ->willReturn([
                [
                    'eventId'          => 'sync_ev_01',
                    'employeeNoString' => 'EMP_SYNC_1',
                    'name'             => 'Sync User',
                    'major'            => 5,
                    'minor'            => 75,
                    'time'             => '2026-08-08T08:00:00+03:00',
                ],
            ]);
        $mockHikvision->expects($this->once())
            ->method('storeEvents')
            ->willReturn(['stored' => 1, 'skipped' => 0]);

        $mockBitrix = $this->createMock(BitrixAttendanceService::class);
        $mockBitrix->method('sendAttendanceEvent')->willReturn(true);

        $mockServerSync = $this->createMock(ServerSyncService::class);
        $mockServerSync->method('syncPendingEvents')->willReturn(['sent' => 1, 'failed' => 0, 'total' => 1]);

        $this->app->instance(HikvisionService::class, $mockHikvision);
        $this->app->instance(BitrixAttendanceService::class, $mockBitrix);
        $this->app->instance(ServerSyncService::class, $mockServerSync);

        $this->artisan('attendance:sync')
            ->assertExitCode(0);

        $this->assertDatabaseHas('sync_logs', [
            'status' => 'success',
            'total_records' => 1,
        ]);
    }

    public function test_sync_command_resolves_dynamic_window_from_latest_db_event(): void
    {
        HikvisionEvent::create([
            'event_id'    => 'prev_01',
            'recorded_at' => '2026-08-05 12:00:00',
            'event_date'  => '2026-08-05',
        ]);

        $mockHikvision = $this->createMock(HikvisionService::class);
        $mockHikvision->method('checkDeviceInfo')->willReturn(true);
        $mockHikvision->expects($this->once())
            ->method('fetchAttendanceEvents')
            ->with(
                $this->callback(function ($startTime) {
                    return str_contains($startTime, '2026-08-05T11:00:00'); // latest recorded_at minus 1 hour
                }),
                $this->anything()
            )
            ->willReturn([]);

        $mockServerSync = $this->createMock(ServerSyncService::class);
        $mockServerSync->method('syncPendingEvents')->willReturn(['sent' => 0, 'failed' => 0, 'total' => 0]);

        $this->app->instance(HikvisionService::class, $mockHikvision);
        $this->app->instance(ServerSyncService::class, $mockServerSync);

        $this->artisan('attendance:sync')
            ->assertExitCode(0);
    }
}
