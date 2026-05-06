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

    /**
     * @param array<int, array{pin:string, value:float|int}> $readings
     * @param array<string,string> $pinIdByName
     * @param array<string,string> $pinStyleByName
     */
    public function storeReadings(array $readings, array $pinIdByName, array $pinStyleByName = [], string $controllerId = ''): void
    {
        $now = now();
        $averageIntervalMinutes = max(1, (int) config('smarthome.pin_data_average_interval_minutes', 5));
        foreach ($readings as $reading) {
            $pinName = strtoupper(trim((string) $reading['pin']));
            $pinId = $pinIdByName[$pinName] ?? null;
            if (! $pinId) {
                continue;
            }

            $incomingValue = (float) $reading['value'];
            $pinStyle = strtolower((string) ($pinStyleByName[$pinName] ?? ''));
            $isPowerPin = ($pinStyle === 'power');

            if ($isPowerPin) {
                if ($controllerId !== '' && ! $this->planLimitService->canInsertPinDataForController($controllerId)) {
                    continue;
                }
                DB::table('pin_data')->insert([
                    'id' => Uuid::uuid7()->toString(),
                    'pin_id' => $pinId,
                    'value' => $incomingValue,
                    'created_at' => $now,
                ]);
                continue;
            }

            $lastRow = DB::table('pin_data')
                ->where('pin_id', $pinId)
                ->orderByDesc('created_at')
                ->first(['id', 'value', 'created_at']);

            if ($lastRow && $lastRow->created_at !== null) {
                $lastCreatedAt = \Illuminate\Support\Carbon::parse((string) $lastRow->created_at);
                if ($lastCreatedAt->copy()->addMinutes($averageIntervalMinutes)->gt($now)) {
                    $nextValue = (((float) $lastRow->value) + $incomingValue) / 2.0;

                    DB::table('pin_data')
                        ->where('id', (string) $lastRow->id)
                        ->update([
                            'value' => $nextValue,
                        ]);
                    continue;
                }
            }

            if ($controllerId !== '' && ! $this->planLimitService->canInsertPinDataForController($controllerId)) {
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
