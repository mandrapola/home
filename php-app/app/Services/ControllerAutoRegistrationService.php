<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\IoTController;

class ControllerAutoRegistrationService
{
    public function ensureControllerExists(string $controllerId, string $ip): void
    {
        $exists = IoTController::query()
            ->where('id', $controllerId)
            ->exists();

        if ($exists) {
            return;
        }

        IoTController::query()->create([
            'id' => $controllerId,
            'name' => 'controller-' . substr($controllerId, 0, 8),
            'discription' => 'Auto-registered from ' . $ip,
            'send_interval_seconds' => 5,
            'status' => 'unclaimed',
            'is_service' => 0,
            'last_seen_at' => now(),
        ]);
    }
}
