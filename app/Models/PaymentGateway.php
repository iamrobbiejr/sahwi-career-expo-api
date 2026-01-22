<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentGateway extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'display_order',
        'credentials',
        'settings',
        'supports_webhooks',
        'webhook_url',
        'webhook_secret',
        'supported_currencies',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'supports_webhooks' => 'boolean',
        'credentials' => 'encrypted:array',
        'settings' => 'array',
        'supported_currencies' => 'array',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function supportsCurrency(string $currency): bool
    {
        if (!$this->supported_currencies) {
            return true; // No restrictions
        }

        return in_array(strtoupper($currency), $this->supported_currencies);
    }
}
