<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass-assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'thread_id',
        'sender_id',
        'content',
        'attachments',
        'reply_to_message_id',
        'status',
        'message_type',
        'mentions',
        'reactions',
        'edited_at',
        'original_content',
        'read_by',
        'read_count',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'attachments' => 'array',
        'mentions' => 'array',
        'reactions' => 'array',
        'read_by' => 'array',
        'metadata' => 'array',
        'edited_at' => 'datetime',
    ];

    /**
     * Get the thread this message belongs to.
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    /**
     * Get the sender of the message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the message this is replying to.
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_message_id');
    }

    /**
     * Check if message is deleted.
     */
    public function isDeleted(): bool
    {
        return $this->status === 'deleted';
    }

    /**
     * Check if message is edited.
     */
    public function isEdited(): bool
    {
        return $this->status === 'edited';
    }

    /**
     * Mark as read by user.
     */
    public function markAsReadBy(int $userId): void
    {
        $readBy = $this->read_by ?? [];

        if (!in_array($userId, $readBy)) {
            $readBy[] = $userId;
            $this->update([
                'read_by' => $readBy,
                'read_count' => count($readBy),
            ]);
        }
    }

    /**
     * Add reaction to message.
     */
    public function addReaction(int $userId, string $emoji): void
    {
        $reactions = $this->reactions ?? [];

        if (!isset($reactions[$emoji])) {
            $reactions[$emoji] = [];
        }

        if (!in_array($userId, $reactions[$emoji])) {
            $reactions[$emoji][] = $userId;
            $this->update(['reactions' => $reactions]);
        }
    }

    /**
     * Remove reaction from message.
     */
    public function removeReaction(int $userId, string $emoji): void
    {
        $reactions = $this->reactions ?? [];

        if (isset($reactions[$emoji])) {
            $reactions[$emoji] = array_diff($reactions[$emoji], [$userId]);

            if (empty($reactions[$emoji])) {
                unset($reactions[$emoji]);
            }

            $this->update(['reactions' => $reactions]);
        }
    }
}
