<?php

namespace Tests\Feature;

use App\Models\HikvisionEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HikvisionEventControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_access_dashboard_page(): void
    {
        $response = $this->get('/events');

        $response->assertStatus(200);
        $response->assertViewIs('hikvision.events');
        $response->assertViewHas(['events', 'totalEvents', 'todayEvents', 'authenticatedIn', 'activeEmployees', 'eventTypes']);
    }

    public function test_dashboard_filters_by_search_term(): void
    {
        HikvisionEvent::create([
            'event_id'       => 'ev_101',
            'employee_id'    => '6',
            'employee_name'  => 'Osama Test',
            'event_type'     => 'Authenticated',
            'status_badge'   => 'authenticated',
            'recorded_at'    => now(),
            'event_date'     => now()->toDateString(),
            'event_time'     => now()->format('H:i:s'),
        ]);

        HikvisionEvent::create([
            'event_id'       => 'ev_102',
            'employee_id'    => '11',
            'employee_name'  => 'Sara Test',
            'event_type'     => 'Door Open',
            'status_badge'   => 'doorOpen',
            'recorded_at'    => now(),
            'event_date'     => now()->toDateString(),
            'event_time'     => now()->format('H:i:s'),
        ]);

        $response = $this->get('/events?search=Osama');

        $response->assertStatus(200);
        $response->assertSee('Osama Test');
        $response->assertDontSee('Sara Test');
    }

    public function test_dashboard_filters_by_event_type(): void
    {
        HikvisionEvent::create([
            'event_id'       => 'ev_201',
            'employee_name'  => 'Ahmad',
            'event_type'     => 'Authenticated',
            'status_badge'   => 'authenticated',
            'recorded_at'    => now(),
            'event_date'     => now()->toDateString(),
        ]);

        HikvisionEvent::create([
            'event_id'       => 'ev_202',
            'employee_name'  => 'Ali',
            'event_type'     => 'Authentication Failed',
            'status_badge'   => 'failed',
            'recorded_at'    => now(),
            'event_date'     => now()->toDateString(),
        ]);

        $response = $this->get('/events?event_type=Authenticated');

        $response->assertStatus(200);
        $response->assertSee('Ahmad');
        $response->assertDontSee('Ali');
    }

    public function test_dashboard_filters_by_employee_id(): void
    {
        HikvisionEvent::create([
            'event_id'       => 'ev_301',
            'employee_id'    => 'EMP99',
            'employee_name'  => 'Emp Ninety Nine',
            'event_type'     => 'Authenticated',
            'recorded_at'    => now(),
        ]);

        HikvisionEvent::create([
            'event_id'       => 'ev_302',
            'employee_id'    => 'EMP88',
            'employee_name'  => 'Emp Eighty Eight',
            'event_type'     => 'Authenticated',
            'recorded_at'    => now(),
        ]);

        $response = $this->get('/events?employee_id=EMP99');

        $response->assertStatus(200);
        $response->assertSee('Emp Ninety Nine');
        $response->assertDontSee('Emp Eighty Eight');
    }

    public function test_dashboard_filters_by_date_range(): void
    {
        HikvisionEvent::create([
            'event_id'       => 'ev_401',
            'employee_name'  => 'John Old',
            'recorded_at'    => '2026-01-01 10:00:00',
            'event_date'     => '2026-01-01',
        ]);

        HikvisionEvent::create([
            'event_id'       => 'ev_402',
            'employee_name'  => 'Jane New',
            'recorded_at'    => '2026-08-01 10:00:00',
            'event_date'     => '2026-08-01',
        ]);

        $response = $this->get('/events?date_from=2026-07-01&date_to=2026-08-05');

        $response->assertStatus(200);
        $response->assertSee('Jane New');
        $response->assertDontSee('John Old');
    }

    public function test_dashboard_calculates_summary_statistics(): void
    {
        HikvisionEvent::create([
            'event_id'       => 'stat_1',
            'employee_name'  => 'User A',
            'status_badge'   => 'authenticated',
            'recorded_at'    => now(),
            'event_date'     => now()->toDateString(),
        ]);

        HikvisionEvent::create([
            'event_id'       => 'stat_2',
            'employee_name'  => 'User B',
            'status_badge'   => 'authenticated',
            'recorded_at'    => now(),
            'event_date'     => now()->toDateString(),
        ]);

        $response = $this->get('/events');

        $response->assertStatus(200);
        $response->assertViewHas('totalEvents', 2);
        $response->assertViewHas('todayEvents', 2);
        $response->assertViewHas('authenticatedIn', 2);
        $response->assertViewHas('activeEmployees', 2);
    }
}
