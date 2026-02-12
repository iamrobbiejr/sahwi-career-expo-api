<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Refund extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['payment_id', 'processed_by', 'refund_reference',
        'gateway_refund_id', 'amount_cents', 'currency', 'status', 'reason',
        'admin_notes', 'processed_at'];

    protected $casts = [
        'gateway_response' => 'array',
        'processed_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
