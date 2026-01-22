<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = ['type', 'name', 'verified', 'verification_docs'];
    protected $casts = [
        'verification_docs' => 'array',
        'verified' => 'boolean',
    ];
    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }
}
