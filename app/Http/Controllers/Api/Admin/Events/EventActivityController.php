<?php

namespace App\Http\Controllers\Api\Admin\Events;

use App\Http\Controllers\Controller;
use App\Models\EventActivity;
use Illuminate\Http\Request;

class EventActivityController extends Controller
{
    public function index(string $eventId)
    {
        $activities = EventActivity::where('event_id', $eventId)->get();
        return response()->json([
            'message' => 'Activities retrieved.',
            'data' => $activities
        ]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'type' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);
        $activity = EventActivity::create($validated);
        return response()->json([
            'message' => 'Activity created successfully.',
            'data' => $activity
        ], 201);
    }
    public function update(Request $request, string $activityId)
    {
        $activity = EventActivity::findOrFail($activityId);
        $validated = $request->validate([
            'type' => 'sometimes|string|max:100',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string'
        ]);
        $activity->update($validated);
        return response()->json([
            'message' => 'Activity updated successfully.',
            'data' => $activity
        ]);
    }
    public function destroy(string $activityId)
    {
        $activity = EventActivity::findOrFail($activityId);
        $activity->delete();
        return response()->json(['message' => 'Activity deleted.']);
    }
}
