<?php

namespace App\Http\Controllers\Api\Admin\Events;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventActivity;
use App\Models\EventPanel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
    public function show(string $eventId)
    {
        $event = Event::with(['creator', 'panels.user', 'activities'])->findOrFail($eventId);
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
            'img' => 'nullable|url',
            'status' => 'in:draft,active,cancelled,completed'
        ]);
        $validated['created_by'] = auth()->id();
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
            'img' => 'nullable|url',
            'status' => 'in:draft,active,cancelled,completed'
        ]);
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
     * @throws \Throwable
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
            'img' => 'nullable|url',
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
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create event.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
