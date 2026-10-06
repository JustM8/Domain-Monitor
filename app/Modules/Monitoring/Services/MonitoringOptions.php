<?php

namespace App\Modules\Monitoring\Services;

use Illuminate\Http\Request;

final class MonitoringOptions
{
    public function fromRequest(Request $request): array
    {
        return $request->validate(['monitoring_enabled' => ['sometimes', 'boolean']]);
    }
}
