<?php

use App\Modules\Monitoring\Services\MonitoringInstaller;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(MonitoringInstaller::class)->install();
    }

    public function down(): void
    {
        throw new RuntimeException('Monitoring V2 cutover is irreversible. Restore coordinated code + database backup.');
    }
};
