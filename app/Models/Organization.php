<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Organization extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
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
