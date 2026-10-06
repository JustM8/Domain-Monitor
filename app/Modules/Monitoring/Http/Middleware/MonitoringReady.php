<?php

namespace App\Modules\Monitoring\Http\Middleware;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MonitoringReady
{
    public function handle($request, \Closure $next)
    {
        abort_unless(Schema::hasTable('monitoring_v2_install') && DB::table('monitoring_v2_install')->value('stage') === 'complete', 503, 'Monitoring V2 cutover incomplete.');

        return $next($request);
    }
}
