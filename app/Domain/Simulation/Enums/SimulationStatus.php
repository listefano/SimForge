<?php

namespace App\Domain\Simulation\Enums;

enum SimulationStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
