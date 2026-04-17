<?php

namespace Tests\Feature\Domain\Simulation\Events;

use App\Domain\Simulation\Events\SimulationBatchUpdated;
use App\Domain\Simulation\Events\SimulationCompleted;
use App\Domain\Simulation\Models\Simulation;
use App\Domain\Simulation\Models\SimulationBatch;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SimulationBroadcastEventsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_batch_updated_event_uses_stable_name_and_contains_progress_payload(): void
    {
        $batch = SimulationBatch::factory()->create([
            'total_simulations' => 10,
            'completed_simulations' => 3,
        ]);

        $event = new SimulationBatchUpdated($batch);

        $this->assertSame('simulation.batch.updated', $event->broadcastAs());
        $this->assertSame(7, $event->broadcastWith()['remaining_simulations']);
        $this->assertSame(30.0, $event->broadcastWith()['progress_percent']);
    }

    public function test_batch_updated_event_broadcasts_to_batch_and_user_channels(): void
    {
        $batch = SimulationBatch::factory()->create();

        $event = new SimulationBatchUpdated($batch);

        $channelNames = collect($event->broadcastOn())
            ->filter(fn ($channel) => $channel instanceof PrivateChannel)
            ->map(fn (PrivateChannel $channel) => $channel->name)
            ->values()
            ->all();

        $this->assertContains("private-batch.{$batch->id}", $channelNames);
        $this->assertContains("private-user.{$batch->user_id}", $channelNames);
    }

    public function test_simulation_completed_event_uses_stable_name_and_payload(): void
    {
        $batch = SimulationBatch::factory()->create();

        $simulation = Simulation::factory()->droptimizerItem($batch)->completed([
            'sim' => [
                'players' => [
                    ['collected_data' => ['dps' => ['mean' => 456789.0]]],
                ],
            ],
        ])->create([
            'dps_gain' => 1234.0,
        ]);

        $event = new SimulationCompleted($simulation->fresh(['batch', 'character']));

        $payload = $event->broadcastWith();

        $this->assertSame('simulation.completed', $event->broadcastAs());
        $this->assertSame($simulation->id, $payload['simulation_id']);
        $this->assertSame($batch->id, $payload['batch_id']);
        $this->assertEquals(456789.0, $payload['dps']);
        $this->assertSame(1234.0, $payload['dps_gain']);
    }
}
