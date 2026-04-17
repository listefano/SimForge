<?php

namespace App\Domain\Simulation\Models;

use App\Domain\Character\Models\Character;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Simulation extends Model
{
    protected $fillable = [
        'simulation_batch_id',
        'character_id',
        'status',
        'iterations',
        'config',
        'results',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'config' => 'array',
        'results' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SimulationBatch::class, 'simulation_batch_id');
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
