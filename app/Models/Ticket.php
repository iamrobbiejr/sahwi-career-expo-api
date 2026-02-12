<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Ticket extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = [
        'event_registration_id',
        'payment_id',
        'ticket_number',
        'qr_code_path',
        'pdf_path',
        'status',
        'used_at',
        'used_by',
        'emailed_at',
        'email_attempts',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'emailed_at' => 'datetime',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id', 'id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public function isValid(): bool
    {
        return $this->status === 'active' &&
            $this->registration->status === 'confirmed';
    }
}
