<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donation extends Model
{
    protected $fillable = [
        'campaign_id',
        'donor_id',
        'amount_cents',
        'donor_name',
        'donor_email',
        'message',
        'status',
        'payment_method',
        'transaction_id',
        'anonymous',
        'completed_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'anonymous' => 'boolean',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'amount',
    ];

    protected $hidden = [
        'donor_email', // Don't expose email by default
    ];

    /**
     * Get the campaign this donation belongs to
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(DonationCampaign::class, 'campaign_id');
    }

    /**
     * Get the donor (user)
     */
    public function donor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'donor_id');
    }

    /**
     * Get amount in currency (dollars)
     */
    public function getAmountAttribute(): float
    {
        return $this->amount_cents / 100;
    }

    /**
     * Get display name for donor
     */
    public function getDonorDisplayNameAttribute(): string
    {
        if ($this->anonymous) {
            return 'Anonymous';
        }

        if ($this->donor) {
            return $this->donor->name;
        }

        return $this->donor_name ?? 'Anonymous';
    }

    /**
     * Scope to get only completed donations
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get only pending donations
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to filter by campaign
     */
    public function scopeForCampaign($query, $campaignId)
    {
        return $query->where('campaign_id', $campaignId);
    }

    /**
     * Mark donation as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark donation as failed
     */
    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
        ]);
    }

    /**
     * Mark donation as refunded
     */
    public function markAsRefunded(): void
    {
        $this->update([
            'status' => 'refunded',
        ]);
    }

    /**
     * Check if the donation is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if donation is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
