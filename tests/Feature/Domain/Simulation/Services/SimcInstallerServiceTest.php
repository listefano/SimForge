<?php

namespace Tests\Feature\Domain\Simulation\Services;

use App\Domain\Simulation\Services\SimcInstallerService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SimcInstallerServiceTest extends TestCase
{
    public function test_fetch_release_returns_latest_stable_release(): void
    {
        Http::fake([
            'api.github.com/repos/simulationcraft/simc/releases/latest' => Http::response(
                $this->stableReleasePayload(),
                200,
            ),
        ]);

        $service = new SimcInstallerService;
        $release = $service->fetchRelease('stable');

        $this->assertSame('720-01', $release['tag_name']);
        $this->assertFalse($release['prerelease']);
    }

    public function test_fetch_release_returns_first_prerelease_for_nightly(): void
    {
        Http::fake([
            'api.github.com/repos/simulationcraft/simc/releases' => Http::response(
                [$this->nightlyReleasePayload(), $this->stableReleasePayload()],
                200,
            ),
        ]);

        $service = new SimcInstallerService;
        $release = $service->fetchRelease('nightly');

        $this->assertSame('720-01-nightly', $release['tag_name']);
        $this->assertTrue($release['prerelease']);
    }

    public function test_fetch_release_throws_when_no_nightly_found(): void
    {
        Http::fake([
            'api.github.com/repos/simulationcraft/simc/releases' => Http::response(
                [$this->stableReleasePayload()],
                200,
            ),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/No nightly/i');

        $service = new SimcInstallerService;
        $service->fetchRelease('nightly');
    }

    public function test_fetch_release_throws_on_github_api_error(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([], 500),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to fetch/i');

        $service = new SimcInstallerService;
        $service->fetchRelease('stable');
    }

    public function test_installed_version_returns_null_when_not_installed(): void
    {
        config(['simc.install_path' => sys_get_temp_dir().'/simforge_installer_test_'.uniqid()]);

        $service = new SimcInstallerService;

        $this->assertNull($service->installedVersion('stable'));
    }

    public function test_is_installed_returns_false_when_binary_absent(): void
    {
        config(['simc.install_path' => sys_get_temp_dir().'/simforge_installer_test_'.uniqid()]);

        $service = new SimcInstallerService;

        $this->assertFalse($service->isInstalled('stable'));
    }

    public function test_github_client_sends_authorization_header_when_token_configured(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response($this->stableReleasePayload(), 200),
        ]);

        config(['simc.github.token' => 'test-token']);

        $service = new SimcInstallerService;
        $service->fetchRelease('stable');

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'Bearer test-token');
        });
    }

    public function test_github_client_omits_authorization_header_when_no_token(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response($this->stableReleasePayload(), 200),
        ]);

        config(['simc.github.token' => null]);

        $service = new SimcInstallerService;
        $service->fetchRelease('stable');

        Http::assertSent(function (Request $request) {
            return ! $request->hasHeader('Authorization');
        });
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function stableReleasePayload(): array
    {
        return [
            'tag_name' => '720-01',
            'prerelease' => false,
            'published_at' => '2024-01-15T12:00:00Z',
            'assets' => [
                [
                    'name' => 'SimulationCraft-720-01-Linux-x86_64-python3.tar.gz',
                    'browser_download_url' => 'https://github.com/simulationcraft/simc/releases/download/720-01/SimulationCraft-720-01-Linux-x86_64-python3.tar.gz',
                ],
                [
                    'name' => 'SimulationCraft-720-01-win64.exe',
                    'browser_download_url' => 'https://github.com/simulationcraft/simc/releases/download/720-01/SimulationCraft-720-01-win64.exe',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function nightlyReleasePayload(): array
    {
        return [
            'tag_name' => '720-01-nightly',
            'prerelease' => true,
            'published_at' => '2024-01-16T06:00:00Z',
            'assets' => [
                [
                    'name' => 'SimulationCraft-720-01-nightly-Linux-x86_64-python3.tar.gz',
                    'browser_download_url' => 'https://github.com/simulationcraft/simc/releases/download/720-01-nightly/SimulationCraft-720-01-nightly-Linux-x86_64-python3.tar.gz',
                ],
            ],
        ];
    }
}
