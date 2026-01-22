<?php

namespace App\Http\Controllers\Api\Admin\Events;

use App\Http\Controllers\Controller;
use App\Models\ConferenceCall;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ConferenceCallController extends Controller
{
    /**
     * Display a listing of conference calls.
     */
    public function index(Request $request)
    {
        $query = ConferenceCall::with('event');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by platform
        if ($request->has('platform')) {
            $query->where('platform', $request->platform);
        }

        // Filter by event
        if ($request->has('event_id')) {
            $query->where('event_id', $request->event_id);
        }

        // Filter upcoming meetings
        if ($request->has('upcoming') && $request->upcoming) {
            $query->upcoming();
        }

        $conferenceCalls = $query->orderBy('scheduled_start', 'desc')->paginate(15);

        return response()->json($conferenceCalls);
    }

    /**
     * Store a newly created conference call.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|exists:events,id',
            'platform' => 'required|string|in:zoom,teams,google_meet,webex,custom',
            'meeting_url' => 'required|url',
            'meeting_id' => 'nullable|string',
            'passcode' => 'nullable|string',
            'host_name' => 'nullable|string|max:255',
            'host_email' => 'nullable|email',
            'max_participants' => 'nullable|integer|min:2',
            'duration_minutes' => 'nullable|integer|min:15',
            'scheduled_start' => 'nullable|date',
            'scheduled_end' => 'nullable|date|after:scheduled_start',
            'instructions' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Verify event is virtual
        $event = Event::findOrFail($request->event_id);
        if ($event->location !== 'Virtual') {
            return response()->json([
                'error' => 'Conference calls can only be created for virtual events'
            ], 400);
        }

        $conferenceCall = ConferenceCall::create($request->all());

        return response()->json([
            'message' => 'Conference call created successfully',
            'data' => $conferenceCall->load('event')
        ], 201);
    }

    /**
     * Display the specified conference call.
     */
    public function show($id)
    {
        $conferenceCall = ConferenceCall::with('event')->findOrFail($id);

        return response()->json($conferenceCall);
    }

    /**
     * Update the specified conference call.
     */
    public function update(Request $request, $id)
    {
        $conferenceCall = ConferenceCall::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'platform' => 'sometimes|string|in:zoom,teams,google_meet,webex,custom',
            'meeting_url' => 'sometimes|url',
            'meeting_id' => 'nullable|string',
            'passcode' => 'nullable|string',
            'host_name' => 'nullable|string|max:255',
            'host_email' => 'nullable|email',
            'max_participants' => 'nullable|integer|min:2',
            'duration_minutes' => 'nullable|integer|min:15',
            'scheduled_start' => 'nullable|date',
            'scheduled_end' => 'nullable|date|after:scheduled_start',
            'status' => 'sometimes|in:scheduled,live,ended,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $conferenceCall->update($request->all());

        return response()->json([
            'message' => 'Conference call updated successfully',
            'data' => $conferenceCall->load('event')
        ]);
    }

    /**
     * Remove the specified conference call.
     */
    public function destroy($id)
    {
        $conferenceCall = ConferenceCall::findOrFail($id);
        $conferenceCall->delete();

        return response()->json([
            'message' => 'Conference call deleted successfully'
        ]);
    }

    /**
     * Start a conference call.
     */
    public function start($id)
    {
        $conferenceCall = ConferenceCall::findOrFail($id);

        if ($conferenceCall->status !== 'scheduled') {
            return response()->json([
                'error' => 'Only scheduled meetings can be started'
            ], 400);
        }

        $conferenceCall->start();

        return response()->json([
            'message' => 'Conference call started successfully',
            'data' => $conferenceCall
        ]);
    }

    /**
     * End a conference call.
     */
    public function end($id)
    {
        $conferenceCall = ConferenceCall::findOrFail($id);

        if ($conferenceCall->status !== 'live') {
            return response()->json([
                'error' => 'Only live meetings can be ended'
            ], 400);
        }

        $conferenceCall->end();

        return response()->json([
            'message' => 'Conference call ended successfully',
            'data' => $conferenceCall
        ]);
    }

    /**
     * Cancel a conference call.
     */
    public function cancel(Request $request, $id)
    {
        $conferenceCall = ConferenceCall::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $conferenceCall->cancel($request->reason);

        return response()->json([
            'message' => 'Conference call cancelled successfully',
            'data' => $conferenceCall
        ]);
    }

    /**
     * Get meeting credentials for participants.
     */
    public function getCredentials($id)
    {
        $conferenceCall = ConferenceCall::findOrFail($id);

        return response()->json([
            'credentials' => $conferenceCall->getCredentials(),
            'instructions' => $conferenceCall->instructions,
            'scheduled_start' => $conferenceCall->scheduled_start,
        ]);
    }

    /**
     * Get upcoming meetings.
     */
    public function upcoming()
    {
        $upcomingCalls = ConferenceCall::with('event')
            ->upcoming()
            ->orderBy('scheduled_start', 'asc')
            ->get();

        return response()->json($upcomingCalls);
    }
}
