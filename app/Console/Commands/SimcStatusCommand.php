<?php

namespace App\Console\Commands;

use App\Domain\Simulation\Services\SimcInstallerService;
use Illuminate\Console\Command;

class SimcStatusCommand extends Command
{
    protected $signature = 'simc:status';

    protected $description = 'Show the installed SimulationCraft version and active channel.';

    public function handle(SimcInstallerService $installer): int
    {
        $activeChannel = config('simc.channel', 'stable');
        $customBinary = config('simc.binary_path');

        if ($customBinary) {
            $status = is_executable($customBinary) ? '<fg=green>found</>' : '<fg=red>not found</>';
            $this->line("  Binary path : {$customBinary} [{$status}]");
            $this->line('  Channel     : <fg=yellow>overridden by SIMC_BINARY_PATH</>');

            return self::SUCCESS;
        }

        $this->line("  Active channel : <fg=cyan>{$activeChannel}</>");
        $this->newLine();

        foreach (['stable', 'nightly'] as $channel) {
            $installed = $installer->isInstalled($channel);
            $version = $installer->installedVersion($channel) ?? 'not installed';
            $active = $channel === $activeChannel ? ' <fg=cyan>(active)</>' : '';
            $statusLabel = $installed ? "<fg=green>{$version}</>" : "<fg=yellow>{$version}</>";

            $this->line("  {$channel}: {$statusLabel}{$active}");
        }

        return self::SUCCESS;
    }
}
