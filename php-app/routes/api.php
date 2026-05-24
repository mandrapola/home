<?php

use App\Http\Controllers\Api\ControllerReportController;
use App\Http\Controllers\Api\AliceController;
use App\Http\Controllers\Api\YooKassaWebhookController;
use App\Http\Controllers\OAuth\AliceOAuthProviderController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'ok' => true,
        'service' => 'server',
    ]);
});

Route::post('/controller/provision', [ControllerReportController::class, 'provision'])->middleware('throttle:controller-provision');
Route::post('/controller/report', ControllerReportController::class)->middleware('controller.token');
Route::post('/oauth/token', [AliceOAuthProviderController::class, 'token'])->middleware('throttle:alice-oauth-token');
Route::post('/payments/yookassa/webhook', YooKassaWebhookController::class);

Route::prefix('/alice/v1.0')
    ->middleware(['alice.resolve', 'alice.access', 'throttle:alice-api'])
    ->group(function (): void {
        Route::get('/user/devices', [AliceController::class, 'devices']);
        Route::post('/user/devices/query', [AliceController::class, 'query']);
        Route::post('/user/devices/action', [AliceController::class, 'action']);
        Route::post('/user/unlink', [AliceController::class, 'unlink']);
    });
