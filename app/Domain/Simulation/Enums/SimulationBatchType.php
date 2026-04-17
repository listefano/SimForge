<?php

namespace App\Domain\Simulation\Enums;

enum SimulationBatchType: string
{
    case Standalone = 'standalone';
    case Droptimizer = 'droptimizer';
}
