<?php

namespace App\Domain\Simulation\Services;

use App\Domain\Simulation\DTOs\SimcProfileDto;
use App\Domain\Simulation\DTOs\SimcResultDto;
use App\Domain\Simulation\Exceptions\SimcExecutionException;
use App\Domain\Simulation\Exceptions\SimcNotFoundException;
use Symfony\Component\Process\Process;

class SimcRunnerService
{
    public function run(SimcProfileDto $profile): SimcResultDto
    {
        $binary = $this->resolveBinaryPath();
        $tempDir = $this->createTempDirectory();

        try {
            $profilePath = $tempDir.'/profile.simc';
            $resultPath = $tempDir.'/result.json';

            file_put_contents($profilePath, $profile->profile);

            $process = new Process(
                command: [
                    $binary,
                    'input='.$profilePath,
                    'json2='.$resultPath,
                    'threads='.$profile->threads,
                    'iterations='.$profile->iterations,
                ],
                timeout: config('simc.timeout'),
            );

            $process->run();

            if (! $process->isSuccessful() || ! file_exists($resultPath)) {
                throw new SimcExecutionException(
                    message: 'SimulationCraft exited with errors.',
                    exitCode: $process->getExitCode() ?? 1,
                    output: $process->getOutput().$process->getErrorOutput(),
                );
            }

            $raw = json_decode(file_get_contents($resultPath), true, 512, JSON_THROW_ON_ERROR);

            return SimcResultDto::fromArray($raw);
        } finally {
            $this->cleanupDirectory($tempDir);
        }
    }

    private function resolveBinaryPath(): string
    {
        $customPath = config('simc.binary_path');

        if ($customPath && is_executable($customPath)) {
            return $customPath;
        }

        $channel = config('simc.channel', 'stable');
        $managedPath = config('simc.install_path').'/'.$channel.'/simc';

        if (is_executable($managedPath)) {
            return $managedPath;
        }

        throw new SimcNotFoundException(
            'SimulationCraft binary not found. Run: php artisan simc:install'
        );
    }

    private function createTempDirectory(): string
    {
        $path = sys_get_temp_dir().'/simforge_'.uniqid('', true);
        mkdir($path, 0755, true);

        return $path;
    }

    private function cleanupDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (glob($path.'/*') as $file) {
            unlink($file);
        }

        rmdir($path);
    }
}
