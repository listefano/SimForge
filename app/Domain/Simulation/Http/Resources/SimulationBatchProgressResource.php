<?php

namespace App\Domain\Simulation\Http\Resources;

use App\Domain\Simulation\Models\SimulationBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SimulationBatch
 */
class SimulationBatchProgressResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $progressPercent = $this->total_simulations > 0
            ? (int) round(($this->completed_simulations / $this->total_simulations) * 100)
            : 0;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->status,
            'user_id' => $this->user_id,
            'character_id' => $this->character_id,
            'character_name' => $this->character?->name,
            'total_simulations' => $this->total_simulations,
            'completed_simulations' => $this->completed_simulations,
            'remaining_simulations' => max($this->total_simulations - $this->completed_simulations, 0),
            'progress_percent' => $progressPercent,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
