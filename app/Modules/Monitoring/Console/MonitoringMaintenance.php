<?php

namespace App\Modules\Monitoring\Console;

use App\Modules\Monitoring\Services\MonitorEvents;
use App\Modules\Monitoring\Services\MonitorRetention;
use Illuminate\Console\Command;

class MonitoringMaintenance extends Command
{
    protected $signature = 'monitoring:maintenance {--deliver} {--prune}';

    protected $description = 'Separate bounded delivery/retention budget';

    public function handle(MonitorEvents $events, MonitorRetention $retention): int
    {
        if ($this->option('deliver')) {
            $events->deliver(20, microtime(true) + 45);
        }
        if ($this->option('prune')) {
            $retention->prune(100);
        }

        return self::SUCCESS;
    }
}
