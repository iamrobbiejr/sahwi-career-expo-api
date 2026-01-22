<?php

namespace App\Http\Controllers\Api\Emails;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessEmailBroadcastJob;
use App\Models\EmailBroadcast;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EmailBroadcastController extends Controller
{
    /**
     * Display a listing of broadcasts.
     */
    public function index(Request $request)
    {
        try {
            $query = EmailBroadcast::with(['sender', 'targetUniversity', 'targetEvent']);

            // Filter by sender
            if ($request->has('sender_id')) {
                $query->where('sender_id', $request->sender_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by audience type
            if ($request->has('audience_type')) {
                $query->where('audience_type', $request->audience_type);
            }

            // Filter by date range
            if ($request->has('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }

            $broadcasts = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json($broadcasts);

        } catch (Exception $e) {
            Log::channel('email_broadcast')->error('Failed to fetch broadcasts', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch broadcasts',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created broadcast.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'subject' => 'required|string|max:255',
                'message' => 'required|string',
                'from_email' => 'nullable|email',
                'from_name' => 'nullable|string|max:255',
                'reply_to_email' => 'nullable|email',
                'audience_type' => 'required|in:all_users,university_interested,event_registered,custom',
                'target_university_id' => 'required_if:audience_type,university_interested|exists:universities,id',
                'target_event_id' => 'required_if:audience_type,event_registered|exists:events,id',
                'custom_user_ids' => 'required_if:audience_type,custom|array',
                'custom_user_ids.*' => 'exists:users,id',
                'filters' => 'nullable|array',
                'scheduled_at' => 'nullable|date|after:now',
                'template' => 'nullable|string',
                'attachments' => 'nullable|array',
                'attachments.*' => 'file|max:10240', // 10MB max
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Handle file uploads
            $attachmentPaths = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('broadcast-attachments', 'private');
                    $attachmentPaths[] = $path;
                }
            }

            // Create broadcast
            $broadcast = EmailBroadcast::create([
                'sender_id' => Auth::id(),
                'sender_type' => $request->input('sender_type', 'admin'),
                'sender_entity_id' => $request->input('sender_entity_id'),
                'subject' => $request->subject,
                'message' => $request->message,
                'from_email' => $request->from_email,
                'from_name' => $request->from_name,
                'reply_to_email' => $request->reply_to_email,
                'audience_type' => $request->audience_type,
                'target_university_id' => $request->target_university_id,
                'target_event_id' => $request->target_event_id,
                'custom_user_ids' => $request->custom_user_ids,
                'filters' => $request->filters,
                'scheduled_at' => $request->scheduled_at,
                'is_scheduled' => $request->has('scheduled_at'),
                'template' => $request->template,
                'attachments' => $attachmentPaths,
                'track_opens' => $request->input('track_opens', true),
                'track_clicks' => $request->input('track_clicks', true),
                'status' => 'draft',
            ]);

            $broadcast->log('info', 'Broadcast created', [], 'created');

            return response()->json([
                'message' => 'Broadcast created successfully',
                'data' => $broadcast->load(['sender', 'targetUniversity', 'targetEvent']),
            ], 201);

        } catch (Exception $e) {
            Log::channel('email_broadcast')->error('Failed to create broadcast', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => 'Failed to create broadcast',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified broadcast.
     */
    public function show($id)
    {
        try {
            $broadcast = EmailBroadcast::with([
                'sender',
                'targetUniversity',
                'targetEvent',
                'recipients' => function ($query) {
                    $query->latest()->limit(100);
                },
                'logs' => function ($query) {
                    $query->latest()->limit(50);
                },
            ])->findOrFail($id);

            return response()->json([
                'broadcast' => $broadcast,
                'statistics' => [
                    'total_recipients' => $broadcast->total_recipients,
                    'sent_count' => $broadcast->sent_count,
                    'failed_count' => $broadcast->failed_count,
                    'opened_count' => $broadcast->opened_count,
                    'clicked_count' => $broadcast->clicked_count,
                    'success_rate' => $broadcast->getSuccessRate(),
                    'open_rate' => $broadcast->getOpenRate(),
                    'click_rate' => $broadcast->getClickRate(),
                ],
            ]);

        } catch (Exception $e) {
            Log::channel('email_broadcast')->error('Failed to fetch broadcast', [
                'broadcast_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch broadcast',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified broadcast.
     */
    public function update(Request $request, $id)
    {
        try {
            $broadcast = EmailBroadcast::findOrFail($id);

            // Only allow updates for draft broadcasts
            if ($broadcast->status !== 'draft') {
                return response()->json([
                    'error' => 'Only draft broadcasts can be updated',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'subject' => 'sometimes|string|max:255',
                'message' => 'sometimes|string',
                'from_email' => 'nullable|email',
                'from_name' => 'nullable|string|max:255',
                'reply_to_email' => 'nullable|email',
                'audience_type' => 'sometimes|in:all_users,university_interested,event_registered,custom',
                'scheduled_at' => 'nullable|date|after:now',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $broadcast->update($request->all());

            $broadcast->log('info', 'Broadcast updated', $request->all(), 'updated');

            return response()->json([
                'message' => 'Broadcast updated successfully',
                'data' => $broadcast->load(['sender', 'targetUniversity', 'targetEvent']),
            ]);

        } catch (Exception $e) {
            Log::channel('email_broadcast')->error('Failed to update broadcast', [
                'broadcast_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update broadcast',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete the specified broadcast.
     */
    public function destroy($id)
    {
        try {
            $broadcast = EmailBroadcast::findOrFail($id);

            // Only allow deletion of draft or failed broadcasts
            if (!in_array($broadcast->status, ['draft', 'failed', 'cancelled'])) {
                return response()->json([
                    'error' => 'Only draft, failed, or cancelled broadcasts can be deleted',
                ], 400);
            }

            // Delete attachments
            if (!empty($broadcast->attachments)) {
                foreach ($broadcast->attachments as $path) {
                    Storage::disk('private')->delete($path);
                }
            }

            $broadcast->delete();

            return response()->json([
                'message' => 'Broadcast deleted successfully',
            ]);

        } catch (Exception $e) {
            Log::channel('email_broadcast')->error('Failed to delete broadcast', [
                'broadcast_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to delete broadcast',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send the broadcast immediately.
     */
    public function send($id)
    {
        try {
            $broadcast = EmailBroadcast::findOrFail($id);

            // Only allow sending draft broadcasts
            if ($broadcast->status !== 'draft') {
                return response()->json([
                    'error' => 'Only draft broadcasts can be sent',
                ], 400);
            }

            // Update status to queued
            $broadcast->update(['status' => 'queued']);

            // Dispatch job to process broadcast
            ProcessEmailBroadcastJob::dispatch($broadcast)
                ->onQueue('broadcasts');

            $broadcast->log('info', 'Broadcast queued for sending', [], 'queued');

            return response()->json([
                'message' => 'Broadcast queued successfully and will be processed shortly',
                'data' => $broadcast,
            ]);

        } catch (Exception $e) {
            Log::channel('email_broadcast')->error('Failed to queue broadcast', [
                'broadcast_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to queue broadcast',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a scheduled or processing broadcast.
     */
    public function cancel($id)
    {
        try {
            $broadcast = EmailBroadcast::findOrFail($id);

            // Only allow canceling scheduled, queued, or processing broadcasts
            if (!in_array($broadcast->status, ['draft', 'queued', 'processing'])) {
                return response()->json([
                    'error' => 'This broadcast cannot be cancelled',
                ], 400);
            }

            $broadcast->update(['status' => 'cancelled']);

            $broadcast->log('info', 'Broadcast cancelled', [], 'cancelled');

            return response()->json([
                'message' => 'Broadcast cancelled successfully',
                'data' => $broadcast,
            ]);

        } catch (Exception $e) {
            Log::channel('email_broadcast')->error('Failed to cancel broadcast', [
                'broadcast_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to cancel broadcast',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get broadcast statistics.
     */
    public function statistics($id)
    {
        try {
            $broadcast = EmailBroadcast::with(['recipients'])->findOrFail($id);

            $statistics = [
                'overview' => [
                    'total_recipients' => $broadcast->total_recipients,
                    'sent_count' => $broadcast->sent_count,
                    'failed_count' => $broadcast->failed_count,
                    'opened_count' => $broadcast->opened_count,
                    'clicked_count' => $broadcast->clicked_count,
                    'success_rate' => $broadcast->getSuccessRate(),
                    'open_rate' => $broadcast->getOpenRate(),
                    'click_rate' => $broadcast->getClickRate(),
                ],
                'status_breakdown' => $broadcast->recipients()
                    ->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->get(),
                'timeline' => [
                    'created_at' => $broadcast->created_at,
                    'started_at' => $broadcast->started_at,
                    'completed_at' => $broadcast->completed_at,
                    'duration' => $broadcast->started_at && $broadcast->completed_at
                        ? $broadcast->completed_at->diffInSeconds($broadcast->started_at)
                        : null,
                ],
            ];

            return response()->json($statistics);

        } catch (Exception $e) {
            Log::channel('email_broadcast')->error('Failed to fetch statistics', [
                'broadcast_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch statistics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview broadcast recipients count.
     */
    public function previewRecipients(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'audience_type' => 'required|in:all_users,university_interested,event_registered,custom',
                'target_university_id' => 'required_if:audience_type,university_interested',
                'target_event_id' => 'required_if:audience_type,event_registered',
                'custom_user_ids' => 'required_if:audience_type,custom|array',
                'filters' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Create a temporary broadcast to use the same logic
            $tempBroadcast = new EmailBroadcast($request->all());
            $job = new ProcessEmailBroadcastJob($tempBroadcast);

            // Use reflection to call protected method
            $reflection = new \ReflectionClass($job);
            $method = $reflection->getMethod('getRecipients');
            $recipients = $method->invoke($job);

            return response()->json([
                'recipient_count' => $recipients->count(),
                'sample_recipients' => $recipients->take(10),
            ]);

        } catch (Exception $e) {
            Log::channel('email_broadcast')->error('Failed to preview recipients', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to preview recipients',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
