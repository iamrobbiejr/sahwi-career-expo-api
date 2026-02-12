<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class OrganizationMember extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['organization_id', 'user_id', 'role'];
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
