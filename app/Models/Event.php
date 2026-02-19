<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;

class Event extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected $fillable = [
        'img', 'name', 'description', 'venue', 'status',
        'registrations', 'capacity', 'registration_deadline',
        'created_by', 'location', 'start_date', 'end_date',
        'is_paid', 'price_cents', 'currency'
    ];
    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'registration_deadline' => 'date',
        'is_paid' => 'boolean',
        'registrations' => 'integer',
        'capacity' => 'integer',
        'price_cents' => 'integer',
    ];
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function panels(): HasMany
    {
        return $this->hasMany(EventPanel::class);
    }
    public function activities(): HasMany
    {
        return $this->hasMany(EventActivity::class);
    }

    /**
     * Get the conference call for virtual events.
     */
    public function conferenceCall(): HasOne
    {
        return $this->hasOne(ConferenceCall::class);
    }

    /**
     * Check if the event is virtual.
     */
    public function isVirtual(): bool
    {
        return $this->location === 'Virtual';
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'event_id', 'id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'event_id', 'id');
    }
}
