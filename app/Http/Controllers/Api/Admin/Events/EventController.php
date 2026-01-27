<?php

namespace App\Http\Controllers\Api\Admin\Events;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventActivity;
use App\Models\EventPanel;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::with(['creator', 'panels', 'activities'])
            ->where('status', 'active')
            ->paginate(15);

        return response()->json([
            'message' => 'Events retrieved successfully.',
            'data' => $events->items(),
            'pagination' => [
                'total' => $events->total(),
                'count' => $events->count(),
                'per_page' => $events->perPage(),
                'current_page' => $events->currentPage(),
                'total_pages' => $events->lastPage(),
            ]
        ]);
    }

    public function homeIndex()
    {
        try {
            Log::channel('daily')->info('Home events requested');

            $events = Event::with(['creator', 'panels', 'activities'])
                ->where('status', 'active')
                ->whereDate('start_date', '>=', now())
                ->orderBy('start_date', 'asc')
                ->limit(2)
                ->get();

            return response()->json([
                'message' => 'Upcoming events retrieved successfully.',
                'data' => $events,
            ]);

        } catch (Throwable $e) {
            Log::channel('daily')->error('Failed to retrieve home events', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to retrieve upcoming events.',
            ], 500);
        }
    }

    public function adminIndex(Request $request)
    {
        try {
            Log::channel('daily')->info('Admin events index requested', [
                'query' => $request->all(),
                'user_id' => auth()->id(),
            ]);

            $query = Event::with(['creator', 'panels', 'activities']);

            /**
             * 🔍 Search
             * ?search=conference
             */
            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('venue', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            }

            /**
             * 🎯 Filters
             */
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('is_paid')) {
                $query->where('is_paid', filter_var($request->is_paid, FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->filled('created_by')) {
                $query->where('created_by', $request->created_by);
            }

            if ($request->filled('start_date')) {
                $query->whereDate('start_date', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('end_date', '<=', $request->end_date);
            }

            /**
             * 📊 Sorting + Pagination
             */
            $events = $query
                ->orderByDesc('created_at')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'message' => 'Events retrieved successfully.',
                'data' => $events->items(),
                'pagination' => [
                    'total' => $events->total(),
                    'count' => $events->count(),
                    'per_page' => $events->perPage(),
                    'current_page' => $events->currentPage(),
                    'total_pages' => $events->lastPage(),
                ],
            ]);

        } catch (Throwable $e) {
            Log::channel('daily')->error('Failed to retrieve events', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Failed to retrieve events.',
            ], 500);
        }
    }
    public function show(string $eventId)
    {
        $event = Event::with(['creator', 'panels', 'activities'])->findOrFail($eventId);
        return response()->json([
            'message' => 'Event retrieved.',
            'data' => $event
        ]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'venue' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'capacity' => 'nullable|integer|min:1',
            'is_paid' => 'boolean',
            'price_cents' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|max:3',
            'banner' => 'nullable|url',
            'status' => 'in:draft,active,cancelled,completed',
            'registration_deadline' => 'nullable|date|before:start_date'
        ]);
        $validated['created_by'] = auth()->id();
        $validated['img'] = $validated['banner'];
        $event = Event::create($validated);
        return response()->json([
            'message' => 'Event created successfully.',
            'data' => $event
        ], 201);
    }
    public function update(Request $request, string $eventId)
    {
        $event = Event::findOrFail($eventId);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'venue' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'capacity' => 'nullable|integer|min:1',
            'is_paid' => 'boolean',
            'price_cents' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|max:3',
            'banner' => 'nullable|url',
            'status' => 'in:draft,active,cancelled,completed',
            'registration_deadline' => 'nullable|date|before:start_date'
        ]);
        $validated['img'] = $validated['banner'];
        $event->update($validated);
        return response()->json([
            'message' => 'Event updated successfully.',
            'data' => $event
        ]);
    }
    public function destroy(string $eventId)
    {
        $event = Event::findOrFail($eventId);
        $event->delete();
        return response()->json(['message' => 'Event deleted.']);
    }

    /**
     * @throws Throwable
     */
    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            // Event fields
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'venue' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'capacity' => 'nullable|integer|min:1',
            'is_paid' => 'boolean',
            'price_cents' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|max:3',
            'banner' => 'nullable|url',
            'status' => 'in:draft,active,cancelled,completed',
            // Panels
            'panels' => 'array',
            'panels.*.user_id' => 'nullable|exists:users,id',
            'panels.*.external_full_name' => 'nullable|string|max:255',
            'panels.*.external_contact' => 'nullable|string|max:255',
            'panels.*.organization' => 'nullable|string|max:255',
            'panels.*.panel_role' => 'nullable|string|max:255',
            'panels.*.display_order' => 'integer|min:0',
            // Activities
            'activities' => 'array',
            'activities.*.type' => 'required|string|max:100',
            'activities.*.title' => 'required|string|max:255',
            'activities.*.description' => 'nullable|string',
        ]);
        DB::beginTransaction();
        try {
            // Create Event
            $eventData = collect($validated)->except(['panels', 'activities'])->toArray();
            $eventData['created_by'] = auth()->id();
            $eventData['img'] = $eventData['banner'];
            $event = Event::create($eventData);
            // Create Panels
            if (!empty($validated['panels'])) {
                foreach ($validated['panels'] as $panelData) {
                    EventPanel::create(array_merge($panelData, ['event_id' => $event->id]));
                }
            }
            // Create Activities
            if (!empty($validated['activities'])) {
                foreach ($validated['activities'] as $activityData) {
                    EventActivity::create(array_merge($activityData, ['event_id' => $event->id]));
                }
            }
            DB::commit();
            return response()->json([
                'message' => 'Event created successfully with panels and activities.',
                'data' => $event->load(['panels', 'activities'])
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create event.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
