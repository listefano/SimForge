<?php

namespace App\Domain\Simulation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SimulationBatch extends Model
{
    protected $fillable = [
        'name',
        'status',
        'total_simulations',
        'completed_simulations',
        'config',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'config' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function simulations(): HasMany
    {
        return $this->hasMany(Simulation::class, 'simulation_batch_id');
    }
}
