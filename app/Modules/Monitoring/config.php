<?php

return ['batch_size' => (int) env('MONITORING_BATCH_SIZE', 100), 'max_seconds' => (int) env('MONITORING_MAX_SECONDS', 45), 'php_cli' => env('MONITORING_PHP_CLI')];
