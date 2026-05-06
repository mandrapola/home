<?php

declare(strict_types=1);

return [
    'pin_data_average_interval_minutes' => max(1, (int) env('PIN_DATA_AVERAGE_INTERVAL_MINUTES', 5)),
    'pin_data_retention_hours' => max(1, (int) env('PIN_DATA_RETENTION_HOURS', 24)),
    'pin_data_cleanup_period_minutes' => max(1, (int) env('PIN_DATA_CLEANUP_PERIOD_MINUTES', 60)),
    'max_manual_on_seconds' => max(1, (int) env('MAX_MANUAL_ON_SECONDS', 300)),
    'default_plan' => (string) env('DEFAULT_PLAN', 'free'),
    'subscription_period_days' => max(1, (int) env('SUBSCRIPTION_PERIOD_DAYS', 30)),
];
