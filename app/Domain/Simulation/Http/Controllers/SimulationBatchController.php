<?php

namespace App\Domain\Simulation\Http\Controllers;

use App\Domain\Simulation\Http\Resources\SimulationBatchProgressResource;
use App\Domain\Simulation\Http\Resources\SimulationResultResource;
use App\Domain\Simulation\Models\SimulationBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;

class SimulationBatchController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $user = $request->user();

        $query = SimulationBatch::query()
            ->with('character:id,name')
            ->latest('id');

        if (! $user->is_admin) {
            $query->where('user_id', $user->id);
        } elseif (isset($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        $perPage = $validated['per_page'] ?? 15;

        return SimulationBatchProgressResource::collection($query->paginate($perPage));
    }

    public function show(Request $request, SimulationBatch $batch): JsonResource
    {
        $this->authorizeBatchAccess($request, $batch);

        return new SimulationBatchProgressResource($batch->load('character:id,name'));
    }

    public function simulations(Request $request, SimulationBatch $batch): AnonymousResourceCollection
    {
        $this->authorizeBatchAccess($request, $batch);

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 50;

        $simulations = $batch->simulations()
            ->with('item:id,name,item_level,slot')
            ->latest('id')
            ->paginate($perPage);

        return SimulationResultResource::collection($simulations);
    }

    private function authorizeBatchAccess(Request $request, SimulationBatch $batch): void
    {
        $user = $request->user();

        abort_unless(
            $user->is_admin || (int) $batch->user_id === (int) $user->id,
            403,
            'You are not allowed to access this simulation batch.'
        );
    }
}
