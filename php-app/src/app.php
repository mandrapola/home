<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function normalize_report_readings(array $payload): array
{
    if (isset($payload['readings']) && is_array($payload['readings']) && count($payload['readings']) > 0) {
        $rows = [];
        foreach ($payload['readings'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $pin = normalize_pin((string)($item['pin'] ?? ''));
            $value = to_number($item['value'] ?? null);
            if ($pin !== '' && $value !== null) {
                $rows[] = ['pin' => $pin, 'value' => $value];
            }
        }
        return $rows;
    }

    $rows = [];
    $legacyMap = [
        'thermometer' => $payload['thermometer'] ?? $payload['temperature'] ?? null,
        'pressure' => $payload['pressure'] ?? null,
        'humidity' => $payload['humidity'] ?? null,
    ];

    foreach ($legacyMap as $pin => $raw) {
        $value = to_number($raw);
        if ($value !== null) {
            $rows[] = ['pin' => $pin, 'value' => $value];
        }
    }

    return $rows;
}

function default_pin_config(string $pin, int $sortOrder): array
{
    $isDigital = is_digital_pin($pin);
    $isAnalog = is_analog_pin($pin);

    return [
        'pin' => $pin,
        'label' => $isDigital ? ('Цифровой порт ' . $pin) : ($isAnalog ? (strtoupper($pin) === 'AIR_TEMPERATURE' ? 'Температура воздуха' : (strtoupper($pin) === 'AIR_HUMIDITY' ? 'Влажность воздуха' : ('Аналоговый порт ' . strtoupper($pin)))) : $pin),
        'unit' => strtoupper($pin) === 'AIR_TEMPERATURE' ? '°C' : (strtoupper($pin) === 'AIR_HUMIDITY' ? '%' : ($isAnalog ? 'ADC' : null)),
        'multiplier' => 1,
        'value_offset' => 0,
        'precision_value' => $isAnalog ? 1 : 0,
        'average_interval_minutes' => 5,
        'value_labels' => $isDigital ? ['0' => 'Выключен', '1' => 'Включен'] : new stdClass(),
        'digital_style' => $isDigital ? 'power' : 'sensor',
        'invert_digital_logic' => 0,
        'desired_digital_value' => $isDigital ? 0 : null,
        'power_on_duration_seconds' => null,
        'show_on_dashboard' => 1,
        'show_on_chart' => $isAnalog ? 1 : 0,
        'chart_range_hours' => $isAnalog ? 24 : 1,
        'sort_order' => $sortOrder,
    ];
}

function ensure_controller_exists(PDO $pdo, int $controllerId, string $ip): array
{
    $stmt = $pdo->prepare('SELECT id, name, discription, send_interval_seconds FROM controllers WHERE id = :id');
    $stmt->execute(['id' => $controllerId]);
    $controller = $stmt->fetch();
    if ($controller) {
        return $controller;
    }

    $insert = $pdo->prepare(
        'INSERT INTO controllers (id, name, discription, send_interval_seconds, time_zone)
         VALUES (:id, :name, :discription, 5, :tz)
         ON DUPLICATE KEY UPDATE id = id'
    );
    $insert->execute([
        'id' => $controllerId,
        'name' => 'controller-' . $controllerId,
        'discription' => 'Auto-registered controller from ' . $ip,
        'tz' => 'Europe/Moscow',
    ]);

    $stmt->execute(['id' => $controllerId]);
    return (array)$stmt->fetch();
}

function ensure_pin_configs(PDO $pdo, int $controllerId, array $pins): void
{
    if (count($pins) === 0) {
        return;
    }

    $stmt = $pdo->prepare('SELECT pin FROM controller_pin_config WHERE controller_id = :controller_id');
    $stmt->execute(['controller_id' => $controllerId]);
    $existing = [];
    foreach ($stmt->fetchAll() as $row) {
        $existing[normalize_pin((string)$row['pin'])] = true;
    }

    $sort = count($existing);
    $insert = $pdo->prepare(
        'INSERT INTO controller_pin_config (
          controller_id, pin, label, unit, multiplier, value_offset, precision_value, average_interval_minutes,
          value_labels, digital_style, invert_digital_logic, desired_digital_value, desired_digital_updated_at,
          power_on_duration_seconds, show_on_dashboard, show_on_chart, chart_range_hours, sort_order
        ) VALUES (
          :controller_id, :pin, :label, :unit, :multiplier, :value_offset, :precision_value, :average_interval_minutes,
          :value_labels, :digital_style, :invert_digital_logic, :desired_digital_value, NULL,
          :power_on_duration_seconds, :show_on_dashboard, :show_on_chart, :chart_range_hours, :sort_order
        ) ON DUPLICATE KEY UPDATE pin = pin'
    );

    foreach ($pins as $pin) {
        $normalized = normalize_pin((string)$pin);
        if ($normalized === '' || isset($existing[$normalized])) {
            continue;
        }

        $cfg = default_pin_config($normalized, $sort++);
        $insert->execute([
            'controller_id' => $controllerId,
            'pin' => $cfg['pin'],
            'label' => $cfg['label'],
            'unit' => $cfg['unit'],
            'multiplier' => $cfg['multiplier'],
            'value_offset' => $cfg['value_offset'],
            'precision_value' => $cfg['precision_value'],
            'average_interval_minutes' => $cfg['average_interval_minutes'],
            'value_labels' => json_encode($cfg['value_labels'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'digital_style' => $cfg['digital_style'],
            'invert_digital_logic' => $cfg['invert_digital_logic'],
            'desired_digital_value' => $cfg['desired_digital_value'],
            'power_on_duration_seconds' => $cfg['power_on_duration_seconds'],
            'show_on_dashboard' => $cfg['show_on_dashboard'],
            'show_on_chart' => $cfg['show_on_chart'],
            'chart_range_hours' => $cfg['chart_range_hours'],
            'sort_order' => $cfg['sort_order'],
        ]);
    }
}

function decode_value_labels(mixed $value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? array_map('strval', $decoded) : [];
    }
    if (is_array($value)) {
        return array_map('strval', $value);
    }
    return [];
}

function fetch_pin_configs(PDO $pdo, int $controllerId): array
{
    $stmt = $pdo->prepare(
        'SELECT pin, label, unit, multiplier, value_offset, precision_value, average_interval_minutes, value_labels,
                digital_style, invert_digital_logic, desired_digital_value, desired_digital_updated_at,
                power_on_duration_seconds, show_on_dashboard, show_on_chart, chart_range_hours, sort_order
         FROM controller_pin_config
         WHERE controller_id = :controller_id
         ORDER BY sort_order ASC, pin ASC'
    );
    $stmt->execute(['controller_id' => $controllerId]);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'pin' => normalize_pin((string)$row['pin']),
            'label' => (string)$row['label'],
            'unit' => $row['unit'] !== null ? (string)$row['unit'] : null,
            'multiplier' => (float)$row['multiplier'],
            'offset' => (float)$row['value_offset'],
            'precision' => (int)$row['precision_value'],
            'average_interval_minutes' => (int)$row['average_interval_minutes'],
            'value_labels' => decode_value_labels($row['value_labels']),
            'digital_style' => (string)$row['digital_style'],
            'invert_digital_logic' => (bool)$row['invert_digital_logic'],
            'desired_digital_value' => $row['desired_digital_value'] === null ? null : (int)$row['desired_digital_value'],
            'desired_digital_updated_at' => $row['desired_digital_updated_at'],
            'power_on_duration_seconds' => $row['power_on_duration_seconds'] === null ? null : (int)$row['power_on_duration_seconds'],
            'show_on_dashboard' => (bool)$row['show_on_dashboard'],
            'show_on_chart' => (bool)$row['show_on_chart'],
            'chart_range_hours' => (int)$row['chart_range_hours'],
            'sort_order' => (int)$row['sort_order'],
        ];
    }

    return $rows;
}

