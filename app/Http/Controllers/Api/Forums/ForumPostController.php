<?php

namespace App\Http\Controllers\Api\Forums;

use App\Http\Controllers\Controller;
use App\Models\Forum;
use App\Models\ForumMember;
use App\Models\ForumPost;
use App\Services\RewardService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class ForumPostController extends Controller
{
    /**
     * Get all posts for a forum.
     */
    public function index(Request $request, $forumId)
    {
        try {
            $forum = Forum::findOrFail($forumId);

            // Check access
            if (!$forum->public && !$forum->hasMember(Auth::id())) {
                return response()->json([
                    'error' => 'Access denied',
                ], 403);
            }

            $query = ForumPost::where('forum_id', $forumId)
                ->with(['author', 'forum']);

            // Only show published posts to non-moderators
            $member = ForumMember::where('forum_id', $forumId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$member || !$member->can_moderate) {
                $query->published();
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by category
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            // Search
            if ($request->has('search')) {
                $query->where(function ($q) use ($request) {
                    $q->where('title', 'like', '%' . $request->search . '%')
                        ->orWhere('body', 'like', '%' . $request->search . '%');
                });
            }

            // Sort
            $sortBy = $request->input('sort', 'latest');
            switch ($sortBy) {
                case 'popular':
                    $query->orderBy('view_count', 'desc');
                    break;
                case 'discussed':
                    $query->orderBy('comment_count', 'desc');
                    break;
                case 'oldest':
                    $query->orderBy('created_at', 'asc');
                    break;
                default:
                    $query->orderBy('is_pinned', 'desc')
                        ->orderBy('last_activity_at', 'desc');
            }

            $posts = $query->paginate(20);

            return response()->json($posts);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to fetch forum posts', [
                'forum_id' => $forumId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch posts',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get hottest posts (global).
     */
    public function hottest()
    {
        try {
            // Get the top 3 posts by engagement (views + comments + likes)
            $posts = ForumPost::with(['author', 'forum'])
                ->published()
                ->orderByRaw('(view_count + comment_count + like_count) DESC')
                ->take(3)
                ->get();

            return response()->json($posts);

        } catch (Exception $e) {
            Log::channel('threads')->error('Failed to fetch hottest posts', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch hottest posts',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new post.
     */
    public function store(Request $request, $forumId)
    {
        try {
            $forum = Forum::findOrFail($forumId);
            $userId = Auth::id();

            // Check permissions
            $member = ForumMember::where('forum_id', $forumId)
                ->where('user_id', $userId)
                ->first();

            if (!$member || !$member->can_post || $member->isBanned()) {
                return response()->json([
                    'error' => 'You cannot post in this forum',
                ], 403);
            }

            if (!$forum->allow_posts) {
                return response()->json([
                    'error' => 'Forum is not accepting new posts',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'body' => 'required|string',
                'category' => 'nullable|string',
                'tags' => 'nullable|array',
                'attachments' => 'nullable|array',
                'attachments.*' => 'file|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            DB::beginTransaction();

            // Handle attachments
            $attachmentPaths = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('forum-attachments', 'public');
                    $attachmentPaths[] = [
                        'path' => $path,
                        'name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                    ];
                }
            }

            // Determine initial status
            $status = $forum->require_approval ? 'pending' : 'published';

            $post = ForumPost::create([
                'forum_id' => $forumId,
                'author_id' => $userId,
                'title' => $request->title,
                'slug' => Str::slug($request->title),
                'body' => $request->body,
                'status' => $status,
                'category' => $request->category,
                'tags' => $request->tags,
                'attachments' => $attachmentPaths,
            ]);

            if ($status === 'published') {
                $forum->increment('post_count');
                $forum->update(['last_post_at' => now()]);
                $member->increment('post_count');
            }

            DB::commit();

            // Reward: configured points for creating a forum post
            try {
                app(RewardService::class)->awardFor(Auth::user(), 'forum_post_create', [
                    'forum_id' => $forumId,
                    'post_id' => $post->id,
                ]);
            } catch (Throwable $e) {
                // Do not fail request due to rewards
            }

            return response()->json([
                'message' => 'Post created successfully',
                'data' => $post->load(['author', 'forum']),
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();

           Log::channel('threads')->error('Failed to create forum post', [
                'forum_id' => $forumId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to create post',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get post by ID or slug.
     */
    public function show($forumId, $identifier)
    {
        try {
            $forum = Forum::findOrFail($forumId);

            // Check access
            if (!$forum->public && !$forum->hasMember(Auth::id())) {
                return response()->json([
                    'error' => 'Access denied',
                ], 403);
            }

            $post = ForumPost::where('forum_id', $forumId)
                ->where(function ($q) use ($identifier) {
                    $q->where('id', $identifier)
                        ->orWhere('slug', $identifier);
                })
                ->with([
                    'author',
                    'forum',
                    'comments' => function ($q) {
                        $q->published()
                            ->whereNull('parent_comment_id')
                            ->orderBy('created_at', 'desc');
                    }
                ])
                ->firstOrFail();


            // Increment view count
            $post->incrementViews();

            return response()->json($post);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to fetch forum post', [
                'identifier' => $identifier,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch post',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update post.
     */
    public function update(Request $request, $forumId, $postId)
    {
        try {
            $post = ForumPost::where('forum_id', $forumId)
                ->where('id', $postId)
                ->firstOrFail();

            $userId = Auth::id();

            // Check permissions
            $canEdit = $post->author_id == $userId;

            if (!$canEdit) {
                $member = ForumMember::where('forum_id', $forumId)
                    ->where('user_id', $userId)
                    ->first();
                $canEdit = $member && $member->can_moderate;
            }

            if (!$canEdit) {
                return response()->json([
                    'error' => 'Unauthorized to edit this post',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'body' => 'sometimes|string',
                'category' => 'nullable|string',
                'tags' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $post->update(array_merge($request->all(), [
                'edited_at' => now(),
                'edited_by' => $userId,
            ]));

            return response()->json([
                'message' => 'Post updated successfully',
                'data' => $post->load(['author', 'forum']),
            ]);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to update forum post', [
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update post',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete post.
     */
    public function destroy($forumId, $postId)
    {
        try {
            $post = ForumPost::where('forum_id', $forumId)
                ->where('id', $postId)
                ->firstOrFail();

            $userId = Auth::id();

            // Check permissions
            $canDelete = $post->author_id == $userId;

            if (!$canDelete) {
                $member = ForumMember::where('forum_id', $forumId)
                    ->where('user_id', $userId)
                    ->first();
                $canDelete = $member && $member->can_moderate;
            }

            if (!$canDelete) {
                return response()->json([
                    'error' => 'Unauthorized to delete this post',
                ], 403);
            }

            // Delete attachments
            if (!empty($post->attachments)) {
                foreach ($post->attachments as $attachment) {
                    Storage::disk('public')->delete($attachment['path']);
                }
            }

            $post->delete();

            $forum = Forum::find($forumId);
            if ($forum && $post->status === 'published') {
                $forum->decrement('post_count');
            }

            return response()->json([
                'message' => 'Post deleted successfully',
            ]);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to delete forum post', [
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to delete post',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pin/unpin post.
     */
    public function togglePin($forumId, $postId)
    {
        try {
            $post = ForumPost::where('forum_id', $forumId)
                ->where('id', $postId)
                ->firstOrFail();

            // Check moderator permissions
            $member = ForumMember::where('forum_id', $forumId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$member || !$member->can_moderate) {
                return response()->json([
                    'error' => 'Unauthorized to pin posts',
                ], 403);
            }

            $post->update(['is_pinned' => !$post->is_pinned]);

            return response()->json([
                'message' => $post->is_pinned ? 'Post pinned' : 'Post unpinned',
                'is_pinned' => $post->is_pinned,
            ]);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to toggle pin', [
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to toggle pin',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lock/unlock post.
     */
    public function toggleLock($forumId, $postId)
    {
        try {
            $post = ForumPost::where('forum_id', $forumId)
                ->where('id', $postId)
                ->firstOrFail();

            // Check moderator permissions
            $member = ForumMember::where('forum_id', $forumId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$member || !$member->can_moderate) {
                return response()->json([
                    'error' => 'Unauthorized to lock posts',
                ], 403);
            }

            $post->update(['is_locked' => !$post->is_locked]);

            return response()->json([
                'message' => $post->is_locked ? 'Post locked' : 'Post unlocked',
                'is_locked' => $post->is_locked,
            ]);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to toggle lock', [
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to toggle lock',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve post.
     */
    public function approve($forumId, $postId)
    {
        try {
            $post = ForumPost::where('forum_id', $forumId)
                ->where('id', $postId)
                ->where('status', 'pending')
                ->firstOrFail();

            // Check moderator permissions
            $member = ForumMember::where('forum_id', $forumId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$member || !$member->can_moderate) {
                return response()->json([
                    'error' => 'Unauthorized to approve posts',
                ], 403);
            }

            DB::beginTransaction();

            $post->update([
                'status' => 'published',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            $forum = Forum::find($forumId);
            $forum->increment('post_count');
            $forum->update(['last_post_at' => now()]);

            $authorMember = ForumMember::where('forum_id', $forumId)
                ->where('user_id', $post->author_id)
                ->first();

            if ($authorMember) {
                $authorMember->increment('post_count');
            }

            DB::commit();

            return response()->json([
                'message' => 'Post approved successfully',
                'data' => $post,
            ]);

        } catch (Exception $e) {
            DB::rollBack();

           Log::channel('threads')->error('Failed to approve post', [
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to approve post',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject post.
     */
    public function reject(Request $request, $forumId, $postId)
    {
        try {
            $post = ForumPost::where('forum_id', $forumId)
                ->where('id', $postId)
                ->where('status', 'pending')
                ->firstOrFail();

            // Check moderator permissions
            $member = ForumMember::where('forum_id', $forumId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$member || !$member->can_moderate) {
                return response()->json([
                    'error' => 'Unauthorized to reject posts',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'reason' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $post->update([
                'status' => 'rejected',
                'rejection_reason' => $request->reason,
            ]);

            return response()->json([
                'message' => 'Post rejected',
            ]);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to reject post', [
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to reject post',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
