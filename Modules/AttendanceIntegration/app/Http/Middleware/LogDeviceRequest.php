<?php

namespace Modules\AttendanceIntegration\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogDeviceRequest
{
    public function handle(Request $request, Closure $next): mixed
    {
        $correlationId = $request->header('X-Request-Id')
            ?? $request->header('X-Correlation-Id')
            ?? (string) Str::uuid();

        $request->attributes->set('_correlation_id', $correlationId);

        $serial = $request->input('SN')
            ?? $request->header('X-Device-Serial')
            ?? $request->input('serial_number')
            ?? 'unknown';

        Log::channel('attendance_push')->info('device_push_request_received', [
            'correlation_id' => $correlationId,
            'device_serial' => $serial,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'method' => $request->method(),
            'payload_size' => strlen((string) json_encode($request->all())),
            'timestamp' => now()->toIso8601String(),
        ]);

        $start = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $start) * 1000, 2);

        Log::channel('attendance_push')->info('device_push_request_completed', [
            'correlation_id' => $correlationId,
            'device_serial' => $serial,
            'status' => $response->status(),
            'duration_ms' => $duration,
            'timestamp' => now()->toIso8601String(),
        ]);

        $response->headers->set('X-Correlation-Id', $correlationId);
        $response->headers->set('X-Response-Time-Ms', (string) $duration);

        return $response;
    }
}