function format_reading(array $row, array $configByPin): array
{
    $pin = normalize_pin((string)$row['pin']);
    $raw = (float)$row['value'];
    $cfg = $configByPin[$pin] ?? default_pin_config($pin, 9999);

    $display = $raw * (float)$cfg['multiplier'] + (float)$cfg['offset'];
    $display = round($display, max(0, (int)$cfg['precision']));

    if (is_digital_pin($pin)) {
        $rawBit = $raw > 0 ? 1 : 0;
        $state = $rawBit;
        if ((bool)$cfg['invert_digital_logic'] && ($cfg['digital_style'] ?? 'power') === 'power') {
            $state = $rawBit > 0 ? 0 : 1;
        }
        $display = $state;
        $labels = is_array($cfg['value_labels']) ? $cfg['value_labels'] : [];
        $displayText = $labels[(string)$state] ?? ($state > 0 ? 'Включен' : 'Выключен');
    } else {
        $displayText = (string)$display;
    }

    return [
        'id' => (int)$row['id'],
        'pin' => $pin,
        'value' => $raw,
        'raw_value' => $raw,
        'display_value' => $display,
        'display_text' => $displayText,
        'label' => (string)$cfg['label'],
        'unit' => $cfg['unit'],
        'digital_style' => (string)$cfg['digital_style'],
        'invert_digital_logic' => (bool)$cfg['invert_digital_logic'],
        'desired_digital_value' => $cfg['desired_digital_value'],
        'desired_digital_updated_at' => $cfg['desired_digital_updated_at'],
        'power_on_duration_seconds' => $cfg['power_on_duration_seconds'],
        'show_on_dashboard' => (bool)$cfg['show_on_dashboard'],
        'show_on_chart' => (bool)$cfg['show_on_chart'],
        'chart_range_hours' => (int)$cfg['chart_range_hours'],
        'average_interval_minutes' => (int)$cfg['average_interval_minutes'],
        'controller_id' => (int)$row['controller_id'],
        'created_at' => $row['created_at'],
        'sort_order' => (int)$cfg['sort_order'],
    ];
}

