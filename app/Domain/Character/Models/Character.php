<?php

namespace App\Domain\Character\Models;

use App\Domain\Simulation\Models\Simulation;
use App\Domain\Simulation\Models\SimulationBatch;
use App\Models\User;
use Database\Factories\CharacterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Character extends Model
{
    /** @use HasFactory<CharacterFactory> */
    use HasFactory;

    protected static function newFactory(): CharacterFactory
    {
        return CharacterFactory::new();
    }

    protected $fillable = [
        'user_id',
        'name',
        'class',
        'race',
        'spec',
        'level',
        'stats',
        'gear',
        'simc_profile',
    ];

    protected $casts = [
        'stats' => 'array',
        'gear' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function simulations(): HasMany
    {
        return $this->hasMany(Simulation::class);
    }

    public function simulationBatches(): HasMany
    {
        return $this->hasMany(SimulationBatch::class);
    }
}
