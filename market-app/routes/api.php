<?php

use App\Http\Controllers\Api\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook/{secret}', TelegramWebhookController::class)->name('api.telegram.webhook');
