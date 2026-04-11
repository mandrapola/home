<?php

use App\Http\Controllers\Api\ControllerReportController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'ok' => true,
        'service' => 'server',
    ]);
});

Route::post('/controller/report', ControllerReportController::class)->middleware('proxy.hmac');
