<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailBroadcastRecipient extends Model
{
    /**
     * The attributes that are mass-assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email_broadcast_id',
        'user_id',
        'email',
        'status',
        'sent_at',
        'error_message',
        'retry_count',
        'opened',
        'opened_at',
        'open_count',
        'clicked',
        'clicked_at',
        'click_count',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sent_at' => 'datetime',
        'opened' => 'boolean',
        'opened_at' => 'datetime',
        'clicked' => 'boolean',
        'clicked_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the email broadcast.
     */
    public function emailBroadcast(): BelongsTo
    {
        return $this->belongsTo(EmailBroadcast::class);
    }

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Mark as opened.
     */
    public function markAsOpened(): void
    {
        $this->update([
            'opened' => true,
            'opened_at' => $this->opened_at ?? now(),
            'open_count' => $this->open_count + 1,
        ]);
    }

    /**
     * Mark as clicked.
     */
    public function markAsClicked(): void
    {
        $this->update([
            'clicked' => true,
            'clicked_at' => $this->clicked_at ?? now(),
            'click_count' => $this->click_count + 1,
        ]);
    }
}
