<?php

namespace Tests\Unit\Modules\FingerprintDevices;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FingerprintDevices\Models\DeviceCommand;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\FingerprintDevices\Services\FingerprintTemplateDistributionService;
use Modules\Users\Models\User;
use Tests\TestCase;

class FingerprintTemplateDistributionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_queues_fingerprint_template_with_correct_body_format(): void
    {
        [, $target] = $this->makeDevices();
        $user = $this->makeUser('EMP-FP-100');
        $template = $this->makeTemplate($user, 'fp-template-one', 2);

        $result = app(FingerprintTemplateDistributionService::class)
            ->queueForDevice($target, [$user->id]);

        $this->assertSame(1, $result['queued_fp_templates']);
        $this->assertSame(0, $result['duplicate_fp_commands']);
        $this->assertSame(0, $result['failed_fp_templates']);

        $command = DeviceCommand::query()
            ->where('command_type', DeviceCommand::TYPE_FP_TEMPLATE)
            ->sole();
        $this->assertSame(DeviceCommand::TYPE_FP_TEMPLATE, $command->command_type);
        $this->assertSame($target->id, $command->device_id);
        // Classic FINGERTMP table (not unified biodata): PIN/FID/Size/Valid/TMP.
        $this->assertStringStartsWith('DATA UPDATE FINGERTMP', $command->command_body);
        $this->assertStringContainsString('PIN=EMP-FP-100', $command->command_body);
        $this->assertStringContainsString('FID=2', $command->command_body);
        $this->assertStringContainsString('Size='.strlen('fp-template-one'), $command->command_body);
        $this->assertStringContainsString('Valid=1', $command->command_body);
        $this->assertStringContainsString('TMP=fp-template-one', $command->command_body);
        $this->assertStringNotContainsString('Type=', $command->command_body);
        $this->assertSame(
            'fp3-'.substr(hash('sha256', $target->id.':EMP-FP-100:2:'.$template->template_hash), 0, 56),
            $command->correlation_id,
        );
    }

    public function test_queues_only_freshest_template_per_finger_index(): void
    {
        [, $target] = $this->makeDevices();
        $user = $this->makeUser('EMP-FP-101');
        $this->makeTemplate($user, 'old-template', 0, '-2 hours');
        $this->makeTemplate($user, 'new-template', 0, '-1 hour');
        $this->makeTemplate($user, 'other-finger', 1, '-1 hour');

        $result = app(FingerprintTemplateDistributionService::class)
            ->queueForDevice($target, [$user->id]);

        $this->assertSame(2, $result['queued_fp_templates']);
        $this->assertSame(2, DeviceCommand::query()->where('command_type', DeviceCommand::TYPE_FP_TEMPLATE)->count());
        $this->assertTrue(
            DeviceCommand::query()->where('command_type', DeviceCommand::TYPE_FP_TEMPLATE)->where('command_body', 'like', '%TMP=new-template')->exists()
        );
        $this->assertFalse(
            DeviceCommand::query()->where('command_type', DeviceCommand::TYPE_FP_TEMPLATE)->where('command_body', 'like', '%TMP=old-template')->exists()
        );
    }

    public function test_second_run_reports_duplicates_without_new_rows(): void
    {
        [, $target] = $this->makeDevices();
        $user = $this->makeUser('EMP-FP-102');
        $this->makeTemplate($user, 'fp-template-dup', 3);

        $service = app(FingerprintTemplateDistributionService::class);
        $first = $service->queueForDevice($target, [$user->id]);
        $second = $service->queueForDevice($target, [$user->id]);

        $this->assertSame(1, $first['queued_fp_templates']);
        $this->assertSame(0, $second['queued_fp_templates']);
        $this->assertSame(1, $second['duplicate_fp_commands']);
        $this->assertSame(1, DeviceCommand::query()->where('command_type', DeviceCommand::TYPE_FP_TEMPLATE)->count());
    }

    public function test_queue_pin_for_unknown_pin_is_skipped(): void
    {
        [, $target] = $this->makeDevices();

        $result = app(FingerprintTemplateDistributionService::class)
            ->queuePinForDevice($target, 'NO-SUCH-PIN');

        $this->assertSame(1, $result['skipped_fp_templates']);
        $this->assertSame(0, DeviceCommand::query()->where('command_type', DeviceCommand::TYPE_FP_TEMPLATE)->count());
    }

    public function test_unsupported_device_type_is_rejected(): void
    {
        $type = FingerprintDeviceType::create([
            'name' => 'Other Vendor',
            'manufacturer' => 'Hikvision',
            'default_port' => 4370,
            'protocol' => 'hikvision',
        ]);
        $device = FingerprintDevice::create([
            'device_type_id' => $type->id,
            'name' => 'Other Device',
            'serial_number' => 'OTHER-001',
            'ip_address' => '192.168.10.20',
            'port' => 4370,
            'comm_key' => '0',
            'timeout' => 30,
            'status' => 'online',
            'is_push_enabled' => true,
        ]);
        $user = $this->makeUser('EMP-FP-103');
        $this->makeTemplate($user, 'fp-template-x', 0);

        $result = app(FingerprintTemplateDistributionService::class)
            ->queueForDevice($device, [$user->id]);

        $this->assertSame(1, $result['failed_fp_templates']);
        $this->assertSame(0, DeviceCommand::query()->where('command_type', DeviceCommand::TYPE_FP_TEMPLATE)->count());
    }

    /**
     * @return array{FingerprintDevice, FingerprintDevice}
     */
    private function makeDevices(): array
    {
        $type = FingerprintDeviceType::create([
            'name' => 'ZKTeco FP',
            'manufacturer' => 'ZKTeco',
            'default_port' => 4370,
            'protocol' => 'zkteco',
        ]);

        $source = FingerprintDevice::create([
            'device_type_id' => $type->id,
            'name' => 'Source FP Device',
            'serial_number' => 'SOURCE-FP-001',
            'ip_address' => '192.168.10.10',
            'port' => 4370,
            'comm_key' => '0',
            'timeout' => 30,
            'status' => 'online',
            'is_push_enabled' => true,
        ]);

        $target = FingerprintDevice::create([
            'device_type_id' => $type->id,
            'name' => 'Target FP Device',
            'serial_number' => 'TARGET-FP-001',
            'ip_address' => '192.168.10.11',
            'port' => 4370,
            'comm_key' => '0',
            'timeout' => 30,
            'status' => 'online',
            'is_push_enabled' => true,
        ]);

        return [$source, $target];
    }

    private function makeUser(string $employeeCode): User
    {
        return User::create([
            'employee_code' => $employeeCode,
            'name' => $employeeCode,
            'full_name_ar' => $employeeCode,
            'email' => strtolower($employeeCode).'@test.local',
            'password' => bcrypt('password'),
            'status' => 1,
            'is_active_employee' => true,
        ]);
    }

    private function makeTemplate(
        User $user,
        string $data,
        int $index = 0,
        string $capturedAt = '-1 hour',
    ): UserFingerprint {
        return UserFingerprint::create([
            'user_id' => $user->id,
            'device_id' => null,
            'device_serial' => 'SOURCE-FP-001',
            'finger_id' => $index,
            'template_data' => $data,
            'template_hash' => hash('sha256', $data),
            'template_format' => 'zkteco-fp-push',
            'template_type' => 'fingerprint',
            'template_index' => $index,
            'template_version' => 100,
            'template_metadata' => [
                'No' => 0,
                'Index' => $index,
                'Valid' => 1,
                'Duress' => 0,
                'MajorVer' => 10,
                'MinorVer' => 0,
            ],
            'is_master' => true,
            'captured_at' => now()->modify($capturedAt),
        ]);
    }
}
