<?php

namespace App\Http\Controllers\Api\Admin\Events;

use App\Http\Controllers\Controller;
use App\Models\EventPanel;
use Illuminate\Http\Request;

class EventPanelController extends Controller
{
    public function index(string $eventId)
    {
        $panels = EventPanel::where('event_id', $eventId)
//            ->with('user')
            ->orderBy('display_order')
            ->get();
        return response()->json([
            'message' => 'Panels retrieved.',
            'data' => $panels
        ]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'user_id' => 'nullable|exists:users,id',
            'external_full_name' => 'nullable|string|max:255',
            'external_contact' => 'nullable|string|max:255',
            'organization' => 'nullable|string|max:255',
            'panel_role' => 'nullable|string|max:255',
            'display_order' => 'integer|min:0'
        ]);
        $panel = EventPanel::create($validated);
        return response()->json([
            'message' => 'Panel added successfully.',
            'data' => $panel
        ], 201);
    }
    public function update(Request $request, string $panelId)
    {
        $panel = EventPanel::findOrFail($panelId);
        $validated = $request->validate([
            'external_full_name' => 'sometimes|string|max:255',
            'external_contact' => 'sometimes|string|max:255',
            'organization' => 'sometimes|string|max:255',
            'panel_role' => 'sometimes|string|max:255',
            'display_order' => 'integer|min:0'
        ]);
        $panel->update($validated);
        return response()->json([
            'message' => 'Panel updated successfully.',
            'data' => $panel
        ]);
    }
    public function destroy(string $panelId)
    {
        $panel = EventPanel::findOrFail($panelId);
        $panel->delete();
        return response()->json(['message' => 'Panel removed.']);
    }
}
