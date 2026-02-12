<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Payment extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = [
        'event_id',
        'user_id',
        'payment_gateway_id',
        'payment_reference',
        'gateway_transaction_id',
        'gateway_name',
        'amount_cents',
        'currency',
        'gateway_fee_cents',
        'platform_fee_cents',
        'status',
        'payment_method',
        'payment_phone',
        'gateway_response',
        'failure_reason',
        'paid_at',
        'failed_at',
        'refunded_at',
        'notes',
        'receipt_url',
    ];

    protected $casts = [
        'gateway_response' => 'array',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id', 'id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PaymentItem::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function getAmountAttribute(): float
    {
        return $this->amount_cents / 100;
    }

    public function getTotalFeesCentsAttribute(): int
    {
        return $this->gateway_fee_cents + $this->platform_fee_cents;
    }

    public function getNetAmountCentsAttribute(): int
    {
        return $this->amount_cents - $this->total_fees_cents;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($payment) {
            $payment->payment_reference = self::generatePaymentReference();
        });
    }

    public static function generatePaymentReference(): string
    {
        do {
            $reference = 'PAY-' . strtoupper(uniqid());
        } while (self::where('payment_reference', $reference)->exists());

        return $reference;
    }
}
