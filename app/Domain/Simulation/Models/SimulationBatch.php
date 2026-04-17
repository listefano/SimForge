<?php

namespace App\Domain\Simulation\Models;

use App\Domain\Character\Models\Character;
use App\Domain\ItemDatabase\Models\LootSource;
use App\Domain\Simulation\Enums\SimulationBatchType;
use App\Domain\Simulation\Enums\SimulationStatus;
use App\Models\User;
use Database\Factories\SimulationBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SimulationBatch extends Model
{
    /** @use HasFactory<SimulationBatchFactory> */
    use HasFactory;

    protected static function newFactory(): SimulationBatchFactory
    {
        return SimulationBatchFactory::new();
    }

    protected $fillable = [
        'user_id',
        'character_id',
        'loot_source_id',
        'type',
        'name',
        'status',
        'total_simulations',
        'completed_simulations',
        'config',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => SimulationBatchType::class,
            'status' => SimulationStatus::class,
            'config' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function lootSource(): BelongsTo
    {
        return $this->belongsTo(LootSource::class);
    }

    public function simulations(): HasMany
    {
        return $this->hasMany(Simulation::class, 'simulation_batch_id');
    }
}
