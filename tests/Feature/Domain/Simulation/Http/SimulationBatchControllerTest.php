<?php

namespace Tests\Feature\Domain\Simulation\Http;

use App\Domain\Simulation\Enums\SimulationStatus;
use App\Domain\Simulation\Models\Simulation;
use App\Domain\Simulation\Models\SimulationBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SimulationBatchControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_index_returns_only_batches_of_authenticated_user(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();
        /** @var User $other */
        $other = User::factory()->create();

        $ownBatch = SimulationBatch::factory()->create(['user_id' => $owner->id]);
        SimulationBatch::factory()->create(['user_id' => $other->id]);

        $this->actingAs($owner)
            ->getJson('/api/v1/simulations/batches')
            ->assertOk()
            ->assertJsonPath('data.0.id', $ownBatch->id)
            ->assertJsonCount(1, 'data');
    }

    public function test_owner_can_view_batch_progress(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();

        $batch = SimulationBatch::factory()->running()->create([
            'user_id' => $owner->id,
            'total_simulations' => 10,
            'completed_simulations' => 4,
            'status' => SimulationStatus::Running,
        ]);

        $this->actingAs($owner)
            ->getJson("/api/v1/simulations/batches/{$batch->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $batch->id)
            ->assertJsonPath('data.total_simulations', 10)
            ->assertJsonPath('data.completed_simulations', 4)
            ->assertJsonPath('data.remaining_simulations', 6)
            ->assertJsonPath('data.progress_percent', 40);
    }

    public function test_non_owner_cannot_view_batch_progress(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();
        /** @var User $other */
        $other = User::factory()->create();

        $batch = SimulationBatch::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->getJson("/api/v1/simulations/batches/{$batch->id}")
            ->assertForbidden();
    }

    public function test_owner_can_list_simulations_for_batch(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();

        $batch = SimulationBatch::factory()->create([
            'user_id' => $owner->id,
            'total_simulations' => 2,
        ]);

        Simulation::factory()->droptimizerBase($batch)->completed()->create();
        Simulation::factory()->droptimizerItem($batch)->create();

        $this->actingAs($owner)
            ->getJson("/api/v1/simulations/batches/{$batch->id}/simulations")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.batch_id', $batch->id);
    }
}
