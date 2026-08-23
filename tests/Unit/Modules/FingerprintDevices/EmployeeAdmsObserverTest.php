<?php

namespace Tests\Unit\Modules\FingerprintDevices;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FingerprintDevices\Models\DeviceCommand;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;
use Modules\Users\Models\User;
use Tests\TestCase;

class EmployeeAdmsObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_create_queues_userinfo_create_on_all_zkteco_devices(): void
    {
        $this->makeDevices();

        $user = User::create([
            'employee_code' => '90001',
            'name' => 'Observer Test',
            'full_name_ar' => 'اختبار المراقب',
            'email' => 'obs-test@example.test',
            'status' => 1,
            'is_active_employee' => 1,
            'password' => bcrypt('secret'),
        ]);

        $commands = DeviceCommand::query()
            ->where('command_type', DeviceCommand::TYPE_USER_CREATE)
            ->where('command_body', 'like', "%{$user->employee_code}%")
            ->get();

        $this->assertCount(1, $commands);
        $this->assertStringStartsWith('DATA UPDATE USERINFO PIN=90001', $commands->first()->command_body);
        $this->assertSame(3, $commands->first()->priority);
    }

    public function test_user_update_queues_userinfo_update_when_name_changes(): void
    {
        $this->makeDevices();

        $user = User::create([
            'employee_code' => '90001',
            'name' => 'Before',
            'email' => 'obs-test@example.test',
            'status' => 1,
            'is_active_employee' => 1,
            'password' => bcrypt('secret'),
        ]);

        // Re-fetch on a fresh instance, as the real flow does: a later edit
        // must queue an UPDATE (create is only queued for brand-new records).
        User::find($user->id)->update(['name' => 'After']);

        $command = DeviceCommand::query()
            ->where('command_type', DeviceCommand::TYPE_USER_UPDATE)
            ->latest('id')
            ->first();

        $this->assertNotNull($command);
        $this->assertStringContainsString('PIN=90001', $command->command_body);
    }

    public function test_user_delete_queues_delete_even_for_inactive_employee(): void
    {
        $this->makeDevices();

        $user = User::create([
            'employee_code' => '90001',
            'name' => 'Observer Test',
            'email' => 'obs-test@example.test',
            'status' => 0,
            'is_active_employee' => 0,
            'password' => bcrypt('secret'),
        ]);

        $user->delete();

        $command = DeviceCommand::query()
            ->where('command_type', DeviceCommand::TYPE_USER_DELETE)
            ->latest('id')
            ->first();

        $this->assertNotNull($command);
        $this->assertSame('DATA DELETE USERINFO PIN=90001', $command->command_body);
        $this->assertSame(2, $command->priority);
    }

    private function makeDevices(): void
    {
        $zk = FingerprintDeviceType::create([
            'name' => 'ZKTeco Face',
            'manufacturer' => 'ZKTeco',
            'default_port' => 4370,
            'protocol' => 'zkteco',
        ]);

        FingerprintDevice::create([
            'device_type_id' => $zk->id,
            'name' => 'ZKTeco Gate',
            'serial_number' => 'OBS-ZK-001',
            'ip_address' => '192.168.30.10',
            'port' => 4370,
            'comm_key' => '0',
            'timeout' => 30,
            'status' => 'online',
            'is_push_enabled' => true,
        ]);

        $hik = FingerprintDeviceType::create([
            'name' => 'Hikvision Door',
            'manufacturer' => 'Hikvision',
            'default_port' => 8000,
            'protocol' => 'hikvision',
        ]);

        FingerprintDevice::create([
            'device_type_id' => $hik->id,
            'name' => 'Hikvision Gate',
            'serial_number' => 'OBS-HIK-001',
            'ip_address' => '192.168.30.11',
            'port' => 8000,
            'comm_key' => '0',
            'timeout' => 30,
            'status' => 'online',
            'is_push_enabled' => true,
        ]);
    }
}
