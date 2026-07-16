<?php

namespace Modules\AttendanceIntegration\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\AttendanceIntegration\Contracts\DeviceRepositoryInterface;

class AuthenticateDevice
{
    public function __construct(
        private DeviceRepositoryInterface $deviceRepository,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $serialNumber = $this->resolveSerial($request);

        if (! $serialNumber) {
            return response()->json([
                'success' => false,
                'message' => 'Missing device serial',
                'error_code' => 'DEVICE_SERIAL_MISSING',
            ], 401);
        }

        $device = $this->deviceRepository->findBySerial((string) $serialNumber);

        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => 'Unknown device',
                'error_code' => 'DEVICE_NOT_FOUND',
            ], 401);
        }

        if ($device->getStatus() === 'deactivated') {
            return response()->json([
                'success' => false,
                'message' => 'Device is deactivated',
                'error_code' => 'DEVICE_DEACTIVATED',
            ], 403);
        }

        $token = $request->bearerToken()
            ?? $request->header('X-Device-Token')
            ?? $request->input('api_token');

        $deviceToken = $device->getApiToken();

        if ($deviceToken !== null && $deviceToken !== '') {
            if ($token === null || $token === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                    'error_code' => 'DEVICE_TOKEN_MISSING',
                ], 401);
            }

            if (! $this->validateToken((string) $token, $deviceToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid device token',
                    'error_code' => 'DEVICE_TOKEN_INVALID',
                ], 403);
            }
        }

        $request->attributes->set('_resolved_device', $device);

        return $next($request);
    }

    private function validateToken(string $provided, string $stored): bool
    {
        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2b$')) {
            return Hash::check($provided, $stored);
        }

        return hash_equals($stored, $provided);
    }

    private function resolveSerial(Request $request): ?string
    {
        return $request->input('SN')
            ?? $request->header('X-Device-Serial')
            ?? $request->header('SN')
            ?? $request->input('serial_number');
    }
}
