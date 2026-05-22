<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('billing:charge-daily {date?}', function (?string $date = null) {
    $processed = app(\App\Services\Billing\DailyBalanceChargeService::class)->chargeForDate($date);
    $this->info("Daily balance charge completed. Users processed: {$processed}");
})->purpose('Charge user balances for days with controller activity');
