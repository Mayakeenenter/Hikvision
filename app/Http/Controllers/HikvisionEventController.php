<?php

namespace App\Http\Controllers;

use App\Models\HikvisionEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HikvisionEventController extends Controller
{
    /**
     * Display the main event dashboard with filtering and pagination.
     */
    public function index(Request $request)
    {
        $query = HikvisionEvent::query()->orderByDesc('recorded_at');

        // Search: employee name, ID, event type
        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        // Filter by event type
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }

        // Filter by status badge (checkIn, checkOut, authenticated, doorOpen, doorClosed, exitButton, failed, alarm)
        if ($request->filled('badge')) {
            $query->where('status_badge', $request->input('badge'));
        }

        // Filter by employee ID
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('recorded_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('recorded_at', '<=', $request->input('date_to'));
        }

        // Paginate
        $perPage = (int) $request->input('per_page', 24);
        $events  = $query->paginate($perPage)->withQueryString();

        // Comprehensive stats summary
        $totalEvents      = HikvisionEvent::count();
        $todayEvents      = HikvisionEvent::whereDate('recorded_at', today())->count();
        $authenticatedIn  = HikvisionEvent::where('status_badge', 'authenticated')->count();
        $checkInCount     = HikvisionEvent::where('status_badge', 'checkIn')->count();
        $checkOutCount    = HikvisionEvent::where('status_badge', 'checkOut')->count();
        $doorOpenCount    = HikvisionEvent::where('status_badge', 'doorOpen')->count();
        $doorClosedCount  = HikvisionEvent::where('status_badge', 'doorClosed')->count();
        $exitButtonCount  = HikvisionEvent::where('status_badge', 'exitButton')->count();
        $failedCount      = HikvisionEvent::where('status_badge', 'failed')->count();
        $alarmCount       = HikvisionEvent::where('status_badge', 'alarm')->count();

        $activeEmployees  = HikvisionEvent::whereNotNull('employee_name')
            ->where('employee_name', '!=', '')
            ->distinct('employee_name')
            ->count('employee_name');

        // Distinct event types from DB merged with known categories
        $dbTypes = HikvisionEvent::whereNotNull('event_type')
            ->where('event_type', '!=', '')
            ->select('event_type')
            ->distinct()
            ->pluck('event_type')
            ->toArray();

        $standardTypes = [
            'Authenticated',
            'Check In',
            'Check Out',
            'Door Open',
            'Door Closed',
            'Exit Button Pressed',
            'Exit Button Released',
            'Authentication Failed',
            'Break Out',
            'Break In',
            'Door Open Timeout',
            'Door Forced Open',
            'Access Control Event',
        ];

        $eventTypes = array_values(array_unique(array_filter(array_merge($standardTypes, $dbTypes))));
        sort($eventTypes);

        // Last sync info
        $latestEvent = HikvisionEvent::orderByDesc('recorded_at')->first();

        return view('hikvision.events', compact(
            'events',
            'totalEvents',
            'todayEvents',
            'authenticatedIn',
            'checkInCount',
            'checkOutCount',
            'doorOpenCount',
            'doorClosedCount',
            'exitButtonCount',
            'failedCount',
            'alarmCount',
            'activeEmployees',
            'eventTypes',
            'latestEvent',
        ));
    }
}
