<?php

declare(strict_types=1);

function env_value(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env_value('DB_HOST', 'mysql');
    $port = env_value('DB_PORT', '3306');
    $name = env_value('DB_NAME', 'smarthome');
    $user = env_value('DB_USER', 'smarthome');
    $pass = env_value('DB_PASSWORD', 'smarthome');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function text_response(string $body, int $status = 200, string $contentType = 'text/plain; charset=utf-8'): void
{
    http_response_code($status);
    header('Content-Type: ' . $contentType);
    echo $body;
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_response(['error' => 'invalid_json', 'message' => 'Invalid JSON payload'], 400);
    }

    return $data;
}

function normalize_pin(string $pin): string
{
    return strtoupper(trim($pin));
}

function is_digital_pin(string $pin): bool
{
    return preg_match('/^D[0-9]+$/i', trim($pin)) === 1;
}

function is_analog_pin(string $pin): bool
{
    $normalized = strtolower(trim($pin));
    return preg_match('/^a[0-9]+$/', $normalized) === 1 || $normalized === 'air_temperature' || $normalized === 'air_humidity';
}

function to_number(mixed $value): ?float
{
    if (is_numeric($value)) {
        return (float)$value;
    }
    return null;
}

function get_json_map(mixed $value): array
{
    if (is_array($value)) {
        $result = [];
        foreach ($value as $k => $v) {
            $result[(string)$k] = (string)$v;
        }
        return $result;
    }
    return [];
}

function view(string $template, array $data = []): void
{
    $viewFile = __DIR__ . '/../views/' . $template . '.blade.php';
    $layoutFile = __DIR__ . '/../views/layouts/app.blade.php';
    if (!is_file($viewFile) || !is_file($layoutFile)) {
        text_response('View template not found', 500);
    }

    extract($data, EXTR_SKIP);

    ob_start();
    require $viewFile;
    $content = ob_get_clean();

    require $layoutFile;
    exit;
}
