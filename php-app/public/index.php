<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/app.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($method === 'GET' && $uri === '/api/ping') {
    json_response(['ok' => true, 'service' => 'smart-home-php']);
}

if ($method === 'GET' && $uri === '/api/controllers') {
    handle_get_controllers();
}

if ($method === 'GET' && $uri === '/api/scenarios') {
    handle_get_all_scenarios();
}

if ($method === 'GET' && $uri === '/api/pins') {
    handle_get_all_pins();
}

if ($method === 'GET' && $uri === '/api/settings/timezone') {
    handle_get_system_timezone();
}

if ($method === 'PUT' && $uri === '/api/settings/timezone') {
    handle_put_system_timezone(read_json_body());
}

if ($method === 'GET' && preg_match('#^/api/controllers/(\d+)/scenarios$#', $uri, $m) === 1) {
    handle_get_scenarios((int)$m[1]);
}

if ($method === 'POST' && preg_match('#^/api/controllers/(\d+)/scenarios$#', $uri, $m) === 1) {
    handle_post_scenario((int)$m[1], read_json_body());
}

if ($method === 'PUT' && preg_match('#^/api/controllers/(\d+)/scenarios/(\d+)$#', $uri, $m) === 1) {
    handle_put_scenario((int)$m[1], (int)$m[2], read_json_body());
}

if ($method === 'DELETE' && preg_match('#^/api/controllers/(\d+)/scenarios/(\d+)$#', $uri, $m) === 1) {
    handle_delete_scenario((int)$m[1], (int)$m[2]);
}

if ($method === 'POST' && $uri === '/api/controller/report') {
    handle_report(read_json_body());
}

if (preg_match('#^/api/controllers/(\d+)/settings$#', $uri, $m) === 1) {
    $controllerId = (int)$m[1];
    if ($method === 'GET') {
        handle_get_settings($controllerId);
    }
    if ($method === 'PUT') {
        handle_put_settings($controllerId, read_json_body());
    }
    json_response(['error' => 'method_not_allowed'], 405);
}

if ($method === 'GET' && preg_match('#^/api/controllers/(\d+)/readings$#', $uri, $m) === 1) {
    handle_get_readings((int)$m[1]);
}

if ($method === 'GET' && preg_match('#^/api/controllers/(\d+)/parameters$#', $uri, $m) === 1) {
    handle_get_controller_parameters((int)$m[1]);
}

if ($method === 'PUT' && preg_match('#^/api/controllers/(\d+)/pins/([^/]+)/state$#', $uri, $m) === 1) {
    handle_put_pin_state((int)$m[1], urldecode($m[2]), read_json_body());
}

if ($method === 'DELETE' && preg_match('#^/api/controllers/(\d+)/pins/([^/]+)/history$#', $uri, $m) === 1) {
    handle_delete_pin_history((int)$m[1], urldecode($m[2]));
}

if ($method === 'GET' && $uri === '/') {
    view('dashboard', ['title' => 'Smart Home Dashboard', 'script' => '/assets/app.js?v=7']);
}

if ($method === 'GET' && $uri === '/scenes') {
    view('scenes', ['title' => 'Smart Home Scenarios', 'script' => '/assets/scenes.js?v=4']);
}

if ($method === 'GET' && $uri === '/settings') {
    view('settings', ['title' => 'Smart Home Settings', 'script' => '/assets/settings.js?v=1']);
}

if ($method === 'GET' && $uri === '/parameters') {
    view('parameters', ['title' => 'Smart Home Parameters', 'script' => '/assets/parameters.js?v=2']);
}

if ($method === 'GET' && $uri === '/schedule') {
    view('schedule', ['title' => 'Smart Home Schedule', 'script' => '/assets/schedule.js?v=2']);
}

text_response('Not found', 404);
