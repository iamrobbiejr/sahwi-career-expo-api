<?php

namespace App\Models;

use Database\Factories\EventPanelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPanel extends Model
{
    /** @use HasFactory<EventPanelFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id', 'user_id', 'external_full_name',
        'external_contact', 'organization', 'panel_role', 'display_order'
    ];
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
