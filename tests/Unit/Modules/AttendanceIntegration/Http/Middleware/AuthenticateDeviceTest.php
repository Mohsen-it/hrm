<?php

namespace Tests\Unit\Modules\AttendanceIntegration\Http\Middleware;

use Illuminate\Http\Request;
use Modules\AttendanceIntegration\Contracts\AttendanceDeviceInterface;
use Modules\AttendanceIntegration\Contracts\DeviceRepositoryInterface;
use Modules\AttendanceIntegration\Http\Middleware\AuthenticateDevice;
use Tests\TestCase;

class AuthenticateDeviceTest extends TestCase
{
    public function test_rejects_request_without_serial(): void
    {
        $repo = $this->createMock(DeviceRepositoryInterface::class);
        $middleware = new AuthenticateDevice($repo);

        $request = Request::create('/push', 'POST', []);

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(401, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('DEVICE_SERIAL_MISSING', $data['error_code']);
    }

    public function test_rejects_unknown_device(): void
    {
        $repo = $this->createMock(DeviceRepositoryInterface::class);
        $repo->method('findBySerial')->willReturn(null);

        $middleware = new AuthenticateDevice($repo);

        $request = Request::create('/push', 'POST', ['SN' => 'UNKNOWN']);

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(401, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('DEVICE_NOT_FOUND', $data['error_code']);
    }

    public function test_rejects_deactivated_device(): void
    {
        $device = $this->createMock(AttendanceDeviceInterface::class);
        $device->method('getStatus')->willReturn('deactivated');

        $repo = $this->createMock(DeviceRepositoryInterface::class);
        $repo->method('findBySerial')->willReturn($device);

        $middleware = new AuthenticateDevice($repo);

        $request = Request::create('/push', 'POST', ['SN' => 'TEST_SN']);

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(403, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('DEVICE_DEACTIVATED', $data['error_code']);
    }

    public function test_accepts_valid_device_and_sets_attribute(): void
    {
        $device = $this->createMock(AttendanceDeviceInterface::class);
        $device->method('getStatus')->willReturn('online');

        $repo = $this->createMock(DeviceRepositoryInterface::class);
        $repo->method('findBySerial')->willReturn($device);

        $middleware = new AuthenticateDevice($repo);

        $request = Request::create('/push', 'POST', ['SN' => 'VALID_SN']);
        $request->headers->set('Authorization', 'Bearer test_token');

        $response = $middleware->handle($request, function ($req) {
            $this->assertNotNull($req->attributes->get('_resolved_device'));

            return response()->json(['ok' => true]);
        });

        $this->assertSame(200, $response->status());
    }

    public function test_resolves_serial_from_header(): void
    {
        $device = $this->createMock(AttendanceDeviceInterface::class);
        $device->method('getStatus')->willReturn('online');

        $repo = $this->createMock(DeviceRepositoryInterface::class);
        $repo->method('findBySerial')->with('HEADER_SN')->willReturn($device);

        $middleware = new AuthenticateDevice($repo);

        $request = Request::create('/push', 'POST', []);
        $request->headers->set('X-Device-Serial', 'HEADER_SN');

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(200, $response->status());
    }
}
