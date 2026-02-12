<?php

namespace App\Models;

use Database\Factories\EventActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class EventActivity extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    /** @use HasFactory<EventActivityFactory> */
    use HasFactory;

    protected $fillable = ['event_id', 'type', 'title', 'description'];
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
