<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserReward extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'points',
        'meta',
        'awarded_at',
        'award_date',
    ];

    protected $casts = [
        'meta' => 'array',
        'awarded_at' => 'datetime',
        'award_date' => 'date',
        'points' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
