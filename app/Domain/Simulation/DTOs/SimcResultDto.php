<?php

namespace App\Domain\Simulation\DTOs;

class SimcResultDto
{
    public function __construct(
        public readonly float $dps,
        public readonly float $dpsStdDev,
        public readonly float $dpsMin,
        public readonly float $dpsMax,
        public readonly string $simcVersion,
        public readonly array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Parsed simc json2 output.
     */
    public static function fromArray(array $data): self
    {
        $player = $data['sim']['players'][0] ?? [];
        $dpsData = $player['collected_data']['dps'] ?? [];

        return new self(
            dps: (float) ($dpsData['mean'] ?? 0),
            dpsStdDev: (float) ($dpsData['mean_std_dev'] ?? 0),
            dpsMin: (float) ($dpsData['min'] ?? 0),
            dpsMax: (float) ($dpsData['max'] ?? 0),
            simcVersion: (string) ($data['version'] ?? 'unknown'),
            raw: $data,
        );
    }
}
