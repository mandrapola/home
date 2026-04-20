<?php

declare(strict_types=1);

namespace App\Events;

class ControllerReportReceived
{
    public function __construct(
        public readonly string $controllerId,
        public readonly string $ip,
    ) {
    }
}

