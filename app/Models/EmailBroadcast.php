<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;

class EmailBroadcast extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use  SoftDeletes;

    /**
     * The attributes that are mass-assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sender_id',
        'sender_type',
        'sender_entity_id',
        'subject',
        'message',
        'from_email',
        'from_name',
        'reply_to_email',
        'audience_type',
        'target_university_id',
        'target_event_id',
        'custom_user_ids',
        'filters',
        'scheduled_at',
        'is_scheduled',
        'status',
        'total_recipients',
        'sent_count',
        'failed_count',
        'opened_count',
        'clicked_count',
        'started_at',
        'completed_at',
        'error_message',
        'processing_stats',
        'attachments',
        'template',
        'track_opens',
        'track_clicks',
        'tracking_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'scheduled_at' => 'datetime',
        'is_scheduled' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'track_opens' => 'boolean',
        'track_clicks' => 'boolean',
        'custom_user_ids' => 'array',
        'filters' => 'array',
        'processing_stats' => 'array',
        'attachments' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($broadcast) {
            if (empty($broadcast->tracking_id)) {
                $broadcast->tracking_id = Str::uuid();
            }
        });
    }

    /**
     * Get the sender user.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the target university.
     */
    public function targetUniversity(): BelongsTo
    {
        return $this->belongsTo(University::class, 'target_university_id');
    }

    /**
     * Get the target event.
     */
    public function targetEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'target_event_id');
    }

    /**
     * Get the recipients.
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(EmailBroadcastRecipient::class);
    }

    /**
     * Get the logs.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(EmailBroadcastLog::class);
    }

    /**
     * Scope a query to only include draft broadcasts.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope a query to only include queued broadcasts.
     */
    public function scopeQueued($query)
    {
        return $query->where('status', 'queued');
    }

    /**
     * Scope a query to only include scheduled broadcasts.
     */
    public function scopeScheduled($query)
    {
        return $query->where('is_scheduled', true)
            ->where('status', 'draft')
            ->where('scheduled_at', '<=', now());
    }

    /**
     * Check if broadcast is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the broadcast failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get success rate percentage.
     */
    /**
     * Get success rate percentage.
     */
    public function getSuccessRate(): float
    {
        // Use > 0 to handle null, strings, or zero safely
        if (!$this->total_recipients || $this->total_recipients <= 0) {
            return 0;
        }

        return round(($this->sent_count / $this->total_recipients) * 100, 2);
    }

    /**
     * Get open rate percentage.
     */
    public function getOpenRate(): float
    {
        if (!$this->sent_count || $this->sent_count <= 0) {
            return 0;
        }

        return round(($this->opened_count / $this->sent_count) * 100, 2);
    }

    /**
     * Get click rate percentage.
     */
    public function getClickRate(): float
    {
        if (!$this->sent_count || $this->sent_count <= 0) {
            return 0;
        }

        return round(($this->clicked_count / $this->sent_count) * 100, 2);
    }

    /**
     * Log an event.
     */
    public function log(string $level, string $message, array $context = [], ?string $eventType = null): void
    {
        $this->logs()->create([
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'event_type' => $eventType,
        ]);
    }
}
