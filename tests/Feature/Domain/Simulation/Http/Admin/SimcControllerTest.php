<?php

namespace Tests\Feature\Domain\Simulation\Http\Admin;

use App\Domain\Simulation\Jobs\InstallSimcJob;
use App\Domain\Simulation\Services\SimcInstallerService;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SimcControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    // -------------------------------------------------------------------------
    // GET /api/v1/admin/simc/status
    // -------------------------------------------------------------------------

    public function test_status_returns_both_channels_for_admin_user(): void
    {
        $this->mockInstaller(installedStable: true, versionStable: '720-01', installedNightly: false, versionNightly: null);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/simc/status')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['channel' => 'stable', 'installed' => true, 'version' => '720-01'])
            ->assertJsonFragment(['channel' => 'nightly', 'installed' => false, 'version' => null]);
    }

    public function test_status_marks_active_channel(): void
    {
        config(['simc.channel' => 'nightly']);

        $this->mockInstaller(installedStable: true, versionStable: '720-01', installedNightly: true, versionNightly: '720-02-nightly');

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/simc/status')
            ->assertOk();

        $data = collect($response->json('data'));

        $this->assertTrue($data->firstWhere('channel', 'nightly')['active']);
        $this->assertFalse($data->firstWhere('channel', 'stable')['active']);
    }

    public function test_status_returns_403_for_non_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/admin/simc/status')
            ->assertForbidden();
    }

    public function test_status_returns_403_for_unauthenticated_request(): void
    {
        $this->getJson('/api/v1/admin/simc/status')
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/admin/simc/install
    // -------------------------------------------------------------------------

    public function test_install_dispatches_job_and_returns_202(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/simc/install', ['channel' => 'stable'])
            ->assertAccepted()
            ->assertJsonFragment(['channel' => 'stable']);

        Queue::assertPushed(InstallSimcJob::class, fn ($job) => $job->channel === 'stable' && $job->force === false);
    }

    public function test_install_dispatches_force_job(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/simc/install', ['channel' => 'nightly', 'force' => true])
            ->assertAccepted();

        Queue::assertPushed(InstallSimcJob::class, fn ($job) => $job->channel === 'nightly' && $job->force === true);
    }

    public function test_install_validates_channel(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/simc/install', ['channel' => 'invalid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['channel']);

        Queue::assertNothingPushed();
    }

    public function test_install_requires_channel(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/simc/install', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['channel']);

        Queue::assertNothingPushed();
    }

    public function test_install_returns_403_for_non_admin(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/admin/simc/install', ['channel' => 'stable'])
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function mockInstaller(
        bool $installedStable,
        ?string $versionStable,
        bool $installedNightly,
        ?string $versionNightly,
    ): void {
        $mock = $this->createMock(SimcInstallerService::class);

        $mock->method('isInstalled')
            ->willReturnMap([
                ['stable', $installedStable],
                ['nightly', $installedNightly],
            ]);

        $mock->method('installedVersion')
            ->willReturnMap([
                ['stable', $versionStable],
                ['nightly', $versionNightly],
            ]);

        $this->app->instance(SimcInstallerService::class, $mock);
    }
}
