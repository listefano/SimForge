<?php

namespace App\Domain\Simulation\Http\Resources;

use App\Domain\Simulation\Models\Simulation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Simulation
 */
class SimulationResultResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_id' => $this->simulation_batch_id,
            'type' => $this->type,
            'status' => $this->status,
            'item_id' => $this->item_id,
            'item_name' => $this->item?->name,
            'item_level' => $this->item?->item_level,
            'item_slot' => $this->item?->slot,
            'dps' => data_get($this->results, 'sim.players.0.collected_data.dps.mean'),
            'dps_gain' => $this->dps_gain,
            'iterations' => $this->iterations,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
