<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreadMember extends Model
{
    /**
     * The attributes that are mass-assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'thread_id',
        'user_id',
        'role',
        'status',
        'last_read_at',
        'unread_count',
        'notifications_enabled',
        'muted',
        'can_send_messages',
        'can_add_members',
        'can_remove_members',
        'joined_at',
        'left_at',
        'settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_read_at' => 'datetime',
        'notifications_enabled' => 'boolean',
        'muted' => 'boolean',
        'can_send_messages' => 'boolean',
        'can_add_members' => 'boolean',
        'can_remove_members' => 'boolean',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'settings' => 'array',
    ];

    /**
     * Get the thread.
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if member is owner.
     */
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /**
     * Check if member is moderator or owner.
     */
    public function canModerate(): bool
    {
        return in_array($this->role, ['moderator', 'owner']);
    }

    /**
     * Check if member is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if member is muted.
     */
    public function isMuted(): bool
    {
        return $this->muted || $this->status === 'muted';
    }
}
