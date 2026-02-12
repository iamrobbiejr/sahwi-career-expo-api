<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class PaymentItem extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = [
        'payment_id',
        'event_registration_id',
        'description',
        'amount_cents',
        'quantity',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    public function getAmountAttribute(): float
    {
        return $this->amount_cents / 100;
    }

    public function getTotalAmountCentsAttribute(): int
    {
        return $this->amount_cents * $this->quantity;
    }
}
