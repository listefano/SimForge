<?php

namespace App\Domain\Simulation\Jobs;

use App\Domain\Simulation\DTOs\SimcProfileDto;
use App\Domain\Simulation\DTOs\SimcResultDto;
use App\Domain\Simulation\Enums\SimulationStatus;
use App\Domain\Simulation\Enums\SimulationType;
use App\Domain\Simulation\Events\SimulationBatchUpdated;
use App\Domain\Simulation\Events\SimulationCompleted;
use App\Domain\Simulation\Models\Simulation;
use App\Domain\Simulation\Models\SimulationBatch;
use App\Domain\Simulation\Services\SimcRunnerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class RunSimulationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $simulationId)
    {
        $this->onQueue('simulations');
        $this->timeout = config('simc.timeout', 300);
    }

    public function handle(SimcRunnerService $runner): void
    {
        $simulation = Simulation::with(['character', 'batch'])->findOrFail($this->simulationId);

        $simulation->update([
            'status' => SimulationStatus::Running,
            'started_at' => now(),
        ]);

        $profile = data_get($simulation->config, 'profile') ?? $simulation->character->simc_profile;

        $result = $runner->run(
            SimcProfileDto::fromProfile($profile, iterations: $simulation->iterations)
        );

        $dpsGain = $this->calculateDpsGain($simulation, $result);

        $simulation->update([
            'status' => SimulationStatus::Completed,
            'results' => $result->raw,
            'dps_gain' => $dpsGain,
            'finished_at' => now(),
        ]);

        // Base sim of a droptimizer batch triggers all item sims.
        if ($simulation->type === SimulationType::DroptimizerBase && $simulation->batch) {
            $this->dispatchItemSimulations($simulation->batch);
        }

        $this->finalizeBatchProgress($simulation->simulation_batch_id);

        SimulationCompleted::dispatch($simulation->fresh(['batch', 'character']));
    }

    public function failed(Throwable $exception): void
    {
        $simulation = Simulation::with('batch')->find($this->simulationId);

        if (! $simulation) {
            return;
        }

        $simulation->update([
            'status' => SimulationStatus::Failed,
            'finished_at' => now(),
        ]);

        $this->finalizeBatchProgress($simulation->simulation_batch_id, failed: true);

        SimulationCompleted::dispatch($simulation->fresh(['batch', 'character']));
    }

    private function calculateDpsGain(Simulation $simulation, SimcResultDto $result): ?float
    {
        if ($simulation->type !== SimulationType::DroptimizerItem || ! $simulation->simulation_batch_id) {
            return null;
        }

        $baseSim = Simulation::query()
            ->where('simulation_batch_id', $simulation->simulation_batch_id)
            ->where('type', SimulationType::DroptimizerBase)
            ->whereNotNull('results')
            ->first();

        if (! $baseSim) {
            return null;
        }

        $baseDps = SimcResultDto::fromArray($baseSim->results)->dps;

        return $result->dps - $baseDps;
    }

    private function dispatchItemSimulations(SimulationBatch $batch): void
    {
        Simulation::query()
            ->where('simulation_batch_id', $batch->id)
            ->where('type', SimulationType::DroptimizerItem)
            ->where('status', SimulationStatus::Pending)
            ->pluck('id')
            ->each(fn (int $id) => static::dispatch($id));
    }

    private function finalizeBatchProgress(
        ?int $batchId,
        bool $failed = false,
    ): void {
        if (! $batchId) {
            return;
        }

        $batch = DB::transaction(function () use ($batchId, $failed) {
            $batch = SimulationBatch::lockForUpdate()->find($batchId);

            if (! $batch) {
                return null;
            }

            $batch->increment('completed_simulations');
            $batch->refresh();

            if ($batch->completed_simulations >= $batch->total_simulations) {
                $hasFailed = $failed || $batch->simulations()
                    ->where('status', SimulationStatus::Failed)
                    ->exists();

                $batch->update([
                    'status' => $hasFailed ? SimulationStatus::Failed : SimulationStatus::Completed,
                    'finished_at' => now(),
                ]);

                $batch->refresh();
            }

            return $batch;
        });

        if ($batch) {
            SimulationBatchUpdated::dispatch($batch);
        }
    }
}
