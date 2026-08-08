<?php

namespace Tests\Unit;

use App\Services\BitrixAttendanceService;
use App\Services\BitrixEmployeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BitrixAttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_attendance_event_posts_to_bitrix_webhook(): void
    {
        Http::fake([
            'https://capitalwestern.bitrix24.com/*' => Http::response(['result' => 12345], 200),
        ]);

        $mockEmployeeService = $this->createMock(BitrixEmployeeService::class);
        $mockEmployeeService->method('findEmployeeId')->willReturn(99);

        $service = new BitrixAttendanceService($mockEmployeeService);

        $rawEvent = [
            'eventId'          => 'bitrix_ev_01',
            'name'             => 'Osama',
            'time'             => '2026-08-08T09:00:00+03:00',
            'attendanceStatus' => 'checkIn',
        ];

        $result = $service->sendAttendanceEvent($rawEvent);

        $this->assertTrue($result);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'lists.element.add');
        });
    }

    public function test_send_attendance_event_skips_when_employee_not_matched(): void
    {
        $mockEmployeeService = $this->createMock(BitrixEmployeeService::class);
        $mockEmployeeService->method('findEmployeeId')->willReturn(null);

        $service = new BitrixAttendanceService($mockEmployeeService);

        $rawEvent = [
            'eventId' => 'bitrix_ev_02',
            'name'    => 'Unknown Person',
            'time'    => '2026-08-08T09:00:00+03:00',
        ];

        $result = $service->sendAttendanceEvent($rawEvent);

        $this->assertFalse($result);
    }
}
