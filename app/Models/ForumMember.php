<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForumMember extends Model
{
    /**
     * The attributes that are mass-assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'forum_id',
        'user_id',
        'role',
        'status',
        'can_post',
        'can_comment',
        'can_moderate',
        'notifications_enabled',
        'notification_frequency',
        'post_count',
        'comment_count',
        'last_activity_at',
        'banned_until',
        'ban_reason',
        'banned_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'can_post' => 'boolean',
        'can_comment' => 'boolean',
        'can_moderate' => 'boolean',
        'notifications_enabled' => 'boolean',
        'last_activity_at' => 'datetime',
        'banned_until' => 'datetime',
    ];

    /**
     * Get the forum.
     */
    public function forum(): BelongsTo
    {
        return $this->belongsTo(Forum::class);
    }

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if member is moderator.
     */
    public function isModerator(): bool
    {
        return in_array($this->role, ['moderator', 'admin']);
    }

    /**
     * Check if member is banned.
     */
    public function isBanned(): bool
    {
        return $this->status === 'banned' ||
            ($this->banned_until && $this->banned_until->isFuture());
    }

    /**
     * Check if member is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->isBanned();
    }
}
