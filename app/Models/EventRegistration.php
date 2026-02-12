<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;

class EventRegistration extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = [
        'event_id',
        'user_id',
        'registered_by',
        'group_registration_id',
        'registration_type',
        'attendee_type',
        'status',
        'attendee_name',
        'attendee_email',
        'attendee_phone',
        'attendee_title',
        'attendee_organization_id',
        'special_requirements',
        'custom_fields',
        'ticket_number',
        'ticket_generated_at',
        'checked_in_at',
        'registered_at',
        'cancelled_at',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'ticket_generated_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'registered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function groupRegistration(): BelongsTo
    {
        return $this->belongsTo(GroupRegistration::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'attendee_organization_id');
    }

    public function paymentItem(): HasOne
    {
        return $this->hasOne(PaymentItem::class);
    }

    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class, 'event_registration_id', 'id');
    }

    public function isPaid(): bool
    {
        return $this->paymentItem?->payment?->status === 'completed';
    }

    public function canGenerateTicket(): bool
    {
        return $this->status === 'confirmed' &&
            $this->isPaid() &&
            !$this->ticket;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($registration) {
            $registration->ticket_number = self::generateTicketNumber();
        });
    }

    public static function generateTicketNumber(): string
    {
        do {
            $ticketNumber = 'TKT-' . strtoupper(uniqid());
        } while (self::where('ticket_number', $ticketNumber)->exists());

        return $ticketNumber;
    }
}
