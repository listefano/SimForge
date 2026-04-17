<?php

namespace Tests\Feature\Domain\Simulation\Jobs;

use App\Domain\Simulation\DTOs\SimcProfileDto;
use App\Domain\Simulation\DTOs\SimcResultDto;
use App\Domain\Simulation\Enums\SimulationStatus;
use App\Domain\Simulation\Events\SimulationBatchUpdated;
use App\Domain\Simulation\Events\SimulationCompleted;
use App\Domain\Simulation\Exceptions\SimcExecutionException;
use App\Domain\Simulation\Jobs\RunSimulationJob;
use App\Domain\Simulation\Models\Simulation;
use App\Domain\Simulation\Models\SimulationBatch;
use App\Domain\Simulation\Services\SimcRunnerService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RunSimulationJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    // -------------------------------------------------------------------------
    // Standalone simulation — happy path
    // -------------------------------------------------------------------------

    public function test_standalone_simulation_is_marked_completed_and_results_saved(): void
    {
        Event::fake([SimulationCompleted::class, SimulationBatchUpdated::class]);

        $this->mockRunner(dps: 450_000.0);
        $simulation = Simulation::factory()->create();

        RunSimulationJob::dispatchSync($simulation->id);

        $simulation->refresh();
        $this->assertSame(SimulationStatus::Completed, $simulation->status);
        $this->assertNotNull($simulation->results);
        $this->assertNotNull($simulation->started_at);
        $this->assertNotNull($simulation->finished_at);
        $this->assertNull($simulation->dps_gain);
    }

    public function test_standalone_simulation_broadcasts_simulation_completed(): void
    {
        Event::fake([SimulationCompleted::class, SimulationBatchUpdated::class]);

        $this->mockRunner(dps: 450_000.0);
        $simulation = Simulation::factory()->create();

        RunSimulationJob::dispatchSync($simulation->id);

        Event::assertDispatched(SimulationCompleted::class, fn ($e) => $e->simulation->id === $simulation->id);
        Event::assertNotDispatched(SimulationBatchUpdated::class);
    }

    public function test_standalone_simulation_uses_profile_from_config_when_set(): void
    {
        $capturedProfile = null;

        $mock = $this->createMock(SimcRunnerService::class);
        $mock->method('run')
            ->willReturnCallback(function (SimcProfileDto $dto) use (&$capturedProfile) {
                $capturedProfile = $dto->profile;

                return SimcResultDto::fromArray($this->sampleRaw(450_000.0));
            });

        $this->app->instance(SimcRunnerService::class, $mock);

        $simulation = Simulation::factory()->create([
            'config' => ['profile' => 'warrior="Override"'],
        ]);

        RunSimulationJob::dispatchSync($simulation->id);

        $this->assertSame('warrior="Override"', $capturedProfile);
    }

    // -------------------------------------------------------------------------
    // Failure handling
    // -------------------------------------------------------------------------

    public function test_failed_simulation_is_marked_failed(): void
    {
        Event::fake([SimulationCompleted::class, SimulationBatchUpdated::class]);

        $mock = $this->createMock(SimcRunnerService::class);
        $mock->method('run')->willThrowException(
            new SimcExecutionException('simc crashed', 1, 'fatal error')
        );
        $this->app->instance(SimcRunnerService::class, $mock);

        $simulation = Simulation::factory()->create();
        $job = new RunSimulationJob($simulation->id);

        $job->failed(new SimcExecutionException('simc crashed', 1, 'fatal error'));

        $simulation->refresh();
        $this->assertSame(SimulationStatus::Failed, $simulation->status);
        $this->assertNotNull($simulation->finished_at);
    }

    // -------------------------------------------------------------------------
    // Droptimizer base simulation
    // -------------------------------------------------------------------------

    public function test_droptimizer_base_sim_dispatches_item_sims_on_completion(): void
    {
        Event::fake([SimulationCompleted::class, SimulationBatchUpdated::class]);
        Queue::fake();

        $this->mockRunner(dps: 450_000.0);

        $batch = SimulationBatch::factory()->create(['total_simulations' => 4]);
        $baseSim = Simulation::factory()->droptimizerBase($batch)->create();
        $itemSims = Simulation::factory()->droptimizerItem($batch)->count(3)->create();

        // Use app()->call() so Queue::fake() only captures the inner item sim dispatches.
        app()->call([new RunSimulationJob($baseSim->id), 'handle']);

        Queue::assertPushed(RunSimulationJob::class, 3);

        $itemSims->each(fn ($s) => Queue::assertPushed(
            RunSimulationJob::class,
            fn ($job) => $job->simulationId === $s->id,
        ));
    }

    public function test_droptimizer_base_sim_increments_batch_completed_count(): void
    {
        Event::fake([SimulationCompleted::class, SimulationBatchUpdated::class]);
        Queue::fake();

        $this->mockRunner(dps: 450_000.0);

        $batch = SimulationBatch::factory()->create(['total_simulations' => 4]);
        $baseSim = Simulation::factory()->droptimizerBase($batch)->create();
        Simulation::factory()->droptimizerItem($batch)->count(3)->create();

        app()->call([new RunSimulationJob($baseSim->id), 'handle']);

        $this->assertSame(1, $batch->fresh()->completed_simulations);
    }

    // -------------------------------------------------------------------------
    // Droptimizer item simulation
    // -------------------------------------------------------------------------

    public function test_droptimizer_item_sim_calculates_dps_gain(): void
    {
        Event::fake([SimulationCompleted::class, SimulationBatchUpdated::class]);

        // Item sim runs at 470k DPS, base is 450k → gain = 20k
        $this->mockRunner(dps: 470_000.0);

        $batch = SimulationBatch::factory()->create(['total_simulations' => 2, 'completed_simulations' => 1]);

        // Base sim already completed with 450k DPS
        Simulation::factory()->droptimizerBase($batch)->completed(
            (new SimulationFactory)->sampleResults(450_000.0)
        )->create();

        $itemSim = Simulation::factory()->droptimizerItem($batch)->create();

        RunSimulationJob::dispatchSync($itemSim->id);

        $itemSim->refresh();
        $this->assertEqualsWithDelta(20_000.0, $itemSim->dps_gain, 0.001);
        $this->assertSame(SimulationStatus::Completed, $itemSim->status);
    }

    public function test_last_item_sim_marks_batch_completed(): void
    {
        Event::fake([SimulationCompleted::class, SimulationBatchUpdated::class]);

        $this->mockRunner(dps: 470_000.0);

        $batch = SimulationBatch::factory()->running()->create([
            'total_simulations' => 2,
            'completed_simulations' => 1,
        ]);

        Simulation::factory()->droptimizerBase($batch)->completed(
            (new SimulationFactory)->sampleResults(450_000.0)
        )->create();

        $itemSim = Simulation::factory()->droptimizerItem($batch)->create();

        RunSimulationJob::dispatchSync($itemSim->id);

        $batch->refresh();
        $this->assertSame(SimulationStatus::Completed, $batch->status);
        $this->assertNotNull($batch->finished_at);
        $this->assertSame(2, $batch->completed_simulations);
    }

    public function test_batch_updated_event_is_broadcast_after_item_sim(): void
    {
        Event::fake([SimulationCompleted::class, SimulationBatchUpdated::class]);

        $this->mockRunner(dps: 470_000.0);

        $batch = SimulationBatch::factory()->running()->create([
            'total_simulations' => 2,
            'completed_simulations' => 1,
        ]);

        Simulation::factory()->droptimizerBase($batch)->completed(
            (new SimulationFactory)->sampleResults(450_000.0)
        )->create();

        $itemSim = Simulation::factory()->droptimizerItem($batch)->create();

        RunSimulationJob::dispatchSync($itemSim->id);

        Event::assertDispatched(SimulationBatchUpdated::class, fn ($e) => $e->batch->id === $batch->id);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function mockRunner(float $dps): void
    {
        $mock = $this->createMock(SimcRunnerService::class);
        $mock->method('run')->willReturn(SimcResultDto::fromArray($this->sampleRaw($dps)));
        $this->app->instance(SimcRunnerService::class, $mock);
    }

    /** @return array<string, mixed> */
    private function sampleRaw(float $dps): array
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

// Alias so test helpers can reference the factory without a use statement.
class SimulationFactory extends \Database\Factories\SimulationFactory {}
