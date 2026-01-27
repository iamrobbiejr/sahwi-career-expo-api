<?php

namespace App\Http\Controllers\Api\Communications;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Thread;
use App\Models\ThreadMember;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    /**
     * Get messages for a thread.
     */
    public function index(Request $request, $threadId)
    {
        try {
            $userId = Auth::id();
            $thread = Thread::findOrFail($threadId);

            // Check if user is member
            if (!$thread->hasMember($userId)) {
                return response()->json([
                    'error' => 'Unauthorized access to thread',
                ], 403);
            }

            $query = Message::where('thread_id', $threadId)
                ->with(['sender', 'replyTo.sender'])
                ->where('status', '!=', 'deleted');

            // Pagination
            $perPage = $request->input('per_page', 50);
            $messages = $query->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Mark messages as read
            foreach ($messages as $message) {
                if ($message->sender_id != $userId) {
                    $message->markAsReadBy($userId);
                }
            }

            // Update last read timestamp
            $thread->markAsRead($userId);

            return response()->json($messages);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to fetch messages', [
                'thread_id' => $threadId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch messages',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send a message.
     */
    public function store(Request $request, $threadId)
    {
        try {
            $userId = Auth::id();
            $thread = Thread::findOrFail($threadId);

            // Check if user is member and can send messages
            $member = $thread->members()->where('user_id', $userId)->first();

            if (!$member || !$member->isActive()) {
                return response()->json([
                    'error' => 'You cannot send messages in this thread',
                ], 403);
            }

            if (!$member->can_send_messages) {
                return response()->json([
                    'error' => 'You do not have permission to send messages',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'content_message' => 'required|string',
                'reply_to_message_id' => 'nullable|exists:messages,id',
                'message_type' => 'nullable|in:text,image,file,system',
                'attachments' => 'nullable|array',
                'attachments.*' => 'file|max:10240', // 10MB
                'mentions' => 'nullable|array',
                'mentions.*' => 'exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            DB::beginTransaction();

            // Handle file uploads
            $attachmentPaths = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('message-attachments', 'private');
                    $attachmentPaths[] = [
                        'path' => $path,
                        'name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                    ];
                }
            }

            // Create a message
            $message = Message::create([
                'thread_id' => $threadId,
                'sender_id' => $userId,
                'content' => $request->content_message,
                'reply_to_message_id' => $request->reply_to_message_id,
                'message_type' => $request->input('message_type', 'text'),
                'attachments' => $attachmentPaths,
                'mentions' => $request->mentions,
                'status' => 'sent',
            ]);

            // Update thread
            $thread->update([
                'last_message_at' => now(),
            ]);
            $thread->increment('message_count');

            // Update unread count for other members
            ThreadMember::where('thread_id', $threadId)
                ->where('user_id', '!=', $userId)
                ->where('status', 'active')
                ->increment('unread_count');

            DB::commit();

            return response()->json([
                'message' => 'Message sent successfully',
                'data' => $message->load(['sender', 'replyTo']),
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();

           Log::channel('threads')->error('Failed to send message', [
                'thread_id' => $threadId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => 'Failed to send message',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific message.
     */
    public function show($threadId, $messageId)
    {
        try {
            $userId = Auth::id();
            $thread = Thread::findOrFail($threadId);

            // Check if user is member
            if (!$thread->hasMember($userId)) {
                return response()->json([
                    'error' => 'Unauthorized access',
                ], 403);
            }

            $message = Message::where('thread_id', $threadId)
                ->where('id', $messageId)
                ->with(['sender', 'replyTo'])
                ->firstOrFail();

            return response()->json($message);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to fetch message', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch message',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update/Edit a message.
     */
    public function update(Request $request, $threadId, $messageId)
    {
        try {
            $userId = Auth::id();
            $message = Message::where('thread_id', $threadId)
                ->where('id', $messageId)
                ->firstOrFail();

            // Only sender can edit
            if ($message->sender_id != $userId) {
                return response()->json([
                    'error' => 'You can only edit your own messages',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'content' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Store original content if first edit
            if ($message->status !== 'edited') {
                $message->original_content = $message->content;
            }

            $message->update([
                'content' => $request->content_message,
                'status' => 'edited',
                'edited_at' => now(),
            ]);

            return response()->json([
                'message' => 'Message updated successfully',
                'data' => $message->load(['sender', 'replyTo']),
            ]);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to update message', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update message',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a message.
     */
    public function destroy($threadId, $messageId)
    {
        try {
            $userId = Auth::id();
            $thread = Thread::findOrFail($threadId);
            $message = Message::where('thread_id', $threadId)
                ->where('id', $messageId)
                ->firstOrFail();

            // Check permissions
            $member = $thread->members()->where('user_id', $userId)->first();
            $canDelete = $message->sender_id == $userId ||
                ($member && $member->canModerate());

            if (!$canDelete) {
                return response()->json([
                    'error' => 'Unauthorized to delete this message',
                ], 403);
            }

            // Soft delete
            $message->update([
                'status' => 'deleted',
                'content' => '[Message deleted]',
            ]);

            // Delete attachments
            if (!empty($message->attachments)) {
                foreach ($message->attachments as $attachment) {
                    Storage::disk('private')->delete($attachment['path']);
                }
            }

            $thread->decrement('message_count');

            return response()->json([
                'message' => 'Message deleted successfully',
            ]);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to delete message', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to delete message',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add reaction to message.
     */
    public function addReaction(Request $request, $threadId, $messageId)
    {
        try {
            $userId = Auth::id();
            $thread = Thread::findOrFail($threadId);

            // Check if user is member
            if (!$thread->hasMember($userId)) {
                return response()->json([
                    'error' => 'Unauthorized access',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'emoji' => 'required|string|max:10',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $message = Message::where('thread_id', $threadId)
                ->where('id', $messageId)
                ->firstOrFail();

            $message->addReaction($userId, $request->emoji);

            return response()->json([
                'message' => 'Reaction added successfully',
                'reactions' => $message->fresh()->reactions,
            ]);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to add reaction', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to add reaction',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove reaction from message.
     */
    public function removeReaction(Request $request, $threadId, $messageId)
    {
        try {
            $userId = Auth::id();
            $thread = Thread::findOrFail($threadId);

            // Check if user is member
            if (!$thread->hasMember($userId)) {
                return response()->json([
                    'error' => 'Unauthorized access',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'emoji' => 'required|string|max:10',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $message = Message::where('thread_id', $threadId)
                ->where('id', $messageId)
                ->firstOrFail();

            $message->removeReaction($userId, $request->emoji);

            return response()->json([
                'message' => 'Reaction removed successfully',
                'reactions' => $message->fresh()->reactions,
            ]);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to remove reaction', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to remove reaction',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search messages in a thread.
     */
    public function search(Request $request, $threadId)
    {
        try {
            $userId = Auth::id();
            $thread = Thread::findOrFail($threadId);

            // Check if user is member
            if (!$thread->hasMember($userId)) {
                return response()->json([
                    'error' => 'Unauthorized access',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'query' => 'required|string|min:2',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $messages = Message::where('thread_id', $threadId)
                ->where('content', 'like', '%' . $request->query . '%')
                ->where('status', '!=', 'deleted')
                ->with(['sender'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json($messages);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to search messages', [
                'thread_id' => $threadId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to search messages',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
