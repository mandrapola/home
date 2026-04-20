<?php

declare(strict_types=1);

namespace App\Services\Report;

use Illuminate\Support\Facades\DB;

class PinValueSyncService
{
    /**
     * @param array<int, array{pin:string, value:float|int}> $readings
     * @param array<string,string> $pinIdByName
     * @param array<string,string> $pinStyleByName
     * @param array<string,bool> $pinInvertByName
     */
    public function syncFromReadings(
        array $readings,
        array $pinIdByName,
        array $pinStyleByName,
        array $pinInvertByName
    ): void {
        foreach ($readings as $reading) {
            $pinName = strtoupper(trim((string) $reading['pin']));
            $pinId = $pinIdByName[$pinName] ?? null;
            if (! $pinId) {
                continue;
            }

            $isPowerPin = (($pinStyleByName[$pinName] ?? '') === 'power');
            $isInvertedPin = (bool) ($pinInvertByName[$pinName] ?? false);
            $incomingValue = (float) $reading['value'];

            $updateData = [
                'value' => $incomingValue,
                'value_updated_at' => now(),
            ];

            if ($isPowerPin) {
                $wireValue = ((int) round($incomingValue)) > 0 ? 1 : 0;
                $logicalValue = $isInvertedPin ? (1 - $wireValue) : $wireValue;
                $current = DB::table('pin')
                    ->where('id', $pinId)
                    ->value('value');

                $currentLogicalValue = ((int) round((float) ($current ?? 0))) > 0 ? 1 : 0;
                if ($currentLogicalValue === $logicalValue) {
                    continue;
                }

                $updateData['value'] = $logicalValue;
            }

            DB::table('pin')
                ->where('id', $pinId)
                ->update($updateData);
        }
    }
}
