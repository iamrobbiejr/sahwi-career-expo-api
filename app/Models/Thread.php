<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Thread extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use  SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'thread_type',
        'created_by',
        'meta',
        'is_active',
        'is_archived',
        'allow_attachments',
        'max_members',
        'message_count',
        'member_count',
        'last_message_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'meta' => 'array',
        'is_active' => 'boolean',
        'is_archived' => 'boolean',
        'allow_attachments' => 'boolean',
        'last_message_at' => 'datetime',
    ];

    /**
     * Get the creator of the thread.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the messages in the thread.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the members of the thread.
     */
    public function members(): HasMany
    {
        return $this->hasMany(ThreadMember::class);
    }

    /**
     * Get active members only.
     */
    public function activeMembers(): HasMany
    {
        return $this->members()->where('status', 'active');
    }

    /**
     * Get users in this thread.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'thread_members')
            ->withPivot(['role', 'status', 'last_read_at', 'unread_count'])
            ->withTimestamps();
    }

    /**
     * Scope a query to only include active threads.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include direct messages.
     */
    public function scopeDirect($query)
    {
        return $query->where('thread_type', 'direct');
    }

    /**
     * Scope a query to only include group chats.
     */
    public function scopeGroup($query)
    {
        return $query->where('thread_type', 'group');
    }

    /**
     * Check if thread is direct message.
     */
    public function isDirect(): bool
    {
        return $this->thread_type === 'direct';
    }

    /**
     * Check if thread is group chat.
     */
    public function isGroup(): bool
    {
        return $this->thread_type === 'group';
    }

    /**
     * Check if user is member of thread.
     */
    public function hasMember(int $userId): bool
    {
        return $this->members()->where('user_id', $userId)->exists();
    }

    /**
     * Get unread message count for user.
     */
    public function getUnreadCount(int $userId): int
    {
        $member = $this->members()->where('user_id', $userId)->first();
        return $member ? $member->unread_count : 0;
    }

    /**
     * Mark thread as read for user.
     */
    public function markAsRead(int $userId): void
    {
        $this->members()->where('user_id', $userId)->update([
            'last_read_at' => now(),
            'unread_count' => 0,
        ]);
    }
}
