<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Services\Billing\PlanLimitService;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class PinDataHistoryService
{
    public function __construct(
        private readonly PlanLimitService $planLimitService,
    ) {
    }

    private function normalizeReadingValue(mixed $value, string $pinStyle): ?float
    {
        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            return null;
        }

        $value = is_string($value) ? trim($value) : $value;
        if (! is_numeric($value)) {
            return null;
        }

        $normalized = (float) $value;
        if ($pinStyle === 'power' && ! in_array($normalized, [0.0, 1.0], true)) {
            return null;
        }

        if ($pinStyle !== 'power' && $pinStyle !== 'sensor' && ! str_starts_with($pinStyle, 'sensor_')) {
            return null;
        }

        return $normalized;
    }

    /**
     * @param array<int, array{pin:string, value:mixed}> $readings
     * @param array<string,string> $pinIdByName
     * @param array<string,string> $pinStyleByName
     */
    public function storeReadings(array $readings, array $pinIdByName, array $pinStyleByName = [], string $controllerId = ''): void
    {
        if ($controllerId !== '' && ! $this->planLimitService->canInsertPinDataForController($controllerId)) {
            return;
        }

        $now = now();
        foreach ($readings as $reading) {
            $pinName = strtoupper(trim((string) $reading['pin']));
            $pinId = $pinIdByName[$pinName] ?? null;
            if (! $pinId) {
                continue;
            }

            $pinStyle = strtolower((string) ($pinStyleByName[$pinName] ?? ''));
            $incomingValue = $this->normalizeReadingValue($reading['value'] ?? null, $pinStyle);
            if ($incomingValue === null) {
                continue;
            }

            DB::table('pin_data')->insert([
                'id' => Uuid::uuid7()->toString(),
                'pin_id' => $pinId,
                'value' => $incomingValue,
                'created_at' => $now,
            ]);
        }
    }
}
