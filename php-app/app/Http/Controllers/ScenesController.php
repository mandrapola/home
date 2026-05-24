<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Billing\PlanLimitService;
use App\Services\Report\PinValueTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ScenesController extends Controller
{
    private const SYSTEM_CONTROLLER_ID = '0195f7e0-0000-7000-8000-000000000001';
    private const SYSTEM_CURRENT_TIME_PIN_ID = '0195f7e0-0000-7000-8000-000000000002';

    public function __construct(
        private readonly PinValueTransformer $pinValueTransformer,
        private readonly PlanLimitService $planLimitService,
    ) {
    }

    private function ensureSystemCurrentTimeSource(): void
    {
        $systemControllerExists = DB::table('controller')
            ->where('id', self::SYSTEM_CONTROLLER_ID)
            ->exists();

        if (! $systemControllerExists) {
            DB::table('controller')->insert([
                'id' => self::SYSTEM_CONTROLLER_ID,
                'name' => 'Системный контроллер',
                'discription' => 'Системные параметры',
                'send_interval_seconds' => 60,
                'status' => 'active',
                'last_seen_at' => now(),
                'claimed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $systemTimePinExists = DB::table('pin')
            ->where('id', self::SYSTEM_CURRENT_TIME_PIN_ID)
            ->exists();

        if (! $systemTimePinExists) {
            DB::table('pin')->insert([
                'id' => self::SYSTEM_CURRENT_TIME_PIN_ID,
                'controller_id' => self::SYSTEM_CONTROLLER_ID,
                'pin' => 'CURRENT_TIME',
                'label' => 'Текущее время',
                'unit' => null,
                'digital_style' => 'sensor',
                'value' => null,
                'value_updated_at' => null,
                'desired_digital_value' => null,
                'desired_digital_updated_at' => null,
                'show_on_chart' => 0,
                'is_monitored' => 0,
                'chart_range_hours' => 1,
                'enable_scenario' => 1,
            ]);
        }
    }

    private function sourcePinAvailableForUser(string|int $userId, string $pinId): bool
    {
        if ($pinId === self::SYSTEM_CURRENT_TIME_PIN_ID) {
            return true;
        }

        return DB::table('pin as p')
            ->join('controller as c', 'c.id', '=', 'p.controller_id')
            ->where('p.id', $pinId)
            ->where('c.user_id', (int) $userId)
            ->exists();
    }

    public function index()
    {
        return view('scenes');
    }

    private function evaluateCondition(string $operator, ?float $sourceValue, float $threshold): bool
    {
        if ($sourceValue === null) {
            return false;
        }

        return match (strtolower($operator)) {
            'gt' => $sourceValue > $threshold,
            'gte' => $sourceValue >= $threshold,
            'lt' => $sourceValue < $threshold,
            'lte' => $sourceValue <= $threshold,
            'eq' => abs($sourceValue - $threshold) < 0.000001,
            'ne' => abs($sourceValue - $threshold) >= 0.000001,
            default => false,
        };
    }

    private function normalizeOperator(string $operator): string
    {
        $op = strtolower(trim($operator));
        return in_array($op, ['gt', 'gte', 'lt', 'lte', 'eq', 'ne'], true) ? $op : 'gt';
    }

    private function resolveControllerTimeSecondsForUser(string $timeZone): float
    {
        $tz = in_array($timeZone, \DateTimeZone::listIdentifiers(), true) ? $timeZone : 'Europe/Moscow';
        $now = Carbon::now($tz);
        return ((int) $now->format('H') * 3600) + ((int) $now->format('i') * 60) + (int) $now->format('s');
    }

    private function recalculateScenarioDesiredValue(string $targetPinId, string $timeZone): int
    {
        $scenarioRows = DB::table('scenario')
            ->where('pin_id', $targetPinId)
            ->select(['id'])
            ->get();

        if ($scenarioRows->isEmpty()) {
            return 0;
        }

        $scenarioIds = $scenarioRows->pluck('id')->map(fn ($id) => (string) $id)->all();
        $conditionRows = DB::table('scenario_condition')
            ->whereIn('scenario_id', $scenarioIds)
            ->select(['scenario_id', 'pin_id', 'operator', 'threshold'])
            ->get();

        if ($conditionRows->isEmpty()) {
            return 0;
        }

        $conditionsByScenarioId = [];
        $sourcePinIds = [];
        foreach ($conditionRows as $condition) {
            $scenarioId = (string) $condition->scenario_id;
            $conditionsByScenarioId[$scenarioId] ??= [];
            $conditionsByScenarioId[$scenarioId][] = $condition;
            $sourcePinIds[] = (string) $condition->pin_id;
        }

        $sourceRows = DB::table('pin')
            ->whereIn('id', array_values(array_unique($sourcePinIds)))
            ->get($this->pinSelectColumnsForTransform());
        $sourceValues = [];
        foreach ($sourceRows as $sourceRow) {
            $sourcePinId = (string) $sourceRow->id;
            $rawValue = is_numeric($sourceRow->value ?? null) ? (float) $sourceRow->value : null;
            $sourceValues[$sourcePinId] = $this->pinValueTransformer->transform($sourceRow, $rawValue);
        }

        $controllerCurrentTimeSeconds = $this->resolveControllerTimeSecondsForUser($timeZone);

        foreach ($scenarioIds as $scenarioId) {
            $scenarioConditions = $conditionsByScenarioId[$scenarioId] ?? [];
            if (count($scenarioConditions) === 0) {
                continue;
            }

            $scenarioTrue = true;
            foreach ($scenarioConditions as $condition) {
                $sourcePinId = (string) $condition->pin_id;
                $sourceValue = $sourcePinId === self::SYSTEM_CURRENT_TIME_PIN_ID
                    ? $controllerCurrentTimeSeconds
                    : (is_numeric($sourceValues[$sourcePinId] ?? null) ? (float) $sourceValues[$sourcePinId] : null);

                $threshold = is_numeric($condition->threshold) ? (float) $condition->threshold : 0.0;
                if (! $this->evaluateCondition((string) $condition->operator, $sourceValue, $threshold)) {
                    $scenarioTrue = false;
                    break;
                }
            }

            if ($scenarioTrue) {
                return 1;
            }
        }

        return 0;
    }

    private function userOwnsController(string|int $userId, string $controllerId): bool
    {
        return DB::table('controller')
            ->where('user_id', (int) $userId)
            ->where('id', $controllerId)
            ->exists();
    }

    public function data(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $this->ensureSystemCurrentTimeSource();

        $timeZone = (string) ($user->time_zone ?: 'Europe/Moscow');
        $now = Carbon::now($timeZone);
        $currentSeconds = ((int) $now->format('H') * 3600) + ((int) $now->format('i') * 60) + (int) $now->format('s');

        $targets = DB::table('pin as p')
            ->join('controller as c', 'c.id', '=', 'p.controller_id')
            ->where('c.user_id', (int) $user->id)
            ->where('p.digital_style', 'power')
            ->select([
                'p.id',
                'p.pin',
                'p.label',
                'p.enable_scenario',
                'c.id as controller_id',
                'c.name as controller_name',
            ])
            ->orderBy('c.name')
            ->orderBy('p.pin')
            ->get();

        $pins = DB::table('pin as p')
            ->join('controller as c', 'c.id', '=', 'p.controller_id')
            ->where('c.user_id', (int) $user->id)
            ->select($this->pinSelectColumnsForTransformWithAliases([
                'p.id',
                'p.pin',
                'p.label',
                'p.digital_style',
                'p.value',
                'p.unit',
                'c.id as controller_id',
                'c.name as controller_name',
            ]))
            ->orderBy('c.name')
            ->orderBy('p.pin')
            ->get();

        $systemPins = DB::table('pin as p')
            ->join('controller as c', 'c.id', '=', 'p.controller_id')
            ->where('p.id', self::SYSTEM_CURRENT_TIME_PIN_ID)
            ->select($this->pinSelectColumnsForTransformWithAliases([
                'p.id',
                'p.pin',
                'p.label',
                'p.digital_style',
                'p.value',
                'p.unit',
                'c.id as controller_id',
                'c.name as controller_name',
            ]))
            ->get();

        $pins = $pins
            ->concat($systemPins)
            ->sortBy([
                ['controller_name', 'asc'],
                ['pin', 'asc'],
            ])
            ->map(function ($pin) {
                if ((string) $pin->id === self::SYSTEM_CURRENT_TIME_PIN_ID) {
                    return $pin;
                }

                $rawValue = is_numeric($pin->value ?? null) ? (float) $pin->value : null;
                $pin->value = $this->pinValueTransformer->transform($pin, $rawValue);
                $pin->unit = $this->pinValueTransformer->resolveUnit($pin);

                return $pin;
            })
            ->values();

        $definitions = DB::table('scenario as s')
            ->join('pin as p_target', 'p_target.id', '=', 's.pin_id')
            ->join('controller as c', 'c.id', '=', 'p_target.controller_id')
            ->where('c.user_id', (int) $user->id)
            ->select([
                's.id',
                's.name',
                's.pin_id',
                's.created_at',
                's.updated_at',
                'p_target.pin as target_pin',
                'p_target.label as target_pin_label',
                'p_target.enable_scenario as scenario_enabled',
                'c.id as controller_id',
                'c.name as controller_name',
            ])
            ->orderBy('c.name')
            ->orderBy('p_target.pin')
            ->orderBy('s.name')
            ->get();

        $conditions = DB::table('scenario_condition as sc')
            ->join('scenario as s', 's.id', '=', 'sc.scenario_id')
            ->join('pin as p_target', 'p_target.id', '=', 's.pin_id')
            ->join('controller as c', 'c.id', '=', 'p_target.controller_id')
            ->join('pin as p_source', 'p_source.id', '=', 'sc.pin_id')
            ->where('c.user_id', (int) $user->id)
            ->select($this->pinSelectColumnsForConditions([
                'sc.id',
                'sc.scenario_id',
                'sc.operator',
                'sc.threshold',
                'sc.pin_id as source_pin_id',
                'p_source.pin as source_pin',
                'p_source.label as source_pin_label',
                'p_source.value as source_value',
                'p_source.digital_style as digital_style',
                'p_source.unit as unit',
                'p_target.pin as target_pin',
                'p_target.label as target_pin_label',
                'p_target.enable_scenario as scenario_enabled',
                's.name',
                'c.id as controller_id',
                'c.name as controller_name',
            ]))
            ->orderBy('c.name')
            ->orderBy('p_target.pin')
            ->orderBy('s.name')
            ->orderBy('sc.id')
            ->get()
            ->map(function ($row) use ($currentSeconds) {
                $sourcePin = strtoupper((string) $row->source_pin);
                $sourceValue = $sourcePin === 'CURRENT_TIME'
                    ? (float) $currentSeconds
                    : $this->pinValueTransformer->transform($row, is_numeric($row->source_value) ? (float) $row->source_value : null);

                $threshold = is_numeric($row->threshold) ? (float) $row->threshold : 0.0;
                $isTrue = $this->evaluateCondition((string) $row->operator, $sourceValue, $threshold);
                $row->current_state = $isTrue ? 1 : 0;

                return $row;
            });

        return response()->json([
            'targets' => $targets,
            'pins' => $pins,
            'scenario_definitions' => $definitions,
            'conditions' => $conditions,
            'time_zone' => $timeZone,
            'server_time' => $now->toIso8601String(),
        ]);
    }

    /**
     * @return array<int,string>
     */
    private function pinSelectColumnsForTransform(): array
    {
        $columns = ['id', 'value', 'digital_style', 'unit'];
        if ($this->hasMoistureColumns()) {
            $columns[] = 'moisture_raw_dry';
            $columns[] = 'moisture_raw_wet';
            $columns[] = 'moisture_show_percent';
        }

        return $columns;
    }

    /**
     * @param array<int,string> $baseColumns
     * @return array<int,string>
     */
    private function pinSelectColumnsForTransformWithAliases(array $baseColumns): array
    {
        $columns = $baseColumns;
        if ($this->hasMoistureColumns()) {
            $columns[] = 'p.moisture_raw_dry';
            $columns[] = 'p.moisture_raw_wet';
            $columns[] = 'p.moisture_show_percent';
        }

        return $columns;
    }

    /**
     * @param array<int,string> $baseColumns
     * @return array<int,string>
     */
    private function pinSelectColumnsForConditions(array $baseColumns): array
    {
        $columns = $baseColumns;
        if ($this->hasMoistureColumns()) {
            $columns[] = 'p_source.moisture_raw_dry as moisture_raw_dry';
            $columns[] = 'p_source.moisture_raw_wet as moisture_raw_wet';
            $columns[] = 'p_source.moisture_show_percent as moisture_show_percent';
        }

        return $columns;
    }

    private function hasMoistureColumns(): bool
    {
        return Schema::hasColumn('pin', 'moisture_raw_dry')
            && Schema::hasColumn('pin', 'moisture_raw_wet')
            && Schema::hasColumn('pin', 'moisture_show_percent');
    }

    public function storeDefinition(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $validated = $request->validate([
            'pin_id' => ['required', 'string', 'size:36'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $targetPin = DB::table('pin')
            ->where('id', (string) $validated['pin_id'])
            ->first(['id', 'controller_id', 'pin', 'digital_style']);

        if (! $targetPin) {
            return response()->json(['error' => 'not_found', 'message' => 'Target pin not found'], 404);
        }
        if ((string) $targetPin->digital_style !== 'power') {
            return response()->json(['error' => 'validation_error', 'message' => 'Target pin must be power type'], 422);
        }
        if (! $this->userOwnsController((string) $user->id, (string) $targetPin->controller_id)) {
            return response()->json(['error' => 'forbidden', 'message' => 'Target pin is not available'], 403);
        }

        $name = trim((string) $validated['name']);
        $exists = DB::table('scenario')
            ->where('pin_id', (string) $targetPin->id)
            ->where('name', $name)
            ->exists();
        if ($exists) {
            return response()->json(['error' => 'validation_error', 'message' => 'Scenario with this name already exists for pin'], 422);
        }

        $allowance = $this->planLimitService->scenarioCreateAllowance($user);
        if (! $allowance['allowed']) {
            return response()->json([
                'error' => 'plan_limit_exceeded',
                'message' => __('Scenario limit reached for effective plan.'),
                'used' => $allowance['used'],
                'max' => $allowance['max'],
            ], 409);
        }

        $id = (string) \Ramsey\Uuid\Uuid::uuid7();
        DB::table('scenario')->insert([
            'id' => $id,
            'pin_id' => (string) $targetPin->id,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'scenario_definition' => [
                'id' => $id,
                'pin_id' => (string) $targetPin->id,
                'name' => $name,
            ],
        ], 201);
    }

    public function updateDefinition(Request $request, string $definitionId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $definition = DB::table('scenario as s')
            ->join('pin as p', 'p.id', '=', 's.pin_id')
            ->where('s.id', $definitionId)
            ->select(['s.id', 's.pin_id', 's.name', 'p.controller_id'])
            ->first();

        if (! $definition) {
            return response()->json(['error' => 'not_found', 'message' => 'Scenario definition not found'], 404);
        }
        if (! $this->userOwnsController((string) $user->id, (string) $definition->controller_id)) {
            return response()->json(['error' => 'forbidden', 'message' => 'Scenario definition is not available'], 403);
        }

        $name = trim((string) $validated['name']);
        $exists = DB::table('scenario')
            ->where('pin_id', (string) $definition->pin_id)
            ->where('name', $name)
            ->where('id', '!=', $definitionId)
            ->exists();
        if ($exists) {
            return response()->json(['error' => 'validation_error', 'message' => 'Scenario with this name already exists for pin'], 422);
        }

        DB::table('scenario')
            ->where('id', $definitionId)
            ->update([
                'name' => $name,
                'updated_at' => now(),
            ]);

        return response()->json([
            'ok' => true,
            'scenario_definition' => [
                'id' => $definitionId,
                'pin_id' => (string) $definition->pin_id,
                'name' => $name,
            ],
        ]);
    }

    public function deleteDefinition(Request $request, string $definitionId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $definition = DB::table('scenario as s')
            ->join('pin as p', 'p.id', '=', 's.pin_id')
            ->where('s.id', $definitionId)
            ->select(['s.id', 'p.controller_id'])
            ->first();

        if (! $definition) {
            return response()->json(['error' => 'not_found', 'message' => 'Scenario definition not found'], 404);
        }
        if (! $this->userOwnsController((string) $user->id, (string) $definition->controller_id)) {
            return response()->json(['error' => 'forbidden', 'message' => 'Scenario definition is not available'], 403);
        }

        DB::table('scenario')->where('id', $definitionId)->delete();
        return response()->json(['ok' => true]);
    }

    public function storeCondition(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $validated = $request->validate([
            'scenario_id' => ['required', 'string', 'size:36'],
            'pin_id' => ['required', 'string', 'size:36'],
            'operator' => ['required', 'string', 'max:8'],
            'threshold' => ['required', 'numeric'],
        ]);

        $definition = DB::table('scenario as s')
            ->join('pin as p', 'p.id', '=', 's.pin_id')
            ->where('s.id', (string) $validated['scenario_id'])
            ->select(['s.id', 's.pin_id as target_pin_id', 'p.controller_id'])
            ->first();
        if (! $definition) {
            return response()->json(['error' => 'not_found', 'message' => 'Scenario definition not found'], 404);
        }
        if (! $this->userOwnsController((string) $user->id, (string) $definition->controller_id)) {
            return response()->json(['error' => 'forbidden', 'message' => 'Scenario definition is not available'], 403);
        }

        $sourcePin = DB::table('pin')
            ->where('id', (string) $validated['pin_id'])
            ->first(['id', 'controller_id']);
        if (! $sourcePin) {
            return response()->json(['error' => 'not_found', 'message' => 'Source pin not found'], 404);
        }
        if (! $this->sourcePinAvailableForUser((string) $user->id, (string) $sourcePin->id)) {
            return response()->json(['error' => 'forbidden', 'message' => 'Source pin is not available'], 403);
        }

        $allowance = $this->planLimitService->scenarioConditionCreateAllowance($user);
        if (! $allowance['allowed']) {
            return response()->json([
                'error' => 'plan_limit_exceeded',
                'message' => __('Scenario condition limit reached for effective plan.'),
                'used' => $allowance['used'],
                'max' => $allowance['max'],
            ], 409);
        }

        $id = (string) \Ramsey\Uuid\Uuid::uuid7();
        DB::table('scenario_condition')->insert([
            'id' => $id,
            'scenario_id' => (string) $definition->id,
            'pin_id' => (string) $sourcePin->id,
            'operator' => $this->normalizeOperator((string) $validated['operator']),
            'threshold' => (float) $validated['threshold'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'condition_id' => $id], 201);
    }

    public function updateCondition(Request $request, string $conditionId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $validated = $request->validate([
            'pin_id' => ['required', 'string', 'size:36'],
            'operator' => ['required', 'string', 'max:8'],
            'threshold' => ['required', 'numeric'],
        ]);

        $condition = DB::table('scenario_condition as sc')
            ->join('scenario as s', 's.id', '=', 'sc.scenario_id')
            ->join('pin as p_target', 'p_target.id', '=', 's.pin_id')
            ->where('sc.id', $conditionId)
            ->select(['sc.id', 'sc.scenario_id', 'p_target.controller_id'])
            ->first();
        if (! $condition) {
            return response()->json(['error' => 'not_found', 'message' => 'Condition not found'], 404);
        }
        if (! $this->userOwnsController((string) $user->id, (string) $condition->controller_id)) {
            return response()->json(['error' => 'forbidden', 'message' => 'Condition is not available'], 403);
        }

        $sourcePin = DB::table('pin')
            ->where('id', (string) $validated['pin_id'])
            ->first(['id', 'controller_id']);
        if (! $sourcePin) {
            return response()->json(['error' => 'not_found', 'message' => 'Source pin not found'], 404);
        }
        if (! $this->sourcePinAvailableForUser((string) $user->id, (string) $sourcePin->id)) {
            return response()->json(['error' => 'forbidden', 'message' => 'Source pin is not available'], 403);
        }

        DB::table('scenario_condition')
            ->where('id', $conditionId)
            ->update([
                'pin_id' => (string) $sourcePin->id,
                'operator' => $this->normalizeOperator((string) $validated['operator']),
                'threshold' => (float) $validated['threshold'],
                'updated_at' => now(),
            ]);

        return response()->json(['ok' => true]);
    }

    public function deleteCondition(Request $request, string $conditionId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $condition = DB::table('scenario_condition as sc')
            ->join('scenario as s', 's.id', '=', 'sc.scenario_id')
            ->join('pin as p_target', 'p_target.id', '=', 's.pin_id')
            ->where('sc.id', $conditionId)
            ->select(['sc.id', 'p_target.controller_id'])
            ->first();
        if (! $condition) {
            return response()->json(['error' => 'not_found', 'message' => 'Condition not found'], 404);
        }
        if (! $this->userOwnsController((string) $user->id, (string) $condition->controller_id)) {
            return response()->json(['error' => 'forbidden', 'message' => 'Condition is not available'], 403);
        }

        DB::table('scenario_condition')->where('id', $conditionId)->delete();
        return response()->json(['ok' => true]);
    }

    public function setTargetScenarioEnabled(Request $request, string $pinId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $target = DB::table('pin')
            ->where('id', $pinId)
            ->first(['id', 'controller_id', 'digital_style']);
        if (! $target) {
            return response()->json(['error' => 'not_found', 'message' => 'Target pin not found'], 404);
        }
        if ((string) $target->digital_style !== 'power') {
            return response()->json(['error' => 'validation_error', 'message' => 'Target pin must be power type'], 422);
        }
        if (! $this->userOwnsController((string) $user->id, (string) $target->controller_id)) {
            return response()->json(['error' => 'forbidden', 'message' => 'Target pin is not available'], 403);
        }

        $enabled = ! empty($validated['enabled']) ? 1 : 0;
        $updates = ['enable_scenario' => $enabled];

        if ($enabled === 1) {
            $timeZone = (string) ($user->time_zone ?: 'Europe/Moscow');
            $desired = $this->recalculateScenarioDesiredValue((string) $pinId, $timeZone);
            $updates['desired_digital_value'] = $desired;
            $updates['desired_digital_updated_at'] = now();
        }

        DB::table('pin')
            ->where('id', $pinId)
            ->update($updates);

        $pin = DB::table('pin')
            ->where('id', $pinId)
            ->first(['id', 'enable_scenario', 'desired_digital_value', 'desired_digital_updated_at']);

        return response()->json([
            'ok' => true,
            'enabled' => $enabled === 1,
            'pin' => $pin,
        ]);
    }
}