function handle_report(array $payload): void
{
    $controllerId = (int)($payload['controller_id'] ?? 0);
    if ($controllerId <= 0) {
        json_response(['error' => 'invalid_controller_id', 'message' => 'controller_id must be a positive integer'], 400);
    }

    $readings = normalize_report_readings($payload);
    if (count($readings) === 0) {
        json_response(['error' => 'empty_readings', 'message' => 'No valid sensor readings provided'], 400);
    }

    $ip = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $controller = ensure_controller_exists($pdo, $controllerId, $ip);
        ensure_pin_configs($pdo, $controllerId, array_column($readings, 'pin'));

        $insert = $pdo->prepare('INSERT INTO controller_data (pin, value, controller_id, created_at) VALUES (:pin, :value, :controller_id, NOW())');
        foreach ($readings as $reading) {
            $insert->execute([
                'pin' => normalize_pin($reading['pin']),
                'value' => $reading['value'],
                'controller_id' => $controllerId,
            ]);
        }

        apply_controller_scenarios($pdo, $controllerId, $readings);

        $timeoutStmt = $pdo->prepare(
            'UPDATE controller_pin_config
             SET desired_digital_value = 0, desired_digital_updated_at = NULL
             WHERE controller_id = :controller_id
               AND digital_style = "power"
               AND desired_digital_value = 1
               AND power_on_duration_seconds IS NOT NULL
               AND desired_digital_updated_at IS NOT NULL
               AND DATE_ADD(desired_digital_updated_at, INTERVAL power_on_duration_seconds SECOND) <= NOW()'
        );
        $timeoutStmt->execute(['controller_id' => $controllerId]);

        $outputsStmt = $pdo->prepare(
            'SELECT pin,
                    CASE
                      WHEN COALESCE(invert_digital_logic, 0) = 1 THEN CASE WHEN desired_digital_value > 0 THEN 0 ELSE 1 END
                      ELSE CASE WHEN desired_digital_value > 0 THEN 1 ELSE 0 END
                    END AS value
             FROM controller_pin_config
             WHERE controller_id = :controller_id
               AND digital_style = "power"
               AND desired_digital_value IS NOT NULL
               AND pin REGEXP "^D[0-9]+$"
             ORDER BY sort_order ASC, pin ASC'
        );
        $outputsStmt->execute(['controller_id' => $controllerId]);

        $outputs = [];
        foreach ($outputsStmt->fetchAll() as $row) {
            $outputs[normalize_pin((string)$row['pin'])] = ((int)$row['value']) > 0 ? 1 : 0;
        }

        $pdo->commit();

        json_response([
            'send_interval_seconds' => max(1, (int)($controller['send_interval_seconds'] ?? 5)),
            'digital_outputs' => $outputs,
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        json_response(['error' => 'db_error', 'message' => $e->getMessage()], 500);
    }
}

function normalize_scenario_operator(mixed $value): string
{
    $operator = strtolower(trim((string)$value));
    return in_array($operator, ['gt', 'gte', 'lt', 'lte'], true) ? $operator : 'gt';
}

function to_bit(mixed $value, int $fallback = 0): int
{
    if ($value === null) {
        return $fallback > 0 ? 1 : 0;
    }
    return ((int)$value) > 0 ? 1 : 0;
}

function evaluate_scenario_active(string $operator, float $sourceValue, float $threshold, float $hysteresis, bool $wasActive): bool
{
    $h = max(0.0, $hysteresis);
    return match ($operator) {
        'gt' => $wasActive ? $sourceValue > ($threshold - $h) : $sourceValue > ($threshold + $h),
        'gte' => $sourceValue >= $threshold,
        'lt' => $wasActive ? $sourceValue < ($threshold + $h) : $sourceValue < ($threshold - $h),
        'lte' => $sourceValue <= $threshold,
        default => false,
    };
}

function apply_controller_scenarios(PDO $pdo, int $controllerId, array $incomingReadings): void
{
    $scenariosStmt = $pdo->prepare(
        'SELECT s.id, s.name, s.source_pin, s.operator, s.threshold, s.hysteresis, s.target_pin,
                s.value_when_true, s.value_when_false, s.priority, s.enabled,
                COALESCE(st.is_active, 0) AS current_state
         FROM controller_scenarios s
         LEFT JOIN controller_scenario_state st ON st.scenario_id = s.id
         WHERE s.controller_id = :controller_id
         ORDER BY s.priority ASC, s.id ASC'
    );
    $scenariosStmt->execute(['controller_id' => $controllerId]);
    $scenarios = $scenariosStmt->fetchAll();
    if (!$scenarios) {
        return;
    }

    $readingMap = [];
    foreach ($incomingReadings as $reading) {
        $pin = normalize_pin((string)($reading['pin'] ?? ''));
        $value = to_number($reading['value'] ?? null);
        if ($pin !== '' && $value !== null) {
            $readingMap[$pin] = $value;
        }
    }

    $latestStmt = $pdo->prepare(
        'SELECT pin, value
         FROM (
           SELECT pin, value, ROW_NUMBER() OVER (PARTITION BY pin ORDER BY created_at DESC, id DESC) AS rn
           FROM controller_data
           WHERE controller_id = :controller_id
         ) ranked
         WHERE rn = 1'
    );
    $latestStmt->execute(['controller_id' => $controllerId]);
    foreach ($latestStmt->fetchAll() as $row) {
        $pin = normalize_pin((string)$row['pin']);
        if (!isset($readingMap[$pin])) {
            $readingMap[$pin] = (float)$row['value'];
        }
    }

    $invertStmt = $pdo->prepare(
        'SELECT pin, COALESCE(invert_digital_logic, 0) AS invert_digital_logic
         FROM controller_pin_config
         WHERE controller_id = :controller_id
           AND pin REGEXP "^D[0-9]+$"'
    );
    $invertStmt->execute(['controller_id' => $controllerId]);
    $invertMap = [];
    foreach ($invertStmt->fetchAll() as $row) {
        $invertMap[normalize_pin((string)$row['pin'])] = ((int)$row['invert_digital_logic']) > 0;
    }

    $upsertStateStmt = $pdo->prepare(
        'INSERT INTO controller_scenario_state (scenario_id, is_active, updated_at)
         VALUES (:scenario_id, :is_active, NOW())
         ON DUPLICATE KEY UPDATE is_active = VALUES(is_active), updated_at = VALUES(updated_at)'
    );

    $targetDecisions = [];
    foreach ($scenarios as $scenario) {
        if (((int)$scenario['enabled']) <= 0) {
            continue;
        }

        $sourcePin = normalize_pin((string)$scenario['source_pin']);
        $targetPin = normalize_pin((string)$scenario['target_pin']);
        if ($sourcePin === '' || $targetPin === '') {
            continue;
        }

        if (!isset($readingMap[$sourcePin])) {
            continue;
        }

        $sourceValue = (float)$readingMap[$sourcePin];
        $operator = normalize_scenario_operator($scenario['operator']);
        $threshold = (float)$scenario['threshold'];
        $hysteresis = max(0.0, (float)$scenario['hysteresis']);
        $wasActive = ((int)$scenario['current_state']) > 0;
        $isActive = evaluate_scenario_active($operator, $sourceValue, $threshold, $hysteresis, $wasActive);

        $nextValueRaw = $isActive
            ? to_bit($scenario['value_when_true'], 1)
            : to_bit($scenario['value_when_false'], 0);
        $isInvertedTarget = $invertMap[$targetPin] ?? false;
        $nextDesiredValue = $isInvertedTarget ? ($nextValueRaw > 0 ? 0 : 1) : $nextValueRaw;

        if (!array_key_exists($targetPin, $targetDecisions)) {
            $targetDecisions[$targetPin] = $nextDesiredValue;
        }

        $upsertStateStmt->execute([
            'scenario_id' => (int)$scenario['id'],
            'is_active' => $isActive ? 1 : 0,
        ]);
    }

    if (!$targetDecisions) {
        return;
    }

    $updateTargetStmt = $pdo->prepare(
        'UPDATE controller_pin_config
         SET desired_digital_value = :value_set,
             desired_digital_updated_at =
               CASE WHEN COALESCE(desired_digital_value, -1) <> :value_cmp THEN NOW() ELSE desired_digital_updated_at END
         WHERE controller_id = :controller_id
           AND pin = :pin
           AND digital_style = "power"'
    );

    foreach ($targetDecisions as $targetPin => $value) {
        $updateTargetStmt->execute([
            'value_set' => ((int)$value) > 0 ? 1 : 0,
            'value_cmp' => ((int)$value) > 0 ? 1 : 0,
            'controller_id' => $controllerId,
            'pin' => $targetPin,
        ]);
    }
}

function handle_get_controllers(): void
{
    $stmt = db()->query('SELECT id, name, discription, send_interval_seconds FROM controllers ORDER BY id ASC');
    json_response(['controllers' => $stmt->fetchAll()]);
}

function handle_get_settings(int $controllerId): void
{
    $controllerStmt = db()->prepare('SELECT id, name, discription, send_interval_seconds FROM controllers WHERE id = :id');
    $controllerStmt->execute(['id' => $controllerId]);
    $controller = $controllerStmt->fetch();

    if (!$controller) {
        json_response(['error' => 'not_found', 'message' => 'Controller not found'], 404);
    }

    json_response([
        'controller' => $controller,
        'pinConfigs' => fetch_pin_configs(db(), $controllerId),
    ]);
}

function handle_put_settings(int $controllerId, array $payload): void
{
    $name = trim((string)($payload['name'] ?? ''));
    if ($name === '') {
        json_response(['error' => 'validation_error', 'message' => 'name must not be empty'], 400);
    }

    $sendInterval = max(1, (int)($payload['send_interval_seconds'] ?? 5));
    $description = isset($payload['discription']) ? trim((string)$payload['discription']) : null;
    if ($description === '') {
        $description = null;
    }

    $configs = $payload['pinConfigs'] ?? null;
    if (!is_array($configs) || count($configs) === 0) {
        json_response(['error' => 'validation_error', 'message' => 'pinConfigs must contain at least one item'], 400);
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $controllerStmt = $pdo->prepare('SELECT id FROM controllers WHERE id = :id');
        $controllerStmt->execute(['id' => $controllerId]);
        if (!$controllerStmt->fetch()) {
            $pdo->rollBack();
            json_response(['error' => 'not_found', 'message' => 'Controller not found'], 404);
        }

        $updateController = $pdo->prepare('UPDATE controllers SET name = :name, discription = :discription, send_interval_seconds = :send_interval WHERE id = :id');
        $updateController->execute([
            'id' => $controllerId,
            'name' => $name,
            'discription' => $description,
            'send_interval' => $sendInterval,
        ]);

        $pdo->prepare('DELETE FROM controller_pin_config WHERE controller_id = :controller_id')->execute(['controller_id' => $controllerId]);

        $insert = $pdo->prepare(
            'INSERT INTO controller_pin_config (
              controller_id, pin, label, unit, multiplier, value_offset, precision_value, average_interval_minutes,
              value_labels, digital_style, invert_digital_logic, desired_digital_value, desired_digital_updated_at,
              power_on_duration_seconds, show_on_dashboard, show_on_chart, chart_range_hours, sort_order
            ) VALUES (
              :controller_id, :pin, :label, :unit, :multiplier, :value_offset, :precision_value, :average_interval_minutes,
              :value_labels, :digital_style, :invert_digital_logic, :desired_digital_value, NULL,
              :power_on_duration_seconds, :show_on_dashboard, :show_on_chart, :chart_range_hours, :sort_order
            )'
        );

        foreach (array_values($configs) as $index => $config) {
            if (!is_array($config)) {
                continue;
            }

            $pin = normalize_pin((string)($config['pin'] ?? ''));
            $label = trim((string)($config['label'] ?? $pin));
            if ($pin === '' || $label === '') {
                continue;
            }

            $isDigital = is_digital_pin($pin);
            $style = (string)($config['digital_style'] ?? ($isDigital ? 'power' : 'sensor'));
            $desired = $isDigital && $style === 'power' ? (((int)($config['desired_digital_value'] ?? 0)) > 0 ? 1 : 0) : null;

            $insert->execute([
                'controller_id' => $controllerId,
                'pin' => $pin,
                'label' => $label,
                'unit' => isset($config['unit']) && trim((string)$config['unit']) !== '' ? trim((string)$config['unit']) : null,
                'multiplier' => (float)($config['multiplier'] ?? 1),
                'value_offset' => (float)($config['offset'] ?? 0),
                'precision_value' => max(0, (int)($config['precision'] ?? 0)),
                'average_interval_minutes' => max(1, (int)($config['average_interval_minutes'] ?? 5)),
                'value_labels' => json_encode(get_json_map($config['value_labels'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'digital_style' => $style,
                'invert_digital_logic' => !empty($config['invert_digital_logic']) ? 1 : 0,
                'desired_digital_value' => $desired,
                'power_on_duration_seconds' => isset($config['power_on_duration_seconds']) ? (int)$config['power_on_duration_seconds'] : null,
                'show_on_dashboard' => !empty($config['show_on_dashboard']) ? 1 : 0,
                'show_on_chart' => !empty($config['show_on_chart']) ? 1 : 0,
                'chart_range_hours' => max(1, (int)($config['chart_range_hours'] ?? 1)),
                'sort_order' => (int)($config['sort_order'] ?? $index),
            ]);
        }

        $pdo->commit();
        json_response(['ok' => true]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        json_response(['error' => 'db_error', 'message' => $e->getMessage()], 500);
    }
}

function handle_get_readings(int $controllerId): void
{
    $pdo = db();
    $controllerStmt = $pdo->prepare('SELECT id, name, discription, send_interval_seconds FROM controllers WHERE id = :id');
    $controllerStmt->execute(['id' => $controllerId]);
    $controller = $controllerStmt->fetch();

    if (!$controller) {
        json_response(['error' => 'not_found', 'message' => 'Controller not found'], 404);
    }

    $pinConfigs = fetch_pin_configs($pdo, $controllerId);
    $configByPin = [];
    $maxChartHours = 1;
    foreach ($pinConfigs as $cfg) {
        $configByPin[normalize_pin($cfg['pin'])] = $cfg;
        if ($cfg['show_on_chart']) {
            $maxChartHours = max($maxChartHours, (int)$cfg['chart_range_hours']);
        }
    }

    $latestStmt = $pdo->prepare(
        'SELECT id, pin, value, controller_id, created_at
         FROM (
             SELECT id, pin, value, controller_id, created_at,
                    ROW_NUMBER() OVER (PARTITION BY pin ORDER BY created_at DESC, id DESC) AS rn
             FROM controller_data
             WHERE controller_id = :controller_id
         ) ranked
         WHERE rn = 1'
    );
    $latestStmt->execute(['controller_id' => $controllerId]);
    $latestRows = $latestStmt->fetchAll();

    $historyStmt = $pdo->prepare(
        'SELECT id, pin, value, controller_id, created_at
         FROM controller_data
         WHERE controller_id = :controller_id
           AND created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
         ORDER BY created_at DESC, id DESC
         LIMIT 4000'
    );
    $historyStmt->bindValue('controller_id', $controllerId, PDO::PARAM_INT);
    $historyStmt->bindValue('hours', $maxChartHours, PDO::PARAM_INT);
    $historyStmt->execute();
    $historyRows = $historyStmt->fetchAll();

    $latest = array_map(fn(array $row) => format_reading($row, $configByPin), $latestRows);
    usort($latest, fn(array $a, array $b) => ($a['sort_order'] <=> $b['sort_order']) ?: strcmp($a['pin'], $b['pin']));

    $history = array_values(array_filter(
        array_map(fn(array $row) => format_reading($row, $configByPin), $historyRows),
        fn(array $item) => $item['show_on_chart']
    ));

    json_response([
        'controller' => $controller,
        'latest' => $latest,
        'history' => $history,
    ]);
}

function handle_put_pin_state(int $controllerId, string $pin, array $payload): void
{
    $pin = normalize_pin($pin);
    if (!is_digital_pin($pin)) {
        json_response(['error' => 'validation_error', 'message' => 'Pin must be digital'], 400);
    }

    $value = isset($payload['value']) && ((int)$payload['value'] > 0) ? 1 : 0;

    $pdo = db();
    $stmt = $pdo->prepare(
        'UPDATE controller_pin_config
         SET desired_digital_value = :value_set,
             desired_digital_updated_at = CASE WHEN :value_case > 0 THEN NOW() ELSE NULL END
         WHERE controller_id = :controller_id
           AND pin = :pin
           AND digital_style = "power"'
    );
    $stmt->execute([
        'value_set' => $value,
        'value_case' => $value,
        'controller_id' => $controllerId,
        'pin' => $pin,
    ]);

    if ($stmt->rowCount() === 0) {
        json_response(['error' => 'not_found', 'message' => 'Pin not found or not power type'], 404);
    }

    json_response(['ok' => true, 'pin' => $pin, 'value' => $value]);
}

function handle_delete_pin_history(int $controllerId, string $pin): void
{
    $pin = normalize_pin($pin);
    if ($pin === '' || is_digital_pin($pin)) {
        json_response(['error' => 'validation_error', 'message' => 'History cleanup is available only for analog pins'], 400);
    }

    $pdo = db();

    $controllerStmt = $pdo->prepare('SELECT id FROM controllers WHERE id = :id');
    $controllerStmt->execute(['id' => $controllerId]);
    if (!$controllerStmt->fetch()) {
        json_response(['error' => 'not_found', 'message' => "Controller {$controllerId} not found"], 404);
    }

    $deleteStmt = $pdo->prepare(
        'DELETE FROM controller_data
         WHERE controller_id = :controller_id
           AND pin = :pin'
    );
    $deleteStmt->execute([
        'controller_id' => $controllerId,
        'pin' => $pin,
    ]);

    json_response([
        'ok' => true,
        'pin' => $pin,
        'deleted' => (int)$deleteStmt->rowCount(),
    ]);
}

function fetch_controller_scenarios(PDO $pdo, int $controllerId): array
{
    $stmt = $pdo->prepare(
        'SELECT s.id, s.controller_id, s.name, s.source_pin, s.operator, s.threshold, s.hysteresis,
                s.target_pin, s.value_when_true, s.value_when_false, s.priority, s.enabled,
                COALESCE(st.is_active, 0) AS current_state,
                s.created_at, s.updated_at
         FROM controller_scenarios s
         LEFT JOIN controller_scenario_state st ON st.scenario_id = s.id
         WHERE s.controller_id = :controller_id
         ORDER BY s.priority ASC, s.id ASC'
    );
    $stmt->execute(['controller_id' => $controllerId]);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'id' => (int)$row['id'],
            'controller_id' => (int)$row['controller_id'],
            'name' => (string)$row['name'],
            'source_pin' => normalize_pin((string)$row['source_pin']),
            'operator' => normalize_scenario_operator($row['operator']),
            'threshold' => (float)$row['threshold'],
            'hysteresis' => (float)$row['hysteresis'],
            'target_pin' => normalize_pin((string)$row['target_pin']),
            'value_when_true' => to_bit($row['value_when_true'], 1),
            'value_when_false' => to_bit($row['value_when_false'], 0),
            'priority' => (int)$row['priority'],
            'enabled' => ((int)$row['enabled']) > 0,
            'current_state' => to_bit($row['current_state'], 0),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    return $rows;
}

function handle_get_scenarios(int $controllerId): void
{
    json_response(['scenarios' => fetch_controller_scenarios(db(), $controllerId)]);
}

function handle_post_scenario(int $controllerId, array $payload): void
{
    $name = trim((string)($payload['name'] ?? ''));
    $sourcePin = normalize_pin((string)($payload['source_pin'] ?? ''));
    $targetPin = normalize_pin((string)($payload['target_pin'] ?? ''));
    if ($name === '' || $sourcePin === '' || $targetPin === '') {
        json_response(['error' => 'validation_error', 'message' => 'name, source_pin and target_pin are required'], 400);
    }

    $stmt = db()->prepare(
        'INSERT INTO controller_scenarios
          (controller_id, name, source_pin, operator, threshold, hysteresis, target_pin, value_when_true, value_when_false, priority, enabled, created_at, updated_at)
         VALUES
          (:controller_id, :name, :source_pin, :operator, :threshold, :hysteresis, :target_pin, :value_when_true, :value_when_false, :priority, :enabled, NOW(), NOW())'
    );
    $stmt->execute([
        'controller_id' => $controllerId,
        'name' => $name,
        'source_pin' => $sourcePin,
        'operator' => normalize_scenario_operator($payload['operator'] ?? 'gt'),
        'threshold' => (float)($payload['threshold'] ?? 0),
        'hysteresis' => max(0, (float)($payload['hysteresis'] ?? 0)),
        'target_pin' => $targetPin,
        'value_when_true' => to_bit($payload['value_when_true'] ?? 1, 1),
        'value_when_false' => to_bit($payload['value_when_false'] ?? 0, 0),
        'priority' => (int)($payload['priority'] ?? 100),
        'enabled' => isset($payload['enabled']) && !$payload['enabled'] ? 0 : 1,
    ]);

    json_response(['ok' => true, 'scenario_id' => (int)db()->lastInsertId()]);
}

function handle_put_scenario(int $controllerId, int $scenarioId, array $payload): void
{
    $currentStmt = db()->prepare(
        'SELECT id, name, source_pin, operator, threshold, hysteresis, target_pin, value_when_true, value_when_false, priority, enabled
         FROM controller_scenarios
         WHERE id = :id AND controller_id = :controller_id'
    );
    $currentStmt->execute(['id' => $scenarioId, 'controller_id' => $controllerId]);
    $current = $currentStmt->fetch();
    if (!$current) {
        json_response(['error' => 'not_found', 'message' => 'Scenario not found'], 404);
    }

    $name = trim((string)($payload['name'] ?? $current['name']));
    $sourcePin = normalize_pin((string)($payload['source_pin'] ?? $current['source_pin']));
    $targetPin = normalize_pin((string)($payload['target_pin'] ?? $current['target_pin']));
    if ($name === '' || $sourcePin === '' || $targetPin === '') {
        json_response(['error' => 'validation_error', 'message' => 'name, source_pin and target_pin must not be empty'], 400);
    }

    $stmt = db()->prepare(
        'UPDATE controller_scenarios
         SET name = :name,
             source_pin = :source_pin,
             operator = :operator,
             threshold = :threshold,
             hysteresis = :hysteresis,
             target_pin = :target_pin,
             value_when_true = :value_when_true,
             value_when_false = :value_when_false,
             priority = :priority,
             enabled = :enabled,
             updated_at = NOW()
         WHERE id = :id AND controller_id = :controller_id'
    );
    $stmt->execute([
        'id' => $scenarioId,
        'controller_id' => $controllerId,
        'name' => $name,
        'source_pin' => $sourcePin,
        'operator' => normalize_scenario_operator($payload['operator'] ?? $current['operator']),
        'threshold' => (float)($payload['threshold'] ?? $current['threshold']),
        'hysteresis' => max(0, (float)($payload['hysteresis'] ?? $current['hysteresis']),
        ),
        'target_pin' => $targetPin,
        'value_when_true' => to_bit($payload['value_when_true'] ?? $current['value_when_true'], 1),
        'value_when_false' => to_bit($payload['value_when_false'] ?? $current['value_when_false'], 0),
        'priority' => (int)($payload['priority'] ?? $current['priority']),
        'enabled' => isset($payload['enabled']) ? ($payload['enabled'] ? 1 : 0) : (((int)$current['enabled']) > 0 ? 1 : 0),
    ]);

    json_response(['ok' => true]);
}

function handle_delete_scenario(int $controllerId, int $scenarioId): void
{
    $stmt = db()->prepare('DELETE FROM controller_scenarios WHERE id = :id AND controller_id = :controller_id');
    $stmt->execute(['id' => $scenarioId, 'controller_id' => $controllerId]);
    json_response(['ok' => true]);
}

function is_valid_timezone(string $tz): bool
{
    return in_array($tz, DateTimeZone::listIdentifiers(), true);
}

function handle_get_system_timezone(): void
{
    $stmt = db()->query('SELECT time_zone FROM system_settings WHERE id = 1');
    $row = $stmt->fetch();
    if ($row && is_string($row['time_zone']) && trim($row['time_zone']) !== '') {
        json_response(['time_zone' => trim($row['time_zone'])]);
    }
    json_response(['time_zone' => 'Europe/Moscow']);
}

function handle_put_system_timezone(array $payload): void
{
    $timeZone = trim((string)($payload['time_zone'] ?? ''));
    if ($timeZone === '' || !is_valid_timezone($timeZone)) {
        json_response([
            'error' => 'validation_error',
            'message' => 'time_zone must be a valid IANA timezone (e.g. Europe/Moscow)'
        ], 400);
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO system_settings (id, time_zone)
         VALUES (1, :time_zone)
         ON DUPLICATE KEY UPDATE time_zone = VALUES(time_zone)'
    );
    $stmt->execute(['time_zone' => $timeZone]);

    json_response(['ok' => true, 'time_zone' => $timeZone]);
}

function seconds_from_midnight_in_timezone(DateTimeZone $timeZone): int
{
    $now = new DateTimeImmutable('now', $timeZone);
    $h = (int)$now->format('H');
    $m = (int)$now->format('i');
    $s = (int)$now->format('s');
    return $h * 3600 + $m * 60 + $s;
}

function analog_pin_key(string $pin): string
{
    $normalized = trim($pin);
    if (preg_match('/^a\d+$/i', $normalized) === 1) {
        return strtoupper($normalized);
    }
    return strtolower($normalized);
}

function digital_pin_sort_key(string $pin): string
{
    return strtoupper(trim($pin));
}

function calculate_digital_on_seconds_24h(PDO $pdo, int $controllerId, string $pin, bool $invert): int
{
    $pin = digital_pin_sort_key($pin);
    $windowStart = new DateTimeImmutable('-24 hours');
    $windowEnd = new DateTimeImmutable('now');

    $prevStmt = $pdo->prepare(
        'SELECT value, created_at
         FROM controller_data
         WHERE controller_id = :controller_id
           AND pin = :pin
           AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
         ORDER BY created_at DESC, id DESC
         LIMIT 1'
    );
    $prevStmt->execute(['controller_id' => $controllerId, 'pin' => $pin]);
    $prev = $prevStmt->fetch();

    $rowsStmt = $pdo->prepare(
        'SELECT value, created_at
         FROM controller_data
         WHERE controller_id = :controller_id
           AND pin = :pin
           AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
         ORDER BY created_at ASC, id ASC'
    );
    $rowsStmt->execute(['controller_id' => $controllerId, 'pin' => $pin]);
    $rows = $rowsStmt->fetchAll();

    $timeline = [];
    if ($prev) {
        $raw = ((float)$prev['value']) > 0 ? 1 : 0;
        $state = $invert ? ($raw > 0 ? 0 : 1) : $raw;
        $timeline[] = ['ts' => $windowStart->getTimestamp(), 'state' => $state];
    }
    foreach ($rows as $row) {
        $ts = strtotime((string)$row['created_at']);
        if ($ts === false) {
            continue;
        }
        $raw = ((float)$row['value']) > 0 ? 1 : 0;
        $state = $invert ? ($raw > 0 ? 0 : 1) : $raw;
        $timeline[] = ['ts' => $ts, 'state' => $state];
    }

    if (!$timeline) {
        return 0;
    }

    usort($timeline, fn(array $a, array $b) => $a['ts'] <=> $b['ts']);
    $startTs = $windowStart->getTimestamp();
    $endTs = $windowEnd->getTimestamp();
    $onSeconds = 0;

    $count = count($timeline);
    for ($i = 0; $i < $count; $i++) {
        $current = $timeline[$i];
        $nextTs = $i + 1 < $count ? (int)$timeline[$i + 1]['ts'] : $endTs;
        $intervalStart = max((int)$current['ts'], $startTs);
        $intervalEnd = min($nextTs, $endTs);
        if ($intervalEnd > $intervalStart && ((int)$current['state']) > 0) {
            $onSeconds += $intervalEnd - $intervalStart;
        }
    }

    return max(0, $onSeconds);
}

function build_controller_parameters(PDO $pdo, int $controllerId): array
{
    $pinConfigs = fetch_pin_configs($pdo, $controllerId);
    $parameters = [];
    $values = [];
    $controllerPrefix = "controller:{$controllerId}:";

    $tzStmt = $pdo->query('SELECT time_zone FROM system_settings WHERE id = 1');
    $tzRow = $tzStmt->fetch();
    $tzName = is_array($tzRow) && !empty($tzRow['time_zone']) ? (string)$tzRow['time_zone'] : 'Europe/Moscow';
    $timeZone = is_valid_timezone($tzName) ? new DateTimeZone($tzName) : new DateTimeZone('Europe/Moscow');
    $values[$controllerPrefix . 'current_time'] = seconds_from_midnight_in_timezone($timeZone);

    $latestStmt = $pdo->prepare(
        'SELECT pin, value
         FROM (
            SELECT pin, value, ROW_NUMBER() OVER (PARTITION BY pin ORDER BY created_at DESC, id DESC) AS rn
            FROM controller_data
            WHERE controller_id = :controller_id
         ) ranked
         WHERE rn = 1'
    );
    $latestStmt->execute(['controller_id' => $controllerId]);
    $latestByPin = [];
    foreach ($latestStmt->fetchAll() as $row) {
        $latestByPin[digital_pin_sort_key((string)$row['pin'])] = (float)$row['value'];
    }

    foreach ($pinConfigs as $config) {
        $pin = (string)$config['pin'];
        $normalizedPin = digital_pin_sort_key($pin);

        if (is_analog_pin($pin)) {
            $intervalMinutes = max(1, (int)($config['average_interval_minutes'] ?? 5));
            $avgStmt = $pdo->prepare(
                'SELECT AVG(value) AS avg_value
                 FROM controller_data
                 WHERE controller_id = :controller_id
                   AND pin = :pin
                   AND created_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)'
            );
            $avgStmt->bindValue('controller_id', $controllerId, PDO::PARAM_INT);
            $avgStmt->bindValue('pin', $normalizedPin, PDO::PARAM_STR);
            $avgStmt->bindValue('minutes', $intervalMinutes, PDO::PARAM_INT);
            $avgStmt->execute();
            $avgRow = $avgStmt->fetch();
            $avgRaw = $avgRow && $avgRow['avg_value'] !== null ? (float)$avgRow['avg_value'] : null;
            if ($avgRaw !== null) {
                $values[$controllerPrefix . 'avg_pin:' . $normalizedPin] = $avgRaw;
            }
            continue;
        }

        if (is_digital_pin($pin)) {
            if (array_key_exists($normalizedPin, $latestByPin)) {
                $rawBit = $latestByPin[$normalizedPin] > 0 ? 1 : 0;
                $invert = !empty($config['invert_digital_logic']);
                $state = $invert ? ($rawBit > 0 ? 0 : 1) : $rawBit;
                $values[$controllerPrefix . 'pin_state:' . $normalizedPin] = $state;
            }
            $onSeconds = calculate_digital_on_seconds_24h($pdo, $controllerId, $normalizedPin, !empty($config['invert_digital_logic']));
            $values[$controllerPrefix . 'pin_on_seconds_24h:' . $normalizedPin] = $onSeconds;
        }
    }

    foreach ($values as $key => $value) {
        if (!str_starts_with($key, $controllerPrefix)) {
            continue;
        }

        $normalized = substr($key, strlen($controllerPrefix));
        if ($normalized === 'current_time') {
            $parameters[] = [
                'key' => $key,
                'label' => 'Текущее время',
                'value' => (float)$value,
                'unit' => null,
            ];
            continue;
        }

        if (str_starts_with($normalized, 'avg_pin:')) {
            $pin = digital_pin_sort_key(substr($normalized, strlen('avg_pin:')));
            $config = null;
            foreach ($pinConfigs as $item) {
                if (digital_pin_sort_key((string)$item['pin']) === $pin) {
                    $config = $item;
                    break;
                }
            }
            $multiplier = (float)($config['multiplier'] ?? 1);
            $offset = (float)($config['offset'] ?? 0);
            $intervalMinutes = max(1, (int)($config['average_interval_minutes'] ?? 5));
            $label = ((string)($config['label'] ?? $pin)) . ' за последние ' . $intervalMinutes . ' мин';

            $parameters[] = [
                'key' => $key,
                'label' => $label,
                'value' => ((float)$value) * $multiplier + $offset,
                'unit' => $config['unit'] ?? null,
            ];
            continue;
        }

        if (str_starts_with($normalized, 'pin_state:')) {
            $pin = digital_pin_sort_key(substr($normalized, strlen('pin_state:')));
            $parameters[] = [
                'key' => $key,
                'label' => "Состояние пина {$pin} (с учетом инверсии)",
                'value' => (float)$value,
                'unit' => null,
            ];
            continue;
        }

        if (str_starts_with($normalized, 'pin_on_seconds_24h:')) {
            $pin = digital_pin_sort_key(substr($normalized, strlen('pin_on_seconds_24h:')));
            $parameters[] = [
                'key' => $key,
                'label' => "Время включения пина {$pin} за 24 часа",
                'value' => (float)$value,
                'unit' => 'с',
            ];
        }
    }

    usort($parameters, fn(array $a, array $b) => strcmp((string)$a['key'], (string)$b['key']));
    return $parameters;
}

function handle_get_controller_parameters(int $controllerId): void
{
    $controllerStmt = db()->prepare('SELECT id, name FROM controllers WHERE id = :id');
    $controllerStmt->execute(['id' => $controllerId]);
    $controller = $controllerStmt->fetch();
    if (!$controller) {
        json_response(['error' => 'not_found', 'message' => 'Controller not found'], 404);
    }

    $parameters = build_controller_parameters(db(), $controllerId);
    json_response([
        'controller' => ['id' => (int)$controller['id'], 'name' => (string)$controller['name']],
        'parameters' => $parameters,
        'updated_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
    ]);
}

function handle_get_all_scenarios(): void
{
    $stmt = db()->query(
        'SELECT s.id, s.controller_id, c.name AS controller_name, s.name, s.source_pin, s.operator, s.threshold, s.hysteresis,
                s.target_pin, s.value_when_true, s.value_when_false, s.priority, s.enabled,
                COALESCE(st.is_active, 0) AS current_state, s.created_at, s.updated_at
         FROM controller_scenarios s
         INNER JOIN controllers c ON c.id = s.controller_id
         LEFT JOIN controller_scenario_state st ON st.scenario_id = s.id
         ORDER BY c.id ASC, s.priority ASC, s.id ASC'
    );

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'id' => (int)$row['id'],
            'controller_id' => (int)$row['controller_id'],
            'controller_name' => (string)$row['controller_name'],
            'name' => (string)$row['name'],
            'source_pin' => (string)$row['source_pin'],
            'operator' => normalize_scenario_operator($row['operator']),
            'threshold' => (float)$row['threshold'],
            'hysteresis' => (float)$row['hysteresis'],
            'target_pin' => (string)$row['target_pin'],
            'value_when_true' => to_bit($row['value_when_true'], 1),
            'value_when_false' => to_bit($row['value_when_false'], 0),
            'priority' => (int)$row['priority'],
            'enabled' => ((int)$row['enabled']) > 0,
            'current_state' => to_bit($row['current_state'], 0),
            'created_at' => (string)$row['created_at'],
            'updated_at' => (string)$row['updated_at'],
        ];
    }

    json_response(['scenarios' => $rows]);
}

function handle_get_all_pins(): void
{
    $stmt = db()->query(
        'SELECT c.id AS controller_id, c.name AS controller_name, cfg.pin, cfg.label, cfg.digital_style, cfg.sort_order
         FROM controller_pin_config cfg
         INNER JOIN controllers c ON c.id = cfg.controller_id
         ORDER BY c.id ASC, cfg.sort_order ASC, cfg.pin ASC'
    );

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $pin = digital_pin_sort_key((string)$row['pin']);
        $rows[] = [
            'controller_id' => (int)$row['controller_id'],
            'controller_name' => (string)$row['controller_name'],
            'pin' => $pin,
            'label' => (string)$row['label'],
            'digital_style' => (string)$row['digital_style'],
            'sort_order' => (int)$row['sort_order'],
            'display_name' => (string)$row['controller_name'] . ' · ' . (string)$row['label'] . ' (' . $pin . ')',
        ];
    }

    json_response(['pins' => $rows]);
}
