<?php

declare(strict_types=1);

namespace App\Events;

class ControllerReadingsReceived
{
    /**
     * @param array<int, array{pin:string, value:float|int}> $readings
     */
    public function __construct(
        public readonly string $controllerId,
        public readonly array $readings,
    ) {
    }
}

