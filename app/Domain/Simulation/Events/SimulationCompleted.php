<?php

namespace App\Domain\Simulation\Events;

use App\Domain\Simulation\Models\Simulation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SimulationCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Simulation $simulation) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        $channels = [];

        if ($this->simulation->simulation_batch_id) {
            $channels[] = new PrivateChannel("batch.{$this->simulation->simulation_batch_id}");
        }

        $userId = $this->simulation->batch?->user_id
            ?? $this->simulation->character?->user_id;

        if ($userId) {
            $channels[] = new PrivateChannel("user.{$userId}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'simulation.completed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'simulation_id' => $this->simulation->id,
            'batch_id' => $this->simulation->simulation_batch_id,
            'type' => $this->simulation->type,
            'status' => $this->simulation->status,
            'dps' => data_get($this->simulation->results, 'sim.players.0.collected_data.dps.mean'),
            'dps_gain' => $this->simulation->dps_gain,
        ];
    }
}
