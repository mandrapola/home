<?php

use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\VkCallbackController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook/{secret}', TelegramWebhookController::class)->name('api.telegram.webhook');
Route::post('/vk/callback', VkCallbackController::class)->name('api.vk.callback');
