<?php

namespace App\Http\Controllers\Api\Communications;

use App\Http\Controllers\Controller;
use App\Models\Thread;
use App\Models\ThreadMember;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ThreadController extends Controller
{
    /**
     * Get all threads for an authenticated user.
     */
    public function index(Request $request)
    {
        try {
            $userId = Auth::id();

            $query = Thread::whereHas('members', function ($q) use ($userId) {
                $q->where('user_id', $userId)->where('status', 'active');
            })->with(['creator', 'messages' => function ($q) {
                $q->latest()->limit(1);
            }]);

            // Filter by thread type
            if ($request->has('thread_type')) {
                $query->where('thread_type', $request->thread_type);
            }

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Search by title
            if ($request->has('search')) {
                $query->where('title', 'like', '%' . $request->search . '%');
            }

            $threads = $query->orderBy('last_message_at', 'desc')
                ->paginate(20);

            // Add unread count for each thread
            $threads->getCollection()->transform(function ($thread) use ($userId) {
                $thread->unread_count = $thread->getUnreadCount($userId);
                return $thread;
            });

            return response()->json($threads);

        } catch (Exception $e) {
            Log::channel('threads')->error('Failed to fetch threads', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch threads',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new thread.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'nullable|string|max:255',
                'thread_type' => 'required|in:direct,group,forum,event_channel',
                'member_ids' => 'required|array|min:1',
                'member_ids.*' => 'exists:users,id',
                'meta' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            DB::beginTransaction();

            // Create thread
            $thread = Thread::create([
                'title' => $request->title,
                'thread_type' => $request->thread_type,
                'created_by' => Auth::id(),
                'meta' => $request->meta,
                'member_count' => count($request->member_ids) + 1,
            ]);

            // Add creator as owner
            ThreadMember::create([
                'thread_id' => $thread->id,
                'user_id' => Auth::id(),
                'role' => 'owner',
                'status' => 'active',
                'can_add_members' => true,
                'can_remove_members' => true,
            ]);

            // Add other members
            foreach ($request->member_ids as $memberId) {
                if ($memberId != Auth::id()) {
                    ThreadMember::create([
                        'thread_id' => $thread->id,
                        'user_id' => $memberId,
                        'role' => 'member',
                        'status' => 'active',
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Thread created successfully',
                'data' => $thread->load(['creator', 'members.user']),
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();

            Log::channel('threads')->error('Failed to create thread', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => 'Failed to create thread',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get thread by ID.
     */
    public function show($id)
    {
        try {
            $userId = Auth::id();

            $thread = Thread::with([
                'creator',
                'members.user',
                'messages' => function ($q) {
                    $q->orderBy('created_at', 'desc')->limit(50);
                }
            ])->findOrFail($id);

            // Check if user is member
            if (!$thread->hasMember($userId)) {
                return response()->json([
                    'error' => 'Unauthorized access to thread',
                ], 403);
            }

            // Mark as read
            $thread->markAsRead($userId);

            return response()->json($thread);

        } catch (Exception $e) {
            Log::channel('threads')->error('Failed to fetch thread', [
                'thread_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch thread',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update thread.
     */
    public function update(Request $request, $id)
    {
        try {
            $thread = Thread::findOrFail($id);
            $userId = Auth::id();

            // Check if user is owner or moderator
            $member = $thread->members()->where('user_id', $userId)->first();

            if (!$member || !$member->canModerate()) {
                return response()->json([
                    'error' => 'Unauthorized to update thread',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'is_active' => 'sometimes|boolean',
                'is_archived' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $thread->update($request->all());

            return response()->json([
                'message' => 'Thread updated successfully',
                'data' => $thread,
            ]);

        } catch (Exception $e) {
            Log::channel('threads')->error('Failed to update thread', [
                'thread_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update thread',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete thread.
     */
    public function destroy($id)
    {
        try {
            $thread = Thread::findOrFail($id);
            $userId = Auth::id();

            // Only owner can delete
            $member = $thread->members()->where('user_id', $userId)->first();

            if (!$member || !$member->isOwner()) {
                return response()->json([
                    'error' => 'Only thread owner can delete thread',
                ], 403);
            }

            $thread->delete();

            return response()->json([
                'message' => 'Thread deleted successfully',
            ]);

        } catch (Exception $e) {
            Log::channel('threads')->error('Failed to delete thread', [
                'thread_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to delete thread',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add member to thread.
     */
    public function addMember(Request $request, $id)
    {
        try {
            $thread = Thread::findOrFail($id);
            $userId = Auth::id();

            // Check permission
            $member = $thread->members()->where('user_id', $userId)->first();

            if (!$member || !$member->can_add_members) {
                return response()->json([
                    'error' => 'No permission to add members',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Check if already member
            if ($thread->hasMember($request->user_id)) {
                return response()->json([
                    'error' => 'User is already a member',
                ], 400);
            }

            ThreadMember::create([
                'thread_id' => $thread->id,
                'user_id' => $request->user_id,
                'role' => 'member',
                'status' => 'active',
            ]);

            $thread->increment('member_count');

            return response()->json([
                'message' => 'Member added successfully',
            ]);

        } catch (Exception $e) {
            Log::channel('threads')->error('Failed to add member', [
                'thread_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to add member',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove member from thread.
     */
    public function removeMember(Request $request, $id)
    {
        try {
            $thread = Thread::findOrFail($id);
            $userId = Auth::id();

            // Check permission
            $member = $thread->members()->where('user_id', $userId)->first();

            if (!$member || !$member->can_remove_members) {
                return response()->json([
                    'error' => 'No permission to remove members',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Cannot remove owner
            $targetMember = $thread->members()->where('user_id', $request->user_id)->first();

            if ($targetMember && $targetMember->isOwner()) {
                return response()->json([
                    'error' => 'Cannot remove thread owner',
                ], 400);
            }

            ThreadMember::where('thread_id', $thread->id)
                ->where('user_id', $request->user_id)
                ->update(['status' => 'left', 'left_at' => now()]);

            $thread->decrement('member_count');

            return response()->json([
                'message' => 'Member removed successfully',
            ]);

        } catch (Exception $e) {
            Log::channel('threads')->error('Failed to remove member', [
                'thread_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to remove member',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Leave thread.
     */
    public function leave($id)
    {
        try {
            $thread = Thread::findOrFail($id);
            $userId = Auth::id();

            $member = $thread->members()->where('user_id', $userId)->first();

            if (!$member) {
                return response()->json([
                    'error' => 'You are not a member of this thread',
                ], 400);
            }

            // Owner cannot leave
            if ($member->isOwner()) {
                return response()->json([
                    'error' => 'Thread owner cannot leave. Transfer ownership or delete thread.',
                ], 400);
            }

            $member->update([
                'status' => 'left',
                'left_at' => now(),
            ]);

            $thread->decrement('member_count');

            return response()->json([
                'message' => 'Left thread successfully',
            ]);

        } catch (Exception $e) {
            Log::channel('threads')->error('Failed to leave thread', [
                'thread_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to leave thread',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mute/unmute thread.
     */
    public function toggleMute($id)
    {
        try {
            $thread = Thread::findOrFail($id);
            $userId = Auth::id();

            $member = $thread->members()->where('user_id', $userId)->first();

            if (!$member) {
                return response()->json([
                    'error' => 'You are not a member of this thread',
                ], 400);
            }

            $member->update(['muted' => !$member->muted]);

            return response()->json([
                'message' => $member->muted ? 'Thread muted' : 'Thread unmuted',
                'muted' => $member->muted,
            ]);

        } catch (Exception $e) {
            Log::channel('threads')->error('Failed to toggle mute', [
                'thread_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to toggle mute',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
