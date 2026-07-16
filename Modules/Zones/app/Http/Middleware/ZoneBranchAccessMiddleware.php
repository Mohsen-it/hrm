<?php

namespace Modules\Zones\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Zones\Models\Zone;
use Symfony\Component\HttpFoundation\Response;

/**
 * ZoneBranchAccessMiddleware — verifies the user can operate on the
 * branches belonging to the supplied zone.
 *
 * The middleware is intentionally lightweight: it loads the zone (404
 * when missing) and then delegates to {@see ZoneAccessMiddleware}'s
 * permission semantics.
 */
class ZoneBranchAccessMiddleware
{
    public function handle(Request $request, Closure $next, string $permission = 'edit-zones'): Response
    {
        if (! auth()->check()) {
            abort(401);
        }

        if (! auth()->user()->can($permission)) {
            abort(403, __('zones.unauthorized_branches'));
        }

        $zoneId = (int) $request->route('zone');
        if ($zoneId && ! Zone::whereKey($zoneId)->exists()) {
            abort(404);
        }

        return $next($request);
    }
}
