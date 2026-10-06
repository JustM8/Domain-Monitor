<?php

namespace App\Modules\Monitoring\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Monitoring\Services\MonitorHeartbeat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class HeartbeatController extends Controller
{
    public function __invoke(Request $request, MonitorHeartbeat $heartbeats)
    {
        abort_unless($request->secure(), 400, 'HTTPS required');
        abort_if(strlen($request->getContent()) > 1024, 413);
        $credential = $request->bearerToken() ?? '';
        $public = explode('.', $credential, 2)[0];
        foreach (['monitor:'.$public, 'source:'.$public.':'.$request->ip()] as $scope) {
            $key = 'monitoring-ping:'.hash('sha256', $scope);
            abort_if(RateLimiter::tooManyAttempts($key, 60), 429);
            RateLimiter::hit($key, 60);
        }
        $data = $request->validate(['job_run_id' => 'nullable|string|max:128']);
        abort_unless($heartbeats->accept($credential, $data['job_run_id'] ?? null), 401);

        return response()->json(['accepted' => true]);
    }
}
