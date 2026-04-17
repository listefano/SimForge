<?php

namespace App\Domain\Simulation\DTOs;

class SimcProfileDto
{
    public function __construct(
        public readonly string $profile,
        public readonly int $iterations,
        public readonly int $threads,
    ) {}

    public static function fromProfile(
        string $profile,
        ?int $iterations = null,
        ?int $threads = null,
    ): self {
        return new self(
            profile: $profile,
            iterations: $iterations ?? config('simc.iterations'),
            threads: $threads ?? config('simc.threads'),
        );
    }
}
