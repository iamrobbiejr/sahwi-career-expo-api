<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class ConferenceCall extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use  SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'event_id',
        'platform',
        'meeting_url',
        'meeting_id',
        'passcode',
        'dial_in_numbers',
        'host_name',
        'host_email',
        'host_id',
        'waiting_room_enabled',
        'recording_enabled',
        'recording_url',
        'auto_recording',
        'auto_recording_type',
        'require_registration',
        'mute_on_entry',
        'screen_sharing',
        'participant_video',
        'platform_meeting_data',
        'platform_meeting_uuid',
        'max_participants',
        'duration_minutes',
        'scheduled_start',
        'scheduled_end',
        'actual_start',
        'actual_end',
        'status',
        'cancellation_reason',
        'instructions',
        'send_reminders',
        'reminder_minutes_before',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'waiting_room_enabled' => 'boolean',
        'recording_enabled' => 'boolean',
        'auto_recording' => 'boolean',
        'require_registration' => 'boolean',
        'mute_on_entry' => 'boolean',
        'screen_sharing' => 'boolean',
        'send_reminders' => 'boolean',
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'actual_start' => 'datetime',
        'actual_end' => 'datetime',
        'dial_in_numbers' => 'array',
        'platform_meeting_data' => 'array',
    ];

    /**
     * Get the event that owns the conference call.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Scope a query to only include scheduled calls.
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Scope a query to only include live calls.
     */
    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }

    /**
     * Scope a query to only include upcoming calls.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_start', '>', now())
            ->where('status', 'scheduled');
    }

    /**
     * Check if the meeting is currently live.
     */
    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    /**
     * Check if the meeting has ended.
     */
    public function hasEnded(): bool
    {
        return $this->status === 'ended' ||
            ($this->actual_end && $this->actual_end->isPast());
    }

    /**
     * Get the meeting join URL with optional parameters.
     */
    public function getJoinUrl(?string $participantName = null): string
    {
        $url = $this->meeting_url;

        if ($participantName && $this->platform === 'zoom') {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'uname=' . urlencode($participantName);
        }

        return $url;
    }

    /**
     * Start the meeting.
     */
    public function start(): bool
    {
        $this->actual_start = now();
        $this->status = 'live';
        return $this->save();
    }

    /**
     * End the meeting.
     */
    public function end(): bool
    {
        $this->actual_end = now();
        $this->status = 'ended';
        return $this->save();
    }

    /**
     * Cancel the meeting.
     */
    public function cancel(string $reason = null): bool
    {
        $this->status = 'cancelled';
        $this->cancellation_reason = $reason;
        return $this->save();
    }

    /**
     * Get formatted meeting credentials.
     */
    public function getCredentials(): array
    {
        return [
            'meeting_url' => $this->meeting_url,
            'meeting_id' => $this->meeting_id,
            'passcode' => $this->passcode,
            'dial_in_numbers' => $this->dial_in_numbers,
            'platform' => ucfirst($this->platform),
        ];
    }
}
