<?php

namespace App\PortalControl;

use Closure;
use Illuminate\Http\Request;

class EnforcePortalControl
{
    public function handle(Request $request, Closure $next)
    {
        // The authenticated control route must remain reachable when the public site is disabled.
        if ($request->is('api/portal/sync')) {
            return $next($request);
        }
        try {
            $state = app(PortalControlState::class)->read();
        } catch (\Throwable $e) {
            return response('Тимчасово недоступно.', 503)->header('Cache-Control', 'no-store');
        }
        $response = $state['state'] === 'disabled'
            ? response('Сайт тимчасово недоступний. Зверніться до менеджера проєкту.', 503)->header('Cache-Control', 'no-store')
            : $next($request);
        if ($state['display_mode'] === 'iframe') {
            $origins = $state['embed_origins'] ?: ["'none'"];
            // Preserve other CSP directives; replace only frame-ancestors.
            $csp = preg_replace('/(?:^|;)\s*frame-ancestors\s+[^;]*/i', '', (string) $response->headers->get('Content-Security-Policy'));
            $response->headers->set('Content-Security-Policy', trim($csp, '; ').'; frame-ancestors '.implode(' ', $origins));
            $response->headers->remove('X-Frame-Options');
        }

        return $response;
    }
}
