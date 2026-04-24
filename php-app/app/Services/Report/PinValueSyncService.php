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
     */
    public function syncFromReadings(
        array $readings,
        array $pinIdByName,
        array $pinStyleByName
    ): array {
        $changedPowerStates = [];

        foreach ($readings as $reading) {
            $pinName = strtoupper(trim((string) $reading['pin']));
            $pinId = $pinIdByName[$pinName] ?? null;
            if (! $pinId) {
                continue;
            }

            $isPowerPin = (($pinStyleByName[$pinName] ?? '') === 'power');
            $incomingValue = (float) $reading['value'];

            $updateData = [
                'value' => $incomingValue,
                'value_updated_at' => now(),
            ];

            if ($isPowerPin) {
                $logicalValue = ((int) round($incomingValue)) > 0 ? 1 : 0;
                $current = DB::table('pin')
                    ->where('id', $pinId)
                    ->value('value');

                $currentLogicalValue = ((int) round((float) ($current ?? 0))) > 0 ? 1 : 0;
                if ($currentLogicalValue === $logicalValue) {
                    continue;
                }

                $updateData['value'] = $logicalValue;
                $changedPowerStates[(string) $pinId] = ($logicalValue === 1);
            }

            DB::table('pin')
                ->where('id', $pinId)
                ->update($updateData);
        }

        return $changedPowerStates;
    }
}
