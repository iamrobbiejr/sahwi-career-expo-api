<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Program extends Model
{
    protected $fillable = ['university_id', 'name', 'type', 'scholarship_info'];
    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }
}
