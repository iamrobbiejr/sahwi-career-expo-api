<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Program extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['university_id', 'name', 'type', 'scholarship_info'];
    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }
}
