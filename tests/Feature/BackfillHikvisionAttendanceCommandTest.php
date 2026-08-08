<?php

namespace Tests\Feature;

use App\Jobs\ProcessHikvisionBackfillJob;
use App\Services\BitrixAttendanceService;
use App\Services\HikvisionService;
use App\Services\ServerSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BackfillHikvisionAttendanceCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_command_processes_chunked_date_ranges_inline(): void
    {
        $mockHikvision = $this->createMock(HikvisionService::class);
        $mockHikvision->method('checkDeviceInfo')->willReturn(true);
        $mockHikvision->expects($this->exactly(2))
            ->method('fetchAttendanceEvents')
            ->willReturn([
                [
                    'eventId'          => 'backfill_ev_01',
                    'employeeNoString' => 'EMP_BF_1',
                    'name'             => 'Backfill User',
                    'major'            => 5,
                    'minor'            => 75,
                    'time'             => '2026-01-02T10:00:00+03:00',
                ],
            ]);
        $mockHikvision->expects($this->exactly(2))
            ->method('storeEvents')
            ->willReturn(['stored' => 1, 'skipped' => 0]);

        $mockBitrix = $this->createMock(BitrixAttendanceService::class);
        $mockBitrix->method('sendAttendanceEvent')->willReturn(true);

        $mockServerSync = $this->createMock(ServerSyncService::class);
        $mockServerSync->method('syncPendingEvents')->willReturn(['sent' => 1, 'failed' => 0, 'total' => 1]);

        $this->app->instance(HikvisionService::class, $mockHikvision);
        $this->app->instance(BitrixAttendanceService::class, $mockBitrix);
        $this->app->instance(ServerSyncService::class, $mockServerSync);

        $this->artisan('attendance:backfill', [
            '--from'       => '2026-01-01',
            '--to'         => '2026-01-14',
            '--chunk-days' => 7,
        ])->assertExitCode(0);
    }

    public function test_backfill_command_dispatches_jobs_in_queue_mode(): void
    {
        Queue::fake();

        $mockHikvision = $this->createMock(HikvisionService::class);
        $mockHikvision->method('checkDeviceInfo')->willReturn(true);
        $this->app->instance(HikvisionService::class, $mockHikvision);

        $this->artisan('attendance:backfill', [
            '--from'       => '2026-01-01',
            '--to'         => '2026-01-14',
            '--chunk-days' => 7,
            '--queue'      => true,
        ])->assertExitCode(0);

        Queue::assertPushed(ProcessHikvisionBackfillJob::class, 2);
    }
}
