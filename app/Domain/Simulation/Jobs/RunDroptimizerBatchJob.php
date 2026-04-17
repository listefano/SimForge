<?php

namespace App\Domain\Simulation\Jobs;

use App\Domain\Simulation\Enums\SimulationStatus;
use App\Domain\Simulation\Enums\SimulationType;
use App\Domain\Simulation\Events\SimulationBatchUpdated;
use App\Domain\Simulation\Models\Simulation;
use App\Domain\Simulation\Models\SimulationBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class RunDroptimizerBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $batchId) {}

    public function handle(): void
    {
        $batch = SimulationBatch::with('simulations')->findOrFail($this->batchId);

        $baseSim = $batch->simulations
            ->firstWhere('type', SimulationType::DroptimizerBase);

        if (! $baseSim) {
            throw new RuntimeException(
                "Droptimizer batch #{$this->batchId} has no base simulation."
            );
        }

        $batch->update([
            'status' => SimulationStatus::Running,
            'started_at' => now(),
        ]);

        SimulationBatchUpdated::dispatch($batch->fresh());

        RunSimulationJob::dispatch($baseSim->id);
    }

    public function failed(Throwable $exception): void
    {
        $batch = SimulationBatch::find($this->batchId);

        if (! $batch) {
            return;
        }

        $batch->update([
            'status' => SimulationStatus::Failed,
            'finished_at' => now(),
        ]);

        // Mark all pending simulations as failed so the batch is cleanly closed.
        Simulation::query()
            ->where('simulation_batch_id', $this->batchId)
            ->where('status', SimulationStatus::Pending)
            ->update(['status' => SimulationStatus::Failed]);

        SimulationBatchUpdated::dispatch($batch->fresh());
    }
}
