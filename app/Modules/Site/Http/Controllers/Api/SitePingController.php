<?php

namespace App\Modules\Site\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Site\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class SitePingController extends Controller
{
    public function __invoke(Request $request, Site $site)
    {
        try {
            $token = Crypt::decryptString($site->api_token);
        } catch (\Throwable $e) {
            $token = '';
        }
        $provided = (string) $request->header('X-Site-Token');
        abort_if($token === '' || $provided === '' || ! hash_equals($token, $provided), 401);

        return response()->json([
            'site_id' => $site->id, 'managed' => $site->remote_control_enabled,
            'status' => $site->is_active ? 'active' : 'disabled', 'version' => $site->control_version,
            'reason' => $site->disabled_reason, 'display_mode' => $site->display_mode, 'embed_origins' => $site->embed_origins ?: [],
        ])->header('Cache-Control', 'no-store');
    }
}
