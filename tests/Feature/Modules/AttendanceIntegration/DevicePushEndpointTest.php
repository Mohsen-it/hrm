<?php

namespace Tests\Feature\Modules\AttendanceIntegration;

use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;
use Tests\TestCase;

class DevicePushEndpointTest extends TestCase
{
    private FingerprintDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $type = FingerprintDeviceType::create([
            'name' => 'iClock680',
            'manufacturer' => 'ZKTeco',
            'protocol' => 'ADMS',
            'default_port' => 4370,
            'supports_fingerprint' => true,
            'supports_face' => false,
            'max_fingerprints' => 10,
            'max_users' => 1000,
            'is_active' => true,
        ]);

        $this->device = FingerprintDevice::create([
            'device_type_id' => $type->id,
            'name' => 'Test Device',
            'serial_number' => 'TEST_SN_001',
            'ip_address' => '192.168.1.100',
            'port' => 4370,
            'comm_key' => 0,
            'connection_type' => 'tcp',
            'timeout' => 30,
            'status' => 'online',
            'is_push_enabled' => true,
        ]);
    }

    public function test_push_endpoint_accepts_without_serial(): void
    {
        $response = $this->postJson(route('attendance-integration.push'), [
            'user_id' => 'EMP001',
            'timestamp' => '2026-01-15 08:00:00',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_push_endpoint_accepts_unknown_serial(): void
    {
        $response = $this->postJson(route('attendance-integration.push'), [
            'SN' => 'UNKNOWN_SERIAL',
            'user_id' => 'EMP001',
            'timestamp' => '2026-01-15 08:00:00',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_push_endpoint_accepts_valid_payload(): void
    {
        $response = $this->postJson(route('attendance-integration.push'), [
            'SN' => 'TEST_SN_001',
            'user_id' => 'NONEXISTENT',
            'timestamp' => '2026-01-15 08:00:00',
            'punch_type' => 'check_in',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 1,
            ]);
    }

    public function test_push_endpoint_via_header_serial(): void
    {
        $response = $this->postJson(route('attendance-integration.push'), [
            'user_id' => 'NONEXISTENT',
            'timestamp' => '2026-01-15 08:00:00',
        ], [
            'X-Device-Serial' => 'TEST_SN_001',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_adms_push_endpoint_works(): void
    {
        $response = $this->postJson(route('attendance-integration.push.adms'), [
            'SN' => 'TEST_SN_001',
            'Body' => "ATT\t\tNONEXISTENT\t2026-01-15 08:00:00\t0\n",
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 1,
            ]);
    }

    public function test_push_endpoint_batch_punches(): void
    {
        $response = $this->postJson(route('attendance-integration.push'), [
            'SN' => 'TEST_SN_001',
            'attendance' => [
                ['user_id' => 'NONEXISTENT1', 'timestamp' => '2026-01-15 08:00:00', 'status' => 0],
                ['user_id' => 'NONEXISTENT2', 'timestamp' => '2026-01-15 08:01:00', 'status' => 0],
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 2,
            ]);
    }

    public function test_live_snapshot_endpoint(): void
    {
        $response = $this->getJson(route('attendance-integration.live.snapshot'));

        $response->assertOk()
            ->assertJsonStructure([
                'punches',
                'server_time',
            ]);
    }
}
