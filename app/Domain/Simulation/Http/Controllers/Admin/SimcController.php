<?php

namespace App\Domain\Simulation\Http\Controllers\Admin;

use App\Domain\Simulation\Http\Resources\SimcStatusResource;
use App\Domain\Simulation\Jobs\InstallSimcJob;
use App\Domain\Simulation\Services\SimcInstallerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class SimcController extends Controller
{
    public function __construct(private readonly SimcInstallerService $installer) {}

    public function status(): AnonymousResourceCollection
    {
        $activeChannel = config('simc.channel', 'stable');

        $channels = collect(['stable', 'nightly'])->map(fn (string $channel) => (object) [
            'channel' => $channel,
            'installed' => $this->installer->isInstalled($channel),
            'version' => $this->installer->installedVersion($channel),
            'active' => $channel === $activeChannel,
        ]);

        return SimcStatusResource::collection($channels);
    }

    public function install(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['required', Rule::in(['stable', 'nightly'])],
            'force' => ['boolean'],
        ]);

        InstallSimcJob::dispatch($validated['channel'], (bool) ($validated['force'] ?? false));

        return response()->json([
            'message' => "SimulationCraft [{$validated['channel']}] installation has been queued.",
            'channel' => $validated['channel'],
        ], 202);
    }
}
