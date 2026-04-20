<?php

declare(strict_types=1);

namespace App\Services\Report;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScenarioDesiredValueService
{
    private const SYSTEM_CURRENT_TIME_PIN_ID = '0195f7e0-0000-7000-8000-000000000002';

    private function evaluateScenarioCondition(string $operator, ?float $sourceValue, float $threshold): bool
    {
        if ($sourceValue === null) {
            return false;
        }

        return match (strtolower(trim($operator))) {
            'gt' => $sourceValue > $threshold,
            'gte' => $sourceValue >= $threshold,
            'lt' => $sourceValue < $threshold,
            'lte' => $sourceValue <= $threshold,
            'eq' => abs($sourceValue - $threshold) < 0.000001,
            'ne' => abs($sourceValue - $threshold) >= 0.000001,
            default => false,
        };
    }

    private function resolveControllerTimeSeconds(string $controllerId): float
    {
        $timeZone = DB::table('controller_user as cu')
            ->join('users as u', 'u.id', '=', 'cu.user_id')
            ->where('cu.controller_id', $controllerId)
            ->orderByRaw("CASE WHEN cu.role = 'owner' THEN 0 ELSE 1 END")
            ->value('u.time_zone');

        $tz = is_string($timeZone) && in_array($timeZone, \DateTimeZone::listIdentifiers(), true)
            ? $timeZone
            : 'Europe/Moscow';

        $now = new \DateTimeImmutable('now', new \DateTimeZone($tz));
        return ((int) $now->format('H') * 3600) + ((int) $now->format('i') * 60) + (int) $now->format('s');
    }

    /**
     * @return Collection<int, object{id:string,controller_id:string,pin:string,desired_digital_value:int|null,enable_scenario:int, invert_digital_logic:int}>
     */
    public function findTargetRows(string $controllerId): Collection
    {
        return DB::table('pin')
            ->where('controller_id', $controllerId)
            ->where('digital_style', 'power')
            ->whereNotNull('desired_digital_value')
            ->select(['id', 'controller_id', 'pin', 'desired_digital_value', 'enable_scenario', 'invert_digital_logic'])
            ->get();
    }

    public function applyDesiredValue(string $controllerId): void
    {
        $rows = $this->findTargetRows($controllerId);
        if ($rows->count() === 0) {
            return;
        }

        $scenarioTargetRows = $rows
            ->filter(static fn ($row) => ((int) ($row->enable_scenario ?? 0)) > 0)
            ->values();
        if ($scenarioTargetRows->count() === 0) {
            return;
        }

        $firstControllerId = null;
        foreach ($rows as $row) {
            if (isset($row->controller_id)) {
                $firstControllerId = (string) $row->controller_id;
                break;
            }
        }
        if (! is_string($firstControllerId) || $firstControllerId === '') {
            return;
        }

        $sourcePinRows = DB::table('pin')
            ->where('controller_id', $firstControllerId)
            ->select(['id', 'value'])
            ->get();
        $sourceValueByPinId = [];
        foreach ($sourcePinRows as $sourceRow) {
            $sourceValueByPinId[(string) $sourceRow->id] = is_numeric($sourceRow->value) ? (float) $sourceRow->value : null;
        }

        $controllerCurrentTimeSeconds = $this->resolveControllerTimeSeconds($firstControllerId);

        $targetIds = [];
        foreach ($scenarioTargetRows as $row) {
            $targetIds[] = (string) $row->id;
        }

        $scenarioRows = DB::table('scenario')
            ->whereIn('pin_id', $targetIds)
            ->select(['id', 'pin_id'])
            ->get();

        $scenarioByTargetPinId = [];
        $scenarioIds = [];
        foreach ($scenarioRows as $scenario) {
            $scenarioId = (string) $scenario->id;
            $targetPinId = (string) $scenario->pin_id;
            $scenarioIds[] = $scenarioId;
            if (! isset($scenarioByTargetPinId[$targetPinId])) {
                $scenarioByTargetPinId[$targetPinId] = [];
            }
            $scenarioByTargetPinId[$targetPinId][] = $scenarioId;
        }

        $conditionsByScenarioId = [];
        if (count($scenarioIds) > 0) {
            $conditionRows = DB::table('scenario_condition')
                ->whereIn('scenario_id', $scenarioIds)
                ->select(['scenario_id', 'pin_id', 'operator', 'threshold'])
                ->get();
            foreach ($conditionRows as $condition) {
                $scenarioId = (string) $condition->scenario_id;
                if (! isset($conditionsByScenarioId[$scenarioId])) {
                    $conditionsByScenarioId[$scenarioId] = [];
                }
                $conditionsByScenarioId[$scenarioId][] = $condition;
            }
        }

        foreach ($scenarioTargetRows as $targetPinRow) {
            $targetPinId = (string) $targetPinRow->id;
            $targetScenarioIds = $scenarioByTargetPinId[$targetPinId] ?? [];
            if (count($targetScenarioIds) === 0) {
                continue;
            }

            $pinScenarioResult = false;
            foreach ($targetScenarioIds as $scenarioId) {
                $scenarioConditions = $conditionsByScenarioId[$scenarioId] ?? [];
                if (count($scenarioConditions) === 0) {
                    continue;
                }

                $scenarioTrue = true;
                foreach ($scenarioConditions as $condition) {
                    $sourcePinId = (string) $condition->pin_id;
                    $sourcePinValue = $sourceValueByPinId[$sourcePinId] ?? null;

                    if ($sourcePinId === self::SYSTEM_CURRENT_TIME_PIN_ID) {
                        $sourcePinValue = $controllerCurrentTimeSeconds;
                    }

                    $threshold = is_numeric($condition->threshold) ? (float) $condition->threshold : 0.0;
                    if (! $this->evaluateScenarioCondition((string) $condition->operator, $sourcePinValue, $threshold)) {
                        $scenarioTrue = false;
                        break;
                    }
                }

                if ($scenarioTrue) {
                    $pinScenarioResult = true;
                    break;
                }
            }

            DB::table('pin')
                ->where('id', $targetPinId)
                ->update([
                    'desired_digital_value' => $pinScenarioResult ? 1 : 0,
                    'desired_digital_updated_at' => now(),
                ]);
        }
    }
}

