<?php

return [
    // Points per action
    'points' => [
        'event_registration' => 15,
        'forum_post_create' => 8,
        'forum_comment_create' => 2,
        'daily_login' => 1,
        'email_verified' => 10,
        'profile_completed' => 20,
        'event_attended' => 20,
        // Placeholders for future integrations
        'forum_post_liked' => 1,
        'forum_post_viewed' => 0, // usually award on milestones; keep 0 by default
    ],

    // Milestones for views of forum posts. When a post's view_count hits one of these,
    // the post author will receive an award for action 'forum_post_viewed'.
    'view_milestones' => [100, 500, 1000, 5000],

    // Whether an action should advance the user's streak
    'touch_streak' => [
        'event_registration' => true,
        'forum_post_create' => true,
        'forum_comment_create' => true,
        'daily_login' => true,
        'email_verified' => false,
        'profile_completed' => false,
        'event_attended' => true,
        'forum_post_liked' => false, // receiving a like does not advance streak by default
        'forum_post_viewed' => false,
    ],

    // De-duplication policies
    // - one_time: award only once ever per user
    // - once_per_day: at most once per calendar day
    // - allow_multiple: no special de-duplication (but can still be limited by custom logic)
    'dedup' => [
        'daily_login' => 'once_per_day',
        'email_verified' => 'one_time',
        'profile_completed' => 'one_time',
        // default for others is allow_multiple
    ],
];
