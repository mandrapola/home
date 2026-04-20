<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ControllerReportReceived;
use App\Services\ControllerAutoRegistrationService;

class EnsureControllerExistsOnReport
{
    public function __construct(
        private readonly ControllerAutoRegistrationService $controllerAutoRegistrationService,
    ) {
    }

    public function handle(ControllerReportReceived $event): void
    {
        $this->controllerAutoRegistrationService->ensureControllerExists(
            $event->controllerId,
            $event->ip
        );
    }
}
