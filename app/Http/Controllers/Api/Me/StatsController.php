<?php

namespace App\Http\Controllers\Api\Me;

use App\Http\Controllers\Controller;
use App\Models\EventRegistration;
use App\Models\ForumPost;
use App\Models\ThreadMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class StatsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $user = Auth::user();

        $eventsRegistered = EventRegistration::where('user_id', '=', $user->id)
            ->where('status', 'confirmed')
            ->count();

        $forumPosts = ForumPost::where('author_id', $user->id)
            ->whereNull('deleted_at')
            ->count();

        $unreadMessages = ThreadMember::where('user_id', $user->id)->sum('unread_count');

        return response()->json([
            'events_registered_count' => $eventsRegistered,
            'forum_posts_count' => $forumPosts,
            'unread_messages_count' => $unreadMessages,
            'reputation_points' => (int)($user->reputation_points ?? 0),
            'streak_days' => (int)($user->streak_days ?? 0),
        ]);
    }
}
