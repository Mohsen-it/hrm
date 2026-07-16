<?php

namespace Modules\FingerprintDevices\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeprecatedPushController
{
    private const DEPRECATION_MESSAGE = 'This endpoint is deprecated. Use /api/attendance-integration/push instead.';

    public function attendance(Request $request): JsonResponse
    {
        Log::warning('deprecated_push_endpoint_called', [
            'endpoint' => 'fingerprint-push.attendance',
            'ip' => $request->ip(),
        ]);

        return $this->forward($request, '/api/attendance-integration/push');
    }

    public function adms(Request $request): JsonResponse
    {
        Log::warning('deprecated_push_endpoint_called', [
            'endpoint' => 'fingerprint-push.adms',
            'ip' => $request->ip(),
        ]);

        return $this->forward($request, '/api/attendance-integration/push/adms');
    }

    private function forward(Request $request, string $uri): JsonResponse
    {
        try {
            $forwarded = Request::create(
                $uri,
                $request->method(),
                [],
                $request->cookies->all(),
                [],
                array_merge($request->server->all(), [
                    'HTTP_X_DEVICE_SERIAL' => $request->input('SN', $request->input('serial_number', '')),
                    'HTTP_AUTHORIZATION' => $request->header('Authorization', ''),
                    'HTTP_X_REQUEST_ID' => $request->header('X-Request-Id', 'deprecated-'.uniqid()),
                ]),
                $request->getContent()
            );

            $forwarded->headers->replace($request->headers->all());
            $forwarded->headers->set('X-Device-Serial', $request->input('SN', $request->input('serial_number', '')));
            $forwarded->headers->set('Content-Type', 'application/json');

            $response = app()->handle($forwarded);

            $data = json_decode($response->getContent(), true) ?: [];

            return response()->json(
                array_merge($data, [
                    '_deprecated' => true,
                    '_deprecation_message' => self::DEPRECATION_MESSAGE,
                ]),
                $response->status()
            );
        } catch (\Throwable $e) {
            Log::error('deprecated_push_forward_failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Forwarding failed. Please update device to use new endpoint.',
                '_deprecated' => true,
            ], 500);
        }
    }
}
