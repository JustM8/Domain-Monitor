<?php

return [
    'timezone' => 'Europe/Kyiv',
    'batch_size' => (int) env('MONITORING_BATCH_SIZE', 100),
    'max_seconds' => (int) env('MONITORING_MAX_SECONDS', 240),
    'interval_minutes' => (int) env('MONITORING_INTERVAL_MINUTES', 5),
    'pause_ms' => (int) env('MONITORING_PAUSE_MS', 250),
    'detail_days' => max(1, (int) env('MONITORING_DETAIL_DAYS', 30)),
    'daily_days' => max(1, (int) env('MONITORING_DAILY_DAYS', 365)),
];
