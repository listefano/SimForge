<?php

namespace App\Domain\Simulation\Enums;

enum SimulationType: string
{
    case Standalone = 'standalone';
    case DroptimizerBase = 'droptimizer_base';
    case DroptimizerItem = 'droptimizer_item';
}
