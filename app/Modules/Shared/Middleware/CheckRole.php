<?php

namespace App\Modules\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $allowedRoles = array_values(array_filter(array_map('trim', preg_split('/[|,]/', implode(',', $roles)) ?: [])));

        if (! $user || ! $user->portalIsActive() || ! $user->role || ! in_array($user->role->name, $allowedRoles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
