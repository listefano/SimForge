<?php

namespace App\Domain\Character\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Character extends Model
{
    protected $fillable = [
        'name',
        'class',
        'level',
        'stats',
        'gear',
    ];

    protected $casts = [
        'stats' => 'array',
        'gear' => 'array',
    ];

    public function simulations(): HasMany
    {
        return $this->hasMany(\App\Domain\Simulation\Models\Simulation::class);
    }
}
