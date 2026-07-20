<?php

namespace Tests\Feature\Modules\FingerprintDevices;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FingerprintDevices\Models\DeviceSyncLog;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;
use Modules\Users\Models\User;
use Tests\TestCase;

class DevicePushControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user with permission
        $user = User::create([
            'employee_code' => 'ADMIN-001',
            'name' => 'Admin',
            'full_name_ar' => 'مدير',
            'email' => 'admin@test.local',
            'password' => bcrypt('password'),
            'status' => 1,
            'is_active_employee' => true,
        ]);
        $user->givePermissionTo('edit-fingerprint-devices');
        $this->actingAs($user);
    }

    private function makeDevice(): FingerprintDevice
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

    public function test_push_validates_device_id_required(): void
    {
        $this->postJson(route('fingerprint-devices.sync.push'), [
            'options' => ['push_users' => true],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['device_id']);
    }

    public function test_push_validates_options_required(): void
    {
        $device = $this->makeDevice();

        $this->postJson(route('fingerprint-devices.sync.push'), [
            'device_id' => $device->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['options']);
    }

    public function test_push_validates_at_least_one_option(): void
    {
        $device = $this->makeDevice();

        $this->postJson(route('fingerprint-devices.sync.push'), [
            'device_id' => $device->id,
            'options' => [
                'push_users' => false,
                'push_fingerprints' => false,
                'push_face_photos' => false,
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['options']);
    }

    public function test_log_status_returns_404_for_missing_log(): void
    {
        $this->getJson(route('fingerprint-devices.sync.log-status', ['logId' => 99999]))
            ->assertStatus(404);
    }

    public function test_log_status_returns_log_details(): void
    {
        $device = $this->makeDevice();
        $log = DeviceSyncLog::create([
            'device_id' => $device->id,
            'direction' => 'push',
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'duration_seconds' => 60.0,
            'totals' => ['pushed_users' => 5],
        ]);

        $this->getJson(route('fingerprint-devices.sync.log-status', ['logId' => $log->id]))
            ->assertOk()
            ->assertJsonPath('id', $log->id)
            ->assertJsonPath('direction', 'push')
            ->assertJsonPath('status', 'completed');
    }
}
