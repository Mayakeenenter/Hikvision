<?php

namespace Tests\Unit;

use App\Jobs\ProcessHikvisionBackfillJob;
use App\Services\BitrixAttendanceService;
use App\Services\HikvisionService;
use App\Services\ServerSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessHikvisionBackfillJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_fetches_and_stores_events_in_hikvision_events_table(): void
    {
        $startStr = '2026-01-01T00:00:00+03:00';
        $endStr   = '2026-01-07T23:59:59+03:00';

        $mockHikvision = $this->createMock(HikvisionService::class);
        $mockHikvision->method('checkDeviceInfo')->willReturn(true);
        $mockHikvision->expects($this->once())
            ->method('fetchAttendanceEvents')
            ->with($startStr, $endStr)
            ->willReturn([
                [
                    'eventId'          => 'job_ev_101',
                    'employeeNoString' => 'EMP_JOB_1',
                    'name'             => 'Job User',
                    'major'            => 5,
                    'minor'            => 75,
                    'time'             => '2026-01-03T12:00:00+03:00',
                ],
            ]);
        $mockHikvision->expects($this->once())
            ->method('storeEvents')
            ->willReturn(['stored' => 1, 'skipped' => 0]);

        $mockBitrix = $this->createMock(BitrixAttendanceService::class);
        $mockBitrix->expects($this->once())->method('sendAttendanceEvent')->willReturn(true);

        $mockServerSync = $this->createMock(ServerSyncService::class);
        $mockServerSync->expects($this->once())->method('syncPendingEvents')->willReturn(['sent' => 1, 'failed' => 0, 'total' => 1]);

        $job = new ProcessHikvisionBackfillJob($startStr, $endStr);
        $job->handle($mockHikvision, $mockBitrix, $mockServerSync);
    }
}
