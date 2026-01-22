<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DonationCampaign extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'goal_cents',
        'active',
        'description',
        'created_by',
    ];

    protected $casts = [
        'goal_cents' => 'integer',
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'raised_cents',
        'goal_amount',
        'raised_amount',
        'progress_percentage',
    ];

    /**
     * Get the user who created the campaign
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all donations for this campaign
     */
    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class, 'campaign_id');
    }

    /**
     * Get only completed donations
     */
    public function completedDonations(): HasMany
    {
        return $this->hasMany(Donation::class, 'campaign_id')
            ->where('status', 'completed');
    }

    /**
     * Get raised amount in cents (derived attribute)
     */
    public function getRaisedCentsAttribute(): int
    {
        return $this->completedDonations()->sum('amount_cents');
    }

    /**
     * Get goal amount in dollars/currency
     */
    public function getGoalAmountAttribute(): float
    {
        return $this->goal_cents / 100;
    }

    /**
     * Get raised amount in dollars/currency
     */
    public function getRaisedAmountAttribute(): float
    {
        return $this->raised_cents / 100;
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->goal_cents == 0) {
            return 0;
        }

        return round(($this->raised_cents / $this->goal_cents) * 100, 2);
    }

    /**
     * Check if campaign has reached its goal
     */
    public function hasReachedGoal(): bool
    {
        return $this->raised_cents >= $this->goal_cents;
    }

    /**
     * Get remaining amount to reach goal
     */
    public function getRemainingCentsAttribute(): int
    {
        $remaining = $this->goal_cents - $this->raised_cents;
        return max(0, $remaining);
    }

    /**
     * Get remaining amount in currency
     */
    public function getRemainingAmountAttribute(): float
    {
        return $this->remaining_cents / 100;
    }

    /**
     * Scope to get only active campaigns
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope to get campaigns that haven't reached goal
     */
    public function scopeNotReachedGoal($query)
    {
        return $query->whereRaw('(
            SELECT COALESCE(SUM(amount_cents), 0)
            FROM donations
            WHERE campaign_id = donation_campaigns.id
            AND status = "completed"
        ) < goal_cents');
    }

    /**
     * Get total number of donors (unique)
     */
    public function getDonorsCountAttribute(): int
    {
        return $this->completedDonations()
            ->whereNotNull('donor_id')
            ->distinct('donor_id')
            ->count('donor_id');
    }

    /**
     * Get total number of donations
     */
    public function getDonationsCountAttribute(): int
    {
        return $this->completedDonations()->count();
    }
}
