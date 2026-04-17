<?php

namespace Database\Factories;

use App\Domain\Character\Models\Character;
use App\Domain\Simulation\Enums\SimulationStatus;
use App\Domain\Simulation\Enums\SimulationType;
use App\Domain\Simulation\Models\Simulation;
use App\Domain\Simulation\Models\SimulationBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Simulation>
 */
class SimulationFactory extends Factory
{
    protected $model = Simulation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'simulation_batch_id' => null,
            'character_id' => Character::factory(),
            'item_id' => null,
            'type' => SimulationType::Standalone,
            'status' => SimulationStatus::Pending,
            'iterations' => 1000,
            'config' => null,
            'results' => null,
            'dps_gain' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    public function droptimizerBase(SimulationBatch $batch): static
    {
        return $this->state(fn () => [
            'simulation_batch_id' => $batch->id,
            'character_id' => $batch->character_id,
            'type' => SimulationType::DroptimizerBase,
        ]);
    }

    public function droptimizerItem(SimulationBatch $batch): static
    {
        return $this->state(fn () => [
            'simulation_batch_id' => $batch->id,
            'character_id' => $batch->character_id,
            'type' => SimulationType::DroptimizerItem,
        ]);
    }

    public function completed(array $results = []): static
    {
        return $this->state(fn () => [
            'status' => SimulationStatus::Completed,
            'results' => $results ?: $this->sampleResults(450_000.0),
            'started_at' => now()->subMinutes(2),
            'finished_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function sampleResults(float $dps = 450_000.0): array
    {
        return [
            'version' => 'SimulationCraft-720-01',
            'sim' => [
                'players' => [
                    [
                        'name' => 'TestChar',
                        'collected_data' => [
                            'dps' => [
                                'mean' => $dps,
                                'mean_std_dev' => 1500.0,
                                'min' => $dps - 10_000,
                                'max' => $dps + 10_000,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
