<?php

namespace App\Console\Commands;

use App\Domain\Simulation\Services\SimcInstallerService;
use Illuminate\Console\Command;

class SimcInstallCommand extends Command
{
    protected $signature = 'simc:install
                            {--channel= : Release channel to install (stable or nightly). Defaults to SIMC_CHANNEL.}
                            {--force : Reinstall even if the current version is already up to date.}';

    protected $description = 'Install or update the SimulationCraft binary.';

    public function handle(SimcInstallerService $installer): int
    {
        $channel = $this->option('channel') ?? config('simc.channel', 'stable');

        if (! in_array($channel, ['stable', 'nightly'], true)) {
            $this->error("Invalid channel '{$channel}'. Must be 'stable' or 'nightly'.");

            return self::FAILURE;
        }

        $this->info("Checking SimulationCraft [{$channel}] releases on GitHub…");

        if (! $this->option('force') && $installer->isInstalled($channel)) {
            $updated = $installer->update($channel);

            if (! $updated) {
                $version = $installer->installedVersion($channel);
                $this->info("SimulationCraft [{$channel}] is already up to date ({$version}).");

                return self::SUCCESS;
            }

            $version = $installer->installedVersion($channel);
            $this->info("SimulationCraft [{$channel}] updated to {$version}.");

            return self::SUCCESS;
        }

        $this->info('Downloading SimulationCraft…');

        $installer->install($channel);

        $version = $installer->installedVersion($channel);
        $this->info("SimulationCraft [{$channel}] {$version} installed successfully.");

        return self::SUCCESS;
    }
}
