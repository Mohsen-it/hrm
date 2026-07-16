<?php

namespace Modules\FingerprintDevices\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DeviceTypeAccessMiddleware — verifies the user can manage device types.
 */
class DeviceTypeAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            abort(401);
        }

        if (! auth()->user()->can('view-fingerprint-device-types')) {
            abort(403, __('fingerprint_devices.unauthorized'));
        }

        return $next($request);
    }
}
