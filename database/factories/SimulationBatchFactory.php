<?php

namespace Database\Factories;

use App\Domain\Character\Models\Character;
use App\Domain\Simulation\Enums\SimulationBatchType;
use App\Domain\Simulation\Enums\SimulationStatus;
use App\Domain\Simulation\Models\SimulationBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SimulationBatch>
 */
class SimulationBatchFactory extends Factory
{
    protected $model = SimulationBatch::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'character_id' => Character::factory(),
            'loot_source_id' => null,
            'type' => SimulationBatchType::Droptimizer,
            'name' => fake()->words(3, true),
            'status' => SimulationStatus::Pending,
            'total_simulations' => 0,
            'completed_simulations' => 0,
            'config' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn () => [
            'status' => SimulationStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => SimulationStatus::Completed,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
        ]);
    }
}
