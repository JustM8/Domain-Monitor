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
        $providedToken = (string) $request->header('X-Site-Token', '');
        $storedToken = $site->api_token ? Crypt::decryptString($site->api_token) : '';

        if ($providedToken === '' || ! hash_equals($storedToken, $providedToken)) {
            return response()->json([
                'status' => 'unauthorized',
                'message' => __('portal.unauthorized'),
            ], 401);
        }

        if (! $site->is_active) {
            return response()->json([
                'status' => 'disabled',
                'reason' => $site->disabled_reason,
                'message' => __('portal.site_temporarily_disabled'),
            ], 423);
        }

        return response()->json([
            'status' => 'active',
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
                'url' => $site->url,
            ],
        ]);
    }
}
