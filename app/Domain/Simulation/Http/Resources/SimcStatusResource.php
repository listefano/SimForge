<?php

namespace App\Domain\Simulation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $channel
 * @property bool $installed
 * @property string|null $version
 * @property bool $active
 */
class SimcStatusResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'channel' => $this->channel,
            'installed' => $this->installed,
            'version' => $this->version,
            'active' => $this->active,
        ];
    }
}
