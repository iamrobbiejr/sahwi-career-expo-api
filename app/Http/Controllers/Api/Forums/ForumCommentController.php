<?php

namespace App\Http\Controllers\Api\Forums;

use App\Http\Controllers\Controller;
use App\Models\Forum;
use App\Models\ForumComment;
use App\Models\ForumMember;
use App\Models\ForumPost;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ForumCommentController extends Controller
{
    /**
     * Get all comments for a post.
     */
    public function index(Request $request, $forumId, $postId)
    {
        try {
            $forum = Forum::findOrFail($forumId);
            $post = ForumPost::where('forum_id', $forumId)
                ->where('id', $postId)
                ->firstOrFail();

            // Check access
            if (!$forum->public && !$forum->hasMember(Auth::id())) {
                return response()->json([
                    'error' => 'Access denied',
                ], 403);
            }

            $query = ForumComment::where('forum_post_id', $postId)
                ->with(['author', 'replies.author']);

            // Only show published comments to non-moderators
            $member = ForumMember::where('forum_id', $forumId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$member || !$member->can_moderate) {
                $query->published();
            }

            // Get root comments only (threaded structure)
            $comments = $query->whereNull('parent_comment_id')
                ->orderBy('created_at', $request->input('sort', 'asc'))
                ->paginate(50);

            return response()->json($comments);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to fetch comments', [
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch comments',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new comment.
     */
    public function store(Request $request, $forumId, $postId)
    {
        try {
            $forum = Forum::findOrFail($forumId);
            $post = ForumPost::where('forum_id', $forumId)
                ->where('id', $postId)
                ->firstOrFail();

            $userId = Auth::id();

            // Check if post is locked
            if ($post->is_locked) {
                return response()->json([
                    'error' => 'Post is locked for comments',
                ], 403);
            }

            // Check if comments are allowed
            if (!$post->allow_comments) {
                return response()->json([
                    'error' => 'Comments are disabled for this post',
                ], 403);
            }

            // Check member permissions
            $member = ForumMember::where('forum_id', $forumId)
                ->where('user_id', $userId)
                ->first();

            if (!$member || !$member->can_comment || $member->isBanned()) {
                return response()->json([
                    'error' => 'You cannot comment in this forum',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'content_message' => 'required|string',
                'parent_comment_id' => 'nullable|exists:forum_comments,id',
                'attachments' => 'nullable|array',
                'attachments.*' => 'file|max:5120', // 5MB
                'mentions' => 'nullable|array',
                'mentions.*' => 'exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Verify parent comment belongs to same post
            if ($request->parent_comment_id) {
                $parentComment = ForumComment::find($request->parent_comment_id);
                if ($parentComment->forum_post_id != $postId) {
                    return response()->json([
                        'error' => 'Invalid parent comment',
                    ], 400);
                }
            }

            DB::beginTransaction();

            // Handle attachments
            $attachmentPaths = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('forum-comment-attachments', 'public');
                    $attachmentPaths[] = [
                        'path' => $path,
                        'name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                    ];
                }
            }

            // Determine initial status
            $status = $forum->require_approval ? 'pending' : 'published';

            $comment = ForumComment::create([
                'forum_post_id' => $postId,
                'author_id' => $userId,
                'content' => $request->content_message,
                'parent_comment_id' => $request->parent_comment_id,
                'status' => $status,
                'attachments' => $attachmentPaths,
                'mentions' => $request->mentions,
            ]);

            if ($status === 'published') {
                $post->increment('comment_count');
                $post->update(['last_activity_at' => now()]);
                $forum->increment('comment_count');
                $member->increment('comment_count');

                // Increment parent reply count
                if ($request->parent_comment_id) {
                    ForumComment::where('id', $request->parent_comment_id)
                        ->increment('reply_count');
                }
            }

            DB::commit();

            // Reward: configured points for creating a published forum comment
            if ($status === 'published') {
                try {
                    app(\App\Services\RewardService::class)->awardFor(Auth::user(), 'forum_comment_create', [
                        'forum_id' => $forumId,
                        'post_id' => $postId,
                        'comment_id' => $comment->id,
                    ]);
                } catch (\Throwable $e) {
                    // Do not fail request due to rewards
                }
            }

            return response()->json([
                'message' => 'Comment added successfully',
                'data' => $comment->load(['author', 'parent']),
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();

           Log::channel('threads')->error('Failed to create comment', [
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to create comment',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific comment with its replies.
     */
    public function show($forumId, $postId, $commentId)
    {
        try {
            $forum = Forum::findOrFail($forumId);

            // Check access
            if (!$forum->public && !$forum->hasMember(Auth::id())) {
                return response()->json([
                    'error' => 'Access denied',
                ], 403);
            }

            $comment = ForumComment::where('forum_post_id', $postId)
                ->where('id', $commentId)
                ->with(['author', 'replies.author', 'parent'])
                ->firstOrFail();

            return response()->json($comment);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to fetch comment', [
                'comment_id' => $commentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch comment',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update comment.
     */
    public function update(Request $request, $forumId, $postId, $commentId)
    {
        try {
            $comment = ForumComment::where('forum_post_id', $postId)
                ->where('id', $commentId)
                ->firstOrFail();

            $userId = Auth::id();

            // Check permissions
            $canEdit = $comment->author_id == $userId;

            if (!$canEdit) {
                $member = ForumMember::where('forum_id', $forumId)
                    ->where('user_id', $userId)
                    ->first();
                $canEdit = $member && $member->can_moderate;
            }

            if (!$canEdit) {
                return response()->json([
                    'error' => 'Unauthorized to edit this comment',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'content_message' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Store original content if first edit
            if ($comment->status !== 'edited') {
                $comment->original_content = $comment->content;
            }

            $comment->update([
                'content' => $request->content_message,
                'status' => 'published', // Keep as published even after edit
                'edited_at' => now(),
                'edited_by' => $userId,
            ]);

            return response()->json([
                'message' => 'Comment updated successfully',
                'data' => $comment->load(['author', 'parent']),
            ]);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to update comment', [
                'comment_id' => $commentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update comment',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete comment.
     */
    public function destroy($forumId, $postId, $commentId)
    {
        try {
            $comment = ForumComment::where('forum_post_id', $postId)
                ->where('id', $commentId)
                ->firstOrFail();

            $userId = Auth::id();

            // Check permissions
            $canDelete = $comment->author_id == $userId;

            if (!$canDelete) {
                $member = ForumMember::where('forum_id', $forumId)
                    ->where('user_id', $userId)
                    ->first();
                $canDelete = $member && $member->can_moderate;
            }

            if (!$canDelete) {
                return response()->json([
                    'error' => 'Unauthorized to delete this comment',
                ], 403);
            }

            DB::beginTransaction();

            // Delete attachments
            if (!empty($comment->attachments)) {
                foreach ($comment->attachments as $attachment) {
                    Storage::disk('public')->delete($attachment['path']);
                }
            }

            // If comment has replies, soft delete (mark as deleted but keep structure)
            if ($comment->reply_count > 0) {
                $comment->update([
                    'content' => '[Comment deleted]',
                    'status' => 'deleted',
                    'attachments' => null,
                ]);
            } else {
                // Hard delete if no replies
                $comment->delete();
            }

            $post = ForumPost::find($postId);
            if ($post && $comment->status === 'published') {
                $post->decrement('comment_count');
            }

            $forum = Forum::find($forumId);
            if ($forum && $comment->status === 'published') {
                $forum->decrement('comment_count');
            }

            // Decrement parent reply count
            if ($comment->parent_comment_id) {
                ForumComment::where('id', $comment->parent_comment_id)
                    ->decrement('reply_count');
            }

            DB::commit();

            return response()->json([
                'message' => 'Comment deleted successfully',
            ]);

        } catch (Exception $e) {
            DB::rollBack();

           Log::channel('threads')->error('Failed to delete comment', [
                'comment_id' => $commentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to delete comment',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Like/unlike comment.
     */
    public function toggleLike($forumId, $postId, $commentId)
    {
        try {
            $comment = ForumComment::where('forum_post_id', $postId)
                ->where('id', $commentId)
                ->firstOrFail();

            $userId = Auth::id();

            // Check if user is forum member
            $forum = Forum::findOrFail($forumId);
            if (!$forum->public && !$forum->hasMember($userId)) {
                return response()->json([
                    'error' => 'Access denied',
                ], 403);
            }

            // Toggle like (simple implementation - can be enhanced with a likes table)
            // For now, we'll use a simple counter
            // In production, you'd want a separate 'comment_likes' table

            $comment->increment('like_count');

            // Reward the comment author for receiving a like (avoid self-like awards)
            try {
                $author = $comment->author; // lazy load relation
                if ($author && $author->id !== $userId) {
                    app(\App\Services\RewardService::class)->awardFor($author, 'forum_post_liked', [
                        'comment_id' => $comment->id,
                        'post_id' => $postId,
                        'liked_by' => $userId,
                    ]);
                }
            } catch (\Throwable $e) {
                // Non-critical
            }

            return response()->json([
                'message' => 'Comment liked',
                'like_count' => $comment->like_count,
            ]);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to toggle like', [
                'comment_id' => $commentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to toggle like',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve comment.
     */
    public function approve($forumId, $postId, $commentId)
    {
        try {
            $comment = ForumComment::where('forum_post_id', $postId)
                ->where('id', $commentId)
                ->where('status', 'pending')
                ->firstOrFail();

            // Check moderator permissions
            $member = ForumMember::where('forum_id', $forumId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$member || !$member->can_moderate) {
                return response()->json([
                    'error' => 'Unauthorized to approve comments',
                ], 403);
            }

            DB::beginTransaction();

            $comment->update([
                'status' => 'published',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            $post = ForumPost::find($postId);
            $post->increment('comment_count');
            $post->update(['last_activity_at' => now()]);

            $forum = Forum::find($forumId);
            $forum->increment('comment_count');

            $authorMember = ForumMember::where('forum_id', $forumId)
                ->where('user_id', $comment->author_id)
                ->first();

            if ($authorMember) {
                $authorMember->increment('comment_count');
            }

            // Increment parent reply count
            if ($comment->parent_comment_id) {
                ForumComment::where('id', $comment->parent_comment_id)
                    ->increment('reply_count');
            }

            DB::commit();

            return response()->json([
                'message' => 'Comment approved successfully',
                'data' => $comment,
            ]);

        } catch (Exception $e) {
            DB::rollBack();

           Log::channel('threads')->error('Failed to approve comment', [
                'comment_id' => $commentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to approve comment',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject comment.
     */
    public function reject(Request $request, $forumId, $postId, $commentId)
    {
        try {
            $comment = ForumComment::where('forum_post_id', $postId)
                ->where('id', $commentId)
                ->where('status', 'pending')
                ->firstOrFail();

            // Check moderator permissions
            $member = ForumMember::where('forum_id', $forumId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$member || !$member->can_moderate) {
                return response()->json([
                    'error' => 'Unauthorized to reject comments',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'reason' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $comment->update([
                'status' => 'rejected',
                'rejection_reason' => $request->reason,
            ]);

            return response()->json([
                'message' => 'Comment rejected',
            ]);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to reject comment', [
                'comment_id' => $commentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to reject comment',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get comment replies.
     */
    public function replies(Request $request, $forumId, $postId, $commentId)
    {
        try {
            $forum = Forum::findOrFail($forumId);

            // Check access
            if (!$forum->public && !$forum->hasMember(Auth::id())) {
                return response()->json([
                    'error' => 'Access denied',
                ], 403);
            }

            $comment = ForumComment::where('forum_post_id', $postId)
                ->where('id', $commentId)
                ->firstOrFail();

            $replies = ForumComment::where('parent_comment_id', $commentId)
                ->published()
                ->with(['author', 'replies.author'])
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json($replies);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to fetch comment replies', [
                'comment_id' => $commentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch replies',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
