<?php

namespace Modules\FingerprintDevices\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DeviceConnectionMiddleware — rate-limits device connection tests.
 */
class DeviceConnectionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = 'device_connection_'.auth()->id();
        $maxAttempts = (int) config('fingerprintdevices.connection_rate_limit', 10);
        $decayMinutes = (int) config('fingerprintdevices.connection_rate_decay', 1);

        $attempts = cache()->get($key, 0);

        if ($attempts >= $maxAttempts) {
            return response()->json([
                'success' => false,
                'message' => __('fingerprint_devices.rate_limit_exceeded'),
            ], 429);
        }

        cache()->put($key, $attempts + 1, $decayMinutes * 60);

        return $next($request);
    }
}
