<?php

namespace App\PortalControl;

use Illuminate\Http\Request;

class PortalControlEndpoint
{
    public function __invoke(Request $request, PortalControlState $store)
    {
        $secret = (string) config('portal_control.token');
        $provided = (string) $request->header('X-Site-Token');
        abort_if(strlen($secret) < 32 || $provided === '' || ! hash_equals($secret, $provided), 401);
        $data = $request->validate([
            'site_id' => ['required', 'integer'],
            'state' => ['required', 'in:active,disabled'],
            'version' => ['required', 'integer', 'min:0'],
            'issued_at' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'display_mode' => ['required', 'in:standalone,iframe'],
            'embed_origins' => ['present', 'array', 'max:100'],
            'embed_origins.*' => ['string', 'url:http,https', 'max:255'],
        ]);
        abort_unless((int) $data['site_id'] === (int) config('portal_control.site_id'), 403);
        abort_if(abs(time() - $data['issued_at']) > 300, 409, 'Expired command');
        foreach ($data['embed_origins'] as $origin) {
            $parts = parse_url($origin);
            abort_if(isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || ! in_array($parts['path'] ?? '', ['', '/'], true), 422);
        }
        $data['reason'] ??= null;
        try {
            $state = $store->apply($data);
        } catch (\RuntimeException $e) {
            abort($e->getCode() === 409 ? 409 : 503, 'Control state was not applied');
        }

        return response()->json(['status' => $state['state'], 'version' => $state['version']])->header('Cache-Control', 'no-store');
    }
}
