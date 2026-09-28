<?php

return [
    'token' => env('INTERNAL_SCHEDULER_TOKEN'),
    'rate_limit_per_minute' => (int) env('INTERNAL_SCHEDULER_RATE_LIMIT', 20),
    'lock_seconds' => (int) env('INTERNAL_SCHEDULER_LOCK_SECONDS', 60),
    'batch_size' => (int) env('INTERNAL_SCHEDULER_BATCH_SIZE', 50),
    'stale_running_minutes' => (int) env('INTERNAL_SCHEDULER_STALE_RUNNING_MINUTES', 5),
];
