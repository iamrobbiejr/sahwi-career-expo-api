<?php

namespace App\Http\Controllers\Api\Forums;

use App\Http\Controllers\Controller;
use App\Models\Forum;
use App\Models\ForumMember;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ForumController extends Controller
{
    /**
     * Get all forums.
     */
    public function index(Request $request)
    {
        try {
            $query = Forum::with(['creator', 'subForums']);

            // Filter by public/private
            if ($request->has('public')) {
                $query->where('public', $request->boolean('public'));
            }

            // Only show active forums to non-admins
            if (!$request->user()->isAdmin()) {
                $query->active()->public();
            }

            // Search
            if ($request->has('search')) {
                $query->where(function ($q) use ($request) {
                    $q->where('title', 'like', '%' . $request->search . '%')
                        ->orWhere('description', 'like', '%' . $request->search . '%');
                });
            }

            // Parent forums only
            if ($request->boolean('parent_only')) {
                $query->whereNull('parent_forum_id');
            }

            $forums = $query->orderBy('display_order')
                ->orderBy('title')
                ->paginate(20);

            return response()->json($forums);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to fetch forums', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch forums',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new forum.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'public' => 'boolean',
                'parent_forum_id' => 'nullable|exists:forums,id',
                'moderation_policy' => 'nullable|array',
                'categories' => 'nullable|array',
                'icon' => 'nullable|string',
                'display_order' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            DB::beginTransaction();

            $forum = Forum::create([
                'title' => $request->title,
                'slug' => Str::slug($request->title),
                'description' => $request->description,
                'public' => $request->input('public', true),
                'parent_forum_id' => $request->parent_forum_id,
                'created_by' => Auth::id(),
                'moderation_policy' => $request->moderation_policy,
                'categories' => $request->categories,
                'icon' => $request->icon,
                'display_order' => $request->input('display_order', 0),
            ]);

            // Add creator as admin member
            ForumMember::create([
                'forum_id' => $forum->id,
                'user_id' => Auth::id(),
                'role' => 'admin',
                'can_post' => true,
                'can_comment' => true,
                'can_moderate' => true,
            ]);

            $forum->increment('member_count');

            DB::commit();

            return response()->json([
                'message' => 'Forum created successfully',
                'data' => $forum->load('creator'),
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();

           Log::channel('threads')->error('Failed to create forum', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => 'Failed to create forum',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get forum by ID or slug.
     */
    public function show($identifier)
    {
        try {
            $forum = Forum::where('id', $identifier)
                ->orWhere('slug', $identifier)
                ->with([
                    'creator',
                    'subForums',
                    'posts' => function ($q) {
                        $q->published()
                            ->orderBy('is_pinned', 'desc')
                            ->orderBy('last_activity_at', 'desc')
                            ->limit(10);
                    }
                ])
                ->firstOrFail();

            // Check access
            if (!$forum->public && !$forum->hasMember(Auth::id())) {
                return response()->json([
                    'error' => 'Access denied to private forum',
                ], 403);
            }

            return response()->json($forum);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to fetch forum', [
                'identifier' => $identifier,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch forum',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update forum.
     */
    public function update(Request $request, $id)
    {
        try {
            $forum = Forum::findOrFail($id);

            // Check permissions
            if (!$forum->isModerator(Auth::id())) {
                return response()->json([
                    'error' => 'Unauthorized to update forum',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'public' => 'boolean',
                'is_active' => 'boolean',
                'allow_posts' => 'boolean',
                'require_approval' => 'boolean',
                'moderation_policy' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $forum->update($request->all());

            return response()->json([
                'message' => 'Forum updated successfully',
                'data' => $forum,
            ]);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to update forum', [
                'forum_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update forum',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete forum.
     */
    public function destroy($id)
    {
        try {
            $forum = Forum::findOrFail($id);

            // Only creator can delete
            if ($forum->created_by != Auth::id()) {
                return response()->json([
                    'error' => 'Only forum creator can delete forum',
                ], 403);
            }

            $forum->delete();

            return response()->json([
                'message' => 'Forum deleted successfully',
            ]);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to delete forum', [
                'forum_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to delete forum',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Join forum.
     */
    public function join($id)
    {
        try {
            $forum = Forum::findOrFail($id);
            $userId = Auth::id();

            // Check if already a member
            if ($forum->hasMember($userId)) {
                return response()->json([
                    'error' => 'Already a member of this forum',
                ], 400);
            }

            // Check if the forum is public
            if (!$forum->public) {
                return response()->json([
                    'error' => 'Cannot join private forum',
                ], 403);
            }

            ForumMember::create([
                'forum_id' => $forum->id,
                'user_id' => $userId,
                'role' => 'member',
                'can_post' => true,
                'can_comment' => true,
            ]);

            $forum->increment('member_count');

            return response()->json([
                'message' => 'Joined forum successfully',
            ]);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to join forum', [
                'forum_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to join forum',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Leave forum.
     */
    public function leave($id)
    {
        try {
            $forum = Forum::findOrFail($id);
            $userId = Auth::id();

            $member = ForumMember::where('forum_id', $id)
                ->where('user_id', $userId)
                ->first();

            if (!$member) {
                return response()->json([
                    'error' => 'You are not a member of this forum',
                ], 400);
            }

            // Cannot leave if admin and only admin
            if ($member->role === 'admin') {
                $adminCount = ForumMember::where('forum_id', $id)
                    ->where('role', 'admin')
                    ->count();

                if ($adminCount <= 1) {
                    return response()->json([
                        'error' => 'Cannot leave as the only admin',
                    ], 400);
                }
            }

            $member->delete();
            $forum->decrement('member_count');

            return response()->json([
                'message' => 'Left forum successfully',
            ]);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to leave forum', [
                'forum_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to leave forum',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function removeMember($id, $userId)
    {
        try {
            // check if an auth user has an admin role
            if (!Auth::user()->hasRole('admin')) {
                throw new Exception('Unauthorized to remove member');
            }

            $forum = Forum::findOrFail($id);
            $member = User::findOrFail($userId);

            $hasMember = ForumMember::where('forum_id', $id)->where('user_id', $userId)->exists();

            Log::channel('threads')->info('Removing member from forum', [
                'forum_id' => $id,
                'user_id' => $userId,
                'has_member' => $hasMember
            ]);

            if (!$hasMember) {
                throw new Exception('User is not a member of the forum');
            }

            ForumMember::where('forum_id', $id)->where('user_id', $userId)->delete();
            $forum->decrement('member_count');


            Log::channel('threads')->info('Member removed from forum', [
                'forum_id' => $id,
                'user_id' => $userId,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Member removed successfully',
                'data' => $member
            ]);

        } catch (Exception $e) {
            Log::channel('threads')->error('Failed to remove member', [
                'forum_id' => $id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'status' => 'error',
                'error' => 'Unauthorized to remove member',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function checkMembership($forumId)
    {
        try {
            $forum = Forum::findOrFail($forumId);

            if (!$forum->hasMember(Auth::id())) {
                throw new Exception('User is not a member of the forum');
            }

            return response()->json([
                'is_member' => true,
                'message' => 'User is a member of the forum',
                'data' => $forum->members()->where('user_id', Auth::id())->firstOrFail()
            ]);

        } catch (Exception $e) {
            Log::channel('threads')->error('Failed to fetch membership', [
                'forum_id' => $forumId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'is_member' => false,
                'error' => 'Failed to fetch membership',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get forum members.
     */
    public function members(Request $request, $id)
    {
        try {
            $forum = Forum::findOrFail($id);

            // Check access
            if (!$forum->public && !$forum->hasMember(Auth::id())) {
                return response()->json([
                    'error' => 'Access denied',
                ], 403);
            }

            $members = ForumMember::where('forum_id', $id)
                ->with('user')
                ->orderBy('role')
                ->orderBy('post_count', 'desc')
                ->paginate(20);

            return response()->json($members);

        } catch (Exception $e) {
           Log::channel('threads')->error('Failed to fetch forum members', [
                'forum_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch members',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
