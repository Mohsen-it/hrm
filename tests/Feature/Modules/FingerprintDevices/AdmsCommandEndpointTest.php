<?php

namespace Tests\Feature\Modules\FingerprintDevices;

use Illuminate\Support\Facades\Cache;
use Modules\FingerprintDevices\Http\Controllers\AdmsCommandController;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;
use ReflectionMethod;
use Tests\TestCase;

class AdmsCommandEndpointTest extends TestCase
{
    public function test_adms_command_route_is_registered(): void
    {
        $this->getJson('/api/adms/commands')
            ->assertBadRequest()
            ->assertJson([
                'success' => false,
                'error' => 'SN required',
            ]);
    }

    public function test_device_lookup_cache_stores_a_scalar_id_instead_of_an_eloquent_model(): void
    {
        $type = FingerprintDeviceType::create([
            'name' => 'ADMS Test',
            'manufacturer' => 'ZKTeco',
            'protocol' => 'ADMS',
            'default_port' => 4370,
            'is_active' => true,
        ]);

        $device = FingerprintDevice::create([
            'device_type_id' => $type->id,
            'name' => 'ADMS Cache Device',
            'serial_number' => 'ADMS-CACHE-001',
            'ip_address' => '192.168.1.200',
            'port' => 4370,
            'comm_key' => '0',
            'connection_type' => 'tcp',
            'timeout' => 30,
            'status' => 'online',
            'is_push_enabled' => true,
        ]);

        $controller = app(AdmsCommandController::class);
        $findDevice = new ReflectionMethod($controller, 'findDeviceBySerial');
        $cacheKey = 'adms:device:serial:'.$device->serial_number;

        Cache::put($cacheKey, $device, 60);

        $resolvedDevice = $findDevice->invoke($controller, $device->serial_number);

        $cachedDeviceId = Cache::get($cacheKey);

        $this->assertInstanceOf(FingerprintDevice::class, $resolvedDevice);
        $this->assertSame($device->id, $resolvedDevice->id);
        $this->assertIsInt($cachedDeviceId);
        $this->assertSame($device->id, $cachedDeviceId);

        $cachedDevice = $findDevice->invoke($controller, $device->serial_number);

        $this->assertInstanceOf(FingerprintDevice::class, $cachedDevice);
        $this->assertSame($device->id, $cachedDevice->id);
    }
}
