<?php

namespace Modules\Zones\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ZoneAccessMiddleware — gates the supplied route on the
 * `view-zones` permission. The route parameter may be a Zone id
 * (`zone`) or absent (the middleware then only checks the
 * permission).
 */
class ZoneAccessMiddleware
{
    public function handle(Request $request, Closure $next, string $permission = 'view-zones'): Response
    {
        if (! auth()->check()) {
            abort(401);
        }

        if (! auth()->user()->can($permission)) {
            abort(403, __('zones.unauthorized'));
        }

        return $next($request);
    }
}
