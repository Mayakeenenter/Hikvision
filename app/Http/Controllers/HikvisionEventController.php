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

        // Stats summary
        $totalEvents     = HikvisionEvent::count();
        $todayEvents     = HikvisionEvent::whereDate('recorded_at', today())->count();
        $authenticatedIn = HikvisionEvent::where('status_badge', 'authenticated')->count();
        $activeEmployees = HikvisionEvent::whereNotNull('employee_name')
            ->distinct('employee_name')
            ->count('employee_name');

        // Distinct event types for filter dropdown
        $eventTypes = HikvisionEvent::select('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type');

        // Last sync info
        $latestEvent = HikvisionEvent::orderByDesc('recorded_at')->first();

        return view('hikvision.events', compact(
            'events',
            'totalEvents',
            'todayEvents',
            'authenticatedIn',
            'activeEmployees',
            'eventTypes',
            'latestEvent',
        ));
    }
}
