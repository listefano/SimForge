<?php

namespace App\Domain\Simulation\Events;

use App\Domain\Simulation\Models\SimulationBatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SimulationBatchUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly SimulationBatch $batch) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel("batch.{$this->batch->id}")];

        if ($this->batch->user_id) {
            $channels[] = new PrivateChannel("user.{$this->batch->user_id}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'simulation.batch.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $remainingSimulations = max($this->batch->total_simulations - $this->batch->completed_simulations, 0);

        return [
            'batch_id' => $this->batch->id,
            'status' => $this->batch->status,
            'total_simulations' => $this->batch->total_simulations,
            'completed_simulations' => $this->batch->completed_simulations,
            'remaining_simulations' => $remainingSimulations,
            'progress_percent' => $this->batch->total_simulations > 0
                ? round(($this->batch->completed_simulations / $this->batch->total_simulations) * 100)
                : 0,
        ];
    }
}
