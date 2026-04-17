<?php

namespace App\Domain\Simulation\Models;

use App\Domain\Character\Models\Character;
use App\Domain\ItemDatabase\Models\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Simulation extends Model
{
    protected $fillable = [
        'simulation_batch_id',
        'character_id',
        'item_id',
        'type',
        'status',
        'iterations',
        'config',
        'results',
        'dps_gain',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'config' => 'array',
        'results' => 'array',
        'dps_gain' => 'float',
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

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
