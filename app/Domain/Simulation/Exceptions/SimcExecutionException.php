<?php

namespace App\Domain\Simulation\Exceptions;

use RuntimeException;

class SimcExecutionException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $exitCode,
        public readonly string $output,
    ) {
        parent::__construct($message);
    }
}
