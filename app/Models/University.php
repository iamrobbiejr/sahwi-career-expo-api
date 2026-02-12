<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class University extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['name', 'province'];
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }
}
