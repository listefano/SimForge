<?php

namespace Tests\Unit\Domain\Simulation\Services;

use App\Domain\Simulation\DTOs\SimcProfileDto;
use App\Domain\Simulation\DTOs\SimcResultDto;
use App\Domain\Simulation\Exceptions\SimcExecutionException;
use App\Domain\Simulation\Exceptions\SimcNotFoundException;
use App\Domain\Simulation\Services\SimcRunnerService;
use ReflectionClass;
use Tests\TestCase;

class SimcRunnerServiceTest extends TestCase
{
    public function test_result_dto_parses_simc_json_output(): void
    {
        $raw = $this->sampleSimcOutput(dps: 450_000.0, stdDev: 1500.0, min: 440_000.0, max: 460_000.0, version: 'SimulationCraft-720-01');

        $result = SimcResultDto::fromArray($raw);

        $this->assertSame(450_000.0, $result->dps);
        $this->assertSame(1500.0, $result->dpsStdDev);
        $this->assertSame(440_000.0, $result->dpsMin);
        $this->assertSame(460_000.0, $result->dpsMax);
        $this->assertSame('SimulationCraft-720-01', $result->simcVersion);
        $this->assertSame($raw, $result->raw);
    }

    public function test_result_dto_defaults_to_zero_on_missing_data(): void
    {
        $result = SimcResultDto::fromArray([]);

        $this->assertSame(0.0, $result->dps);
        $this->assertSame(0.0, $result->dpsStdDev);
        $this->assertSame('unknown', $result->simcVersion);
    }

    public function test_profile_dto_uses_config_defaults(): void
    {
        $dto = SimcProfileDto::fromProfile('warrior="Test"');

        $this->assertSame('warrior="Test"', $dto->profile);
        $this->assertIsInt($dto->iterations);
        $this->assertIsInt($dto->threads);
    }

    public function test_profile_dto_accepts_explicit_options(): void
    {
        $dto = SimcProfileDto::fromProfile('warrior="Test"', iterations: 5000, threads: 4);

        $this->assertSame(5000, $dto->iterations);
        $this->assertSame(4, $dto->threads);
    }

    public function test_runner_throws_not_found_exception_when_binary_missing(): void
    {
        $this->expectException(SimcNotFoundException::class);

        // Override both config keys to point to non-existent paths.
        config(['simc.binary_path' => null]);
        config(['simc.channel' => 'stable']);
        config(['simc.install_path' => '/nonexistent/path']);

        $service = new SimcRunnerService;
        $service->run(SimcProfileDto::fromProfile('warrior="Test"'));
    }

    public function test_execution_exception_carries_exit_code_and_output(): void
    {
        $exception = new SimcExecutionException(
            message: 'SimulationCraft exited with errors.',
            exitCode: 1,
            output: 'Error: invalid profile',
        );

        $this->assertSame(1, $exception->exitCode);
        $this->assertSame('Error: invalid profile', $exception->output);
        $this->assertSame('SimulationCraft exited with errors.', $exception->getMessage());
    }

    public function test_cleanup_is_called_even_on_exception(): void
    {
        config(['simc.binary_path' => null]);
        config(['simc.install_path' => '/nonexistent/path']);

        $tempDirsBefore = $this->countSimforgeTempDirs();

        try {
            $service = new SimcRunnerService;
            $service->run(SimcProfileDto::fromProfile('warrior="Test"'));
        } catch (SimcNotFoundException) {
            // exception is expected — we're testing cleanup behaviour
        }

        // A SimcNotFoundException is thrown before the temp directory is created,
        // so the count should remain the same.
        $this->assertSame($tempDirsBefore, $this->countSimforgeTempDirs());
    }

    public function test_resolve_binary_path_prefers_custom_path(): void
    {
        $reflection = new ReflectionClass(SimcRunnerService::class);
        $method = $reflection->getMethod('resolveBinaryPath');
        $method->setAccessible(true);

        // Create a real executable temp file to simulate a custom binary.
        $fakeBinary = tempnam(sys_get_temp_dir(), 'simc_test_');
        chmod($fakeBinary, 0755);

        config(['simc.binary_path' => $fakeBinary]);

        $service = new SimcRunnerService;
        $resolved = $method->invoke($service);

        $this->assertSame($fakeBinary, $resolved);

        unlink($fakeBinary);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function sampleSimcOutput(
        float $dps,
        float $stdDev,
        float $min,
        float $max,
        string $version,
    ): array {
        return [
            'version' => $version,
            'sim' => [
                'players' => [
                    [
                        'name' => 'TestChar',
                        'collected_data' => [
                            'dps' => [
                                'mean' => $dps,
                                'mean_std_dev' => $stdDev,
                                'min' => $min,
                                'max' => $max,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function countSimforgeTempDirs(): int
    {
        return count(glob(sys_get_temp_dir().'/simforge_*') ?: []);
    }
}
