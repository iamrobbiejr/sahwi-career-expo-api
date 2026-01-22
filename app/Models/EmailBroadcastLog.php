<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailBroadcastLog extends Model
{
    /**
     * The attributes that are mass-assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email_broadcast_id',
        'level',
        'message',
        'context',
        'event_type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'context' => 'array',
    ];

    /**
     * Get the email broadcast.
     */
    public function emailBroadcast(): BelongsTo
    {
        return $this->belongsTo(EmailBroadcast::class);
    }
}
