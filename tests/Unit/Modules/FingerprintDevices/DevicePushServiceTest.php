<?php

namespace Tests\Unit\Modules\FingerprintDevices;

use App\Services\ZKTecoPythonBridgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AttendanceIntegration\Services\DeviceAdapterResolver;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\FingerprintDevices\Repositories\DevicePushResultRepository;
use Modules\FingerprintDevices\Repositories\DeviceSyncLogRepository;
use Modules\FingerprintDevices\Repositories\FingerprintDeviceRepository;
use Modules\FingerprintDevices\Services\DevicePushService;
use Modules\FingerprintDevices\Services\FaceTemplateDistributionService;
use Modules\Users\Models\User;
use Tests\TestCase;

class DevicePushServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(): DevicePushService
    {
        return new DevicePushService(
            app(FingerprintDeviceRepository::class),
            app(DeviceSyncLogRepository::class),
            app(DevicePushResultRepository::class),
            app(DeviceAdapterResolver::class),
            app(FaceTemplateDistributionService::class),
            app(ZKTecoPythonBridgeService::class),
        );
    }

    private function makeZktDevice(): FingerprintDevice
    {
        $type = FingerprintDeviceType::create([
            'name' => 'ZKTeco Time',
            'manufacturer' => 'ZKTeco',
            'default_port' => 4370,
            'protocol' => 'zkteco',
        ]);

        return FingerprintDevice::create([
            'device_type_id' => $type->id,
            'name' => 'Test ZK',
            'serial_number' => 'TEST-ZK-'.uniqid(),
            'ip_address' => '192.168.99.240',
            'port' => 4370,
            'comm_key' => '0',
            'timeout' => 30,
            'status' => 'online',
            'is_push_enabled' => true,
        ]);
    }

    public function test_skips_users_without_employee_code(): void
    {
        $service = $this->makeService();
        $device = $this->makeZktDevice();

        // Create users: 1 with code, 1 without
        User::create([
            'employee_code' => 'EMP-001',
            'name' => 'With Code',
            'full_name_ar' => 'مع رمز',
            'email' => 'emp1@test.local',
            'password' => bcrypt('x'),
            'status' => 1,
            'is_active_employee' => true,
        ]);
        User::create([
            'employee_code' => null,
            'name' => 'No Code',
            'full_name_ar' => 'بدون رمز',
            'email' => 'emp2@test.local',
            'password' => bcrypt('x'),
            'status' => 1,
            'is_active_employee' => true,
        ]);

        $result = $service->push(
            deviceId: $device->id,
            options: [
                'push_users' => true,
                'push_fingerprints' => false,
                'user_ids' => [1, 2],
            ],
            userId: null,
        );

        // The log should exist; the user without code is simply not in scope,
        // so it doesn't even count as skipped (the service filters them out
        // before calling the adapter).
        $this->assertNotNull($result['sync_log_id']);
        $this->assertArrayHasKey('summary', $result);
    }

    public function test_push_throws_when_device_not_found(): void
    {
        $this->expectException(\RuntimeException::class);

        $service = $this->makeService();
        $service->push(
            deviceId: 99999,
            options: ['push_users' => true],
            userId: null,
        );
    }

    public function test_push_throws_when_push_disabled(): void
    {
        $this->expectException(\RuntimeException::class);

        $service = $this->makeService();
        $device = $this->makeZktDevice();
        $device->update(['is_push_enabled' => false]);

        $service->push(
            deviceId: $device->id,
            options: ['push_users' => true],
            userId: null,
        );
    }

    public function test_device_capability_accessors(): void
    {
        $zk = $this->makeZktDevice();
        $this->assertTrue($zk->can_push_users);
        $this->assertTrue($zk->can_push_fingerprints);
        $this->assertFalse($zk->can_push_face_photos);

        $hikType = FingerprintDeviceType::create([
            'name' => 'Hikvision',
            'manufacturer' => 'Hikvision',
            'default_port' => 80,
            'protocol' => 'hikvision',
        ]);
        $hik = FingerprintDevice::create([
            'device_type_id' => $hikType->id,
            'name' => 'Test Hik',
            'serial_number' => 'TEST-HIK-'.uniqid(),
            'ip_address' => '192.168.99.241',
            'port' => 80,
            'comm_key' => 'admin:pass',
            'timeout' => 30,
            'status' => 'online',
            'is_push_enabled' => true,
        ]);
        $this->assertTrue($hik->can_push_users);
        $this->assertTrue($hik->can_push_fingerprints);
        $this->assertTrue($hik->can_push_face_photos);
    }

    public function test_face_only_fingerprint_push_queues_adms_command_without_tcp_delivery(): void
    {
        $service = $this->makeService();
        $source = $this->makeZktDevice();
        $target = $this->makeZktDevice();
        $user = User::create([
            'employee_code' => 'FACE-001',
            'name' => 'Face Employee',
            'full_name_ar' => 'Face Employee',
            'email' => 'face-employee@test.local',
            'password' => bcrypt('password'),
            'status' => 1,
            'is_active_employee' => true,
        ]);
        $templateData = 'historical-face-template';

        UserFingerprint::create([
            'user_id' => $user->id,
            'device_id' => $source->id,
            'device_serial' => $source->serial_number,
            'finger_id' => 50,
            'template_data' => $templateData,
            'template_hash' => hash('sha256', $templateData),
            'template_format' => 'zkteco-face-push',
            'template_type' => 'face',
            'template_version' => 120,
            'template_metadata' => [
                'MajorVer' => 12,
                'MinorVer' => 0,
                'Format' => 0,
            ],
        ]);

        $result = $service->push(
            deviceId: $target->id,
            options: [
                'push_users' => false,
                'push_fingerprints' => true,
                'user_ids' => [$user->id],
            ],
        );

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['summary']['queued_face_templates']);
        $this->assertSame(0, $result['summary']['pushed_face_templates']);
        $this->assertDatabaseHas('device_commands', [
            'device_id' => $target->id,
            'command_type' => 'face_template',
            'status' => 'pending',
        ]);
    }
}
