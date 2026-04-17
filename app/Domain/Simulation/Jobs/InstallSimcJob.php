<?php

namespace App\Domain\Simulation\Jobs;

use App\Domain\Simulation\Events\SimcInstallUpdated;
use App\Domain\Simulation\Services\SimcInstallerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class InstallSimcJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public readonly string $channel,
        public readonly bool $force,
    ) {}

    public function handle(SimcInstallerService $installer): void
    {
        SimcInstallUpdated::dispatch(
            status: 'started',
            channel: $this->channel,
            version: null,
            message: "Checking SimulationCraft [{$this->channel}] releases on GitHub…",
        );

        if (! $this->force && $installer->isInstalled($this->channel)) {
            $updated = $installer->update($this->channel);

            if (! $updated) {
                $version = $installer->installedVersion($this->channel);

                SimcInstallUpdated::dispatch(
                    status: 'up_to_date',
                    channel: $this->channel,
                    version: $version,
                    message: "SimulationCraft [{$this->channel}] is already up to date ({$version}).",
                );

                return;
            }
        } else {
            $installer->install($this->channel);
        }

        $version = $installer->installedVersion($this->channel);

        SimcInstallUpdated::dispatch(
            status: 'completed',
            channel: $this->channel,
            version: $version,
            message: "SimulationCraft [{$this->channel}] {$version} installed successfully.",
        );
    }

    public function failed(Throwable $exception): void
    {
        SimcInstallUpdated::dispatch(
            status: 'failed',
            channel: $this->channel,
            version: null,
            message: $exception->getMessage(),
        );
    }
}
