<?php

namespace App\Modules\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->portalIsActive()) {
            $routeName = $request->route()?->getName();
            $allowedRoutes = [
                'portal.pending-approval',
            ];

            if ($routeName && (
                in_array($routeName, $allowedRoutes, true) ||
                str_starts_with($routeName, 'portal.profile.')
            )) {
                return $next($request);
            }

            return redirect()
                ->route('portal.pending-approval')
                ->with('error', __('portal.awaiting_admin_approval'));
        }

        return $next($request);
    }
}
