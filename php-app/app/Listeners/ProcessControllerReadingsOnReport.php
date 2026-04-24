<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ControllerReadingsReceived;
use App\Services\Alice\AliceStateNotificationService;
use App\Services\Report\ControllerPinProvisioningService;
use App\Services\Report\PinDataCleanupService;
use App\Services\Report\PinDataHistoryService;
use App\Services\Report\PinValueSyncService;
use App\Services\Report\ScenarioDesiredValueService;

class ProcessControllerReadingsOnReport
{
    public function __construct(
        private readonly ControllerPinProvisioningService $controllerPinProvisioningService,
        private readonly PinDataCleanupService $pinDataCleanupService,
        private readonly PinDataHistoryService $pinDataHistoryService,
        private readonly PinValueSyncService $pinValueSyncService,
        private readonly ScenarioDesiredValueService $scenarioDesiredValueService,
        private readonly AliceStateNotificationService $aliceStateNotificationService,
    ) {
    }

    public function handle(ControllerReadingsReceived $event): void
    {
        $this->pinDataCleanupService->cleanupIfDue();

        $maps = $this->controllerPinProvisioningService->ensurePinsAndBuildMaps(
            $event->controllerId,
            $event->readings
        );

        $this->pinDataHistoryService->storeReadings(
            $event->readings,
            $maps['pinIdByName'],
            $maps['pinStyleByName']
        );

        $changedPowerStatesByPinId = $this->pinValueSyncService->syncFromReadings(
            $event->readings,
            $maps['pinIdByName'],
            $maps['pinStyleByName']
        );

        $this->scenarioDesiredValueService->applyDesiredValue($event->controllerId);

        $this->aliceStateNotificationService->notifyPowerStateChanges(
            $event->controllerId,
            $changedPowerStatesByPinId
        );
    }
}
