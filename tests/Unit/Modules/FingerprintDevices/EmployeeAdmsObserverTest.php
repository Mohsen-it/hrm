<?php

namespace Tests\Unit\Modules\FingerprintDevices;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\FingerprintDevices\Jobs\SyncUserToDeviceViaBridgeJob;
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
        // must propagate. When the initial create is still pending, the
        // edit merges into it (single user entry on the terminal); once
        // delivered, edits queue as user_update rows.
        User::find($user->id)->update(['name' => 'After']);

        $command = DeviceCommand::query()
            ->whereIn('command_type', [DeviceCommand::TYPE_USER_CREATE, DeviceCommand::TYPE_USER_UPDATE])
            ->where('command_body', 'like', '%PIN=90001%')
            ->latest('id')
            ->first();

        $this->assertNotNull($command);
        $this->assertStringContainsString('PIN=90001', $command->command_body);
        $this->assertStringContainsString('After', $command->command_body);
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

    public function test_user_create_uses_explicit_device_privilege_override(): void
    {
        $this->makeDevices();

        User::create([
            'employee_code' => '90002',
            'name' => 'Device Admin',
            'email' => 'obs-admin@example.test',
            'status' => 1,
            'is_active_employee' => 1,
            'device_privilege' => User::DEVICE_PRIVILEGE_ADMIN,
            'password' => bcrypt('secret'),
        ]);

        $command = DeviceCommand::query()
            ->whereIn('command_type', [DeviceCommand::TYPE_USER_CREATE, DeviceCommand::TYPE_USER_UPDATE])
            ->where('command_body', 'like', '%PIN=90002%')
            ->latest('id')
            ->first();

        $this->assertNotNull($command);
        $this->assertStringContainsString('Privilege=14', $command->command_body);
    }

    public function test_user_create_defaults_to_member_privilege(): void
    {
        $this->makeDevices();

        User::create([
            'employee_code' => '90003',
            'name' => 'Regular Member',
            'email' => 'obs-member@example.test',
            'status' => 1,
            'is_active_employee' => 1,
            'password' => bcrypt('secret'),
        ]);

        $command = DeviceCommand::query()
            ->whereIn('command_type', [DeviceCommand::TYPE_USER_CREATE, DeviceCommand::TYPE_USER_UPDATE])
            ->where('command_body', 'like', '%PIN=90003%')
            ->latest('id')
            ->first();

        $this->assertNotNull($command);
        $this->assertStringContainsString('Privilege=0', $command->command_body);
    }

    public function test_device_privilege_change_propagates_to_devices(): void
    {
        $this->makeDevices();

        $user = User::create([
            'employee_code' => '90004',
            'name' => 'Promoted Member',
            'email' => 'obs-promote@example.test',
            'status' => 1,
            'is_active_employee' => 1,
            'password' => bcrypt('secret'),
        ]);

        User::find($user->id)->update(['device_privilege' => User::DEVICE_PRIVILEGE_ADMIN]);

        $command = DeviceCommand::query()
            ->whereIn('command_type', [DeviceCommand::TYPE_USER_CREATE, DeviceCommand::TYPE_USER_UPDATE])
            ->where('command_body', 'like', '%PIN=90004%')
            ->latest('id')
            ->first();

        $this->assertNotNull($command);
        $this->assertStringContainsString('Privilege=14', $command->command_body);
    }

    public function test_device_privilege_resolver_falls_back_safely(): void
    {
        $member = new User(['id' => 99901, 'device_privilege' => null]);
        $this->assertSame(User::DEVICE_PRIVILEGE_MEMBER, $member->devicePrivilege());

        $explicit = new User(['id' => 99902, 'device_privilege' => 14]);
        $this->assertSame(User::DEVICE_PRIVILEGE_ADMIN, $explicit->devicePrivilege());

        // Out-of-range stored values never reach the terminal.
        $bogus = new User(['id' => 99903, 'device_privilege' => 5]);
        $this->assertSame(User::DEVICE_PRIVILEGE_MEMBER, $bogus->devicePrivilege());

        $superAdmin = new User;
        $superAdmin->id = User::SUPER_ADMIN_ID;
        $this->assertSame(User::DEVICE_PRIVILEGE_ADMIN, $superAdmin->devicePrivilege());
    }

    public function test_admin_create_uses_both_channels_when_both_enabled(): void
    {
        config()->set('fingerprintdevices.push_user_via', 'both');
        Queue::fake();
        $this->makeDevices();

        User::create([
            'employee_code' => '90005',
            'name' => 'Dual Channel Admin',
            'email' => 'obs-dual@example.test',
            'status' => 1,
            'is_active_employee' => 1,
            'device_privilege' => User::DEVICE_PRIVILEGE_ADMIN,
            'password' => bcrypt('secret'),
        ]);

        // ADMS channel: identity command carries the privilege.
        $command = DeviceCommand::query()
            ->whereIn('command_type', [DeviceCommand::TYPE_USER_CREATE, DeviceCommand::TYPE_USER_UPDATE])
            ->where('command_body', 'like', '%PIN=90005%')
            ->latest('id')
            ->first();
        $this->assertNotNull($command);
        $this->assertStringContainsString('Privilege=14', $command->command_body);

        // Bridge channel: TCP job enforces what ADMS cannot on this fleet.
        Queue::assertPushed(SyncUserToDeviceViaBridgeJob::class, function (SyncUserToDeviceViaBridgeJob $job): bool {
            return $job->pin === '90005' && $job->privilege === User::DEVICE_PRIVILEGE_ADMIN;
        });
    }

    public function test_bridge_job_retries_across_connectivity_gaps(): void
    {
        $job = new SyncUserToDeviceViaBridgeJob(1, '90005', 'Name', 14);

        $this->assertSame(6, $job->tries);
        $this->assertSame([30, 120, 600, 1800, 3600], $job->backoff);
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
