<?php

declare(strict_types=1);

namespace App\Services\Report;

use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class PinDataHistoryService
{
    /**
     * @param array<int, array{pin:string, value:float|int}> $readings
     * @param array<string,string> $pinIdByName
     */
    public function storeReadings(array $readings, array $pinIdByName): void
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

            DB::table('pin_data')->insert([
                'id' => Uuid::uuid7()->toString(),
                'pin_id' => $pinId,
                'value' => $incomingValue,
                'created_at' => $now,
            ]);
        }
    }
}
