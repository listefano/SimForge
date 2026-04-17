<?php

namespace Tests\Feature\Domain\Simulation\Jobs;

use App\Domain\Simulation\Enums\SimulationStatus;
use App\Domain\Simulation\Events\SimulationBatchUpdated;
use App\Domain\Simulation\Jobs\RunDroptimizerBatchJob;
use App\Domain\Simulation\Jobs\RunSimulationJob;
use App\Domain\Simulation\Models\Simulation;
use App\Domain\Simulation\Models\SimulationBatch;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class RunDroptimizerBatchJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_batch_is_marked_running_and_base_sim_is_dispatched(): void
    {
        Event::fake([SimulationBatchUpdated::class]);
        Queue::fake();

        $batch = SimulationBatch::factory()->create(['total_simulations' => 4]);
        $baseSim = Simulation::factory()->droptimizerBase($batch)->create();
        Simulation::factory()->droptimizerItem($batch)->count(3)->create();

        // Call handle() directly so Queue::fake() only captures inner dispatches.
        (new RunDroptimizerBatchJob($batch->id))->handle();

        $batch->refresh();
        $this->assertSame(SimulationStatus::Running, $batch->status);
        $this->assertNotNull($batch->started_at);

        Queue::assertPushed(RunSimulationJob::class, fn ($job) => $job->simulationId === $baseSim->id);
        Queue::assertPushed(RunSimulationJob::class, 1);
    }

    public function test_batch_updated_event_is_broadcast_on_start(): void
    {
        Event::fake([SimulationBatchUpdated::class]);
        Queue::fake();

        $batch = SimulationBatch::factory()->create(['total_simulations' => 2]);
        Simulation::factory()->droptimizerBase($batch)->create();
        Simulation::factory()->droptimizerItem($batch)->create();

        (new RunDroptimizerBatchJob($batch->id))->handle();

        Event::assertDispatched(SimulationBatchUpdated::class, fn ($e) => $e->batch->id === $batch->id);
    }

    public function test_fails_when_no_base_simulation_exists(): void
    {
        Event::fake([SimulationBatchUpdated::class]);

        $batch = SimulationBatch::factory()->create(['total_simulations' => 3]);
        Simulation::factory()->droptimizerItem($batch)->count(3)->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no base simulation/i');

        (new RunDroptimizerBatchJob($batch->id))->handle();
    }

    public function test_failed_handler_marks_batch_and_pending_sims_as_failed(): void
    {
        Event::fake([SimulationBatchUpdated::class]);

        $batch = SimulationBatch::factory()->create(['total_simulations' => 3]);
        Simulation::factory()->droptimizerBase($batch)->create();
        Simulation::factory()->droptimizerItem($batch)->count(2)->create();

        $job = new RunDroptimizerBatchJob($batch->id);
        $job->failed(new RuntimeException('something went wrong'));

        $batch->refresh();
        $this->assertSame(SimulationStatus::Failed, $batch->status);
        $this->assertNotNull($batch->finished_at);

        $this->assertSame(
            3,
            Simulation::where('simulation_batch_id', $batch->id)
                ->where('status', SimulationStatus::Failed)
                ->count()
        );
    }

    public function test_failed_handler_broadcasts_batch_updated(): void
    {
        Event::fake([SimulationBatchUpdated::class]);

        $batch = SimulationBatch::factory()->create(['total_simulations' => 1]);
        Simulation::factory()->droptimizerBase($batch)->create();

        $job = new RunDroptimizerBatchJob($batch->id);
        $job->failed(new RuntimeException('error'));

        Event::assertDispatched(SimulationBatchUpdated::class, fn ($e) => $e->batch->id === $batch->id);
    }

    public function test_only_pending_sims_are_marked_failed_by_failed_handler(): void
    {
        Event::fake([SimulationBatchUpdated::class]);

        $batch = SimulationBatch::factory()->create(['total_simulations' => 3]);
        Simulation::factory()->droptimizerBase($batch)->completed()->create();
        Simulation::factory()->droptimizerItem($batch)->count(2)->create();

        $job = new RunDroptimizerBatchJob($batch->id);
        $job->failed(new RuntimeException('error'));

        // Only the 2 pending item sims should be marked failed; the completed base sim stays completed.
        $this->assertSame(1, Simulation::where('simulation_batch_id', $batch->id)->where('status', SimulationStatus::Completed)->count());
        $this->assertSame(2, Simulation::where('simulation_batch_id', $batch->id)->where('status', SimulationStatus::Failed)->count());
    }
}
