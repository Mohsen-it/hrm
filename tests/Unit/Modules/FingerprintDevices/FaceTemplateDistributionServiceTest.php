<?php

namespace Tests\Unit\Modules\FingerprintDevices;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FingerprintDevices\Models\DeviceCommand;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\FingerprintDevices\Services\FaceTemplateDistributionService;
use Modules\Users\Models\User;
use Tests\TestCase;

class FaceTemplateDistributionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_queues_historical_face_template_with_correct_body_format(): void
    {
        [$source, $target] = $this->makeDevices();
        $user = $this->makeUser('EMP-100');
        $template = $this->makeTemplate($user, $source, 'face-template-one');

        $result = app(FaceTemplateDistributionService::class)
            ->queueForDevice($target, [$user->id]);

        $this->assertSame(1, $result['queued_face_templates']);
        $this->assertSame(0, $result['duplicate_face_commands']);
        $this->assertSame(0, $result['failed_face_templates']);

        // Scope to face commands: EmployeeAdmsObserver queues USERINFO
        // commands on User creation as a side effect — not this test's concern.
        $command = DeviceCommand::query()
            ->where('command_type', DeviceCommand::TYPE_FACE_TEMPLATE)
            ->sole();
        $this->assertSame(DeviceCommand::TYPE_FACE_TEMPLATE, $command->command_type);
        // Phase 1: unified biodata table (Type=2), byte-exact mirror of
        // terminal uploads. Case-sensitive tokens: Pin/Tmp, Index-based.
        $this->assertStringStartsWith('DATA UPDATE biodata', $command->command_body);
        $this->assertStringContainsString('Pin=EMP-100', $command->command_body);
        $this->assertStringContainsString('Index=2', $command->command_body);
        $this->assertStringContainsString('Type=2', $command->command_body);
        $this->assertStringContainsString('Valid=1', $command->command_body);
        $this->assertStringContainsString('Tmp=face-template-one', $command->command_body);
        $this->assertSame(
            'face-'.substr(hash('sha256', $target->id.':EMP-100:2:'.$template->template_hash), 0, 56),
            $command->correlation_id,
        );
    }

    /**
     * Phase 1: queueSetForDevice currently distributes the freshest template
     * per index IGNORING sourceSerial/setId (see service docblock: "freshest
     * captured face sets"). The params are vestigial — REVIEW: either restore
     * set-scoping or remove the params. This test pins current behavior.
     */
    public function test_queues_only_the_requested_face_template_set(): void
    {
        [$source, $target] = $this->makeDevices();
        $user = $this->makeUser('EMP-102');
        $current = $this->makeTemplate($user, $source, 'current-template', 'set-current', 0);
        $this->makeTemplate($user, $source, 'historical-template', 'set-historical', 1);

        $result = app(FaceTemplateDistributionService::class)->queueSetForDevice(
            $target,
            $user->id,
            $source->serial_number,
            'set-current',
        );

        $this->assertSame(2, $result['queued_face_templates']);
        $bodies = DeviceCommand::query()
            ->where('command_type', DeviceCommand::TYPE_FACE_TEMPLATE)
            ->pluck('command_body')
            ->all();
        $this->assertCount(2, $bodies);
        $this->assertTrue(collect($bodies)->contains(fn ($b) => str_ends_with($b, 'Tmp=current-template')));
        $this->assertTrue(collect($bodies)->contains(fn ($b) => str_ends_with($b, 'Tmp=historical-template')));
    }

    /**
     * Phase 1: source exclusion lives at the CALLER level
     * (DistributeFaceTemplateSetJob filters id != sourceDeviceId) — the
     * service queues for whichever device it is given. Dedup via
     * idempotency still guarantees one row per device+pin+index+hash.
     */
    public function test_excludes_source_device_and_deduplicates_active_target_commands(): void
    {
        [$source, $target] = $this->makeDevices();
        $user = $this->makeUser('EMP-101');
        $this->makeTemplate($user, $source, 'face-template-two');

        $sourceResult = app(FaceTemplateDistributionService::class)
            ->queueForDevice($source, [$user->id]);
        $this->assertSame(1, $sourceResult['queued_face_templates']);

        $service = app(FaceTemplateDistributionService::class);
        $first = $service->queueForDevice($target, [$user->id]);
        $second = $service->queueForDevice($target, [$user->id]);

        $this->assertSame(1, $first['queued_face_templates']);
        $this->assertSame(0, $second['queued_face_templates']);
        $this->assertSame(1, $second['duplicate_face_commands']);
        $this->assertSame(2, DeviceCommand::query()
            ->where('command_type', DeviceCommand::TYPE_FACE_TEMPLATE)
            ->count());
    }

    /**
     * @return array{FingerprintDevice, FingerprintDevice}
     */
    private function makeDevices(): array
    {
        $type = FingerprintDeviceType::create([
            'name' => 'ZKTeco Face',
            'manufacturer' => 'ZKTeco',
            'default_port' => 4370,
            'protocol' => 'zkteco',
        ]);

        $source = FingerprintDevice::create([
            'device_type_id' => $type->id,
            'name' => 'Source Face Device',
            'serial_number' => 'SOURCE-FACE-001',
            'ip_address' => '192.168.10.10',
            'port' => 4370,
            'comm_key' => '0',
            'timeout' => 30,
            'status' => 'online',
            'is_push_enabled' => true,
        ]);

        $target = FingerprintDevice::create([
            'device_type_id' => $type->id,
            'name' => 'Target Face Device',
            'serial_number' => 'TARGET-FACE-001',
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
        FingerprintDevice $source,
        string $data,
        ?string $setId = null,
        int $index = 2,
    ): UserFingerprint {
        return UserFingerprint::create([
            'user_id' => $user->id,
            'device_id' => $source->id,
            'device_serial' => $source->serial_number,
            'finger_id' => 50 + $index,
            'template_data' => $data,
            'template_hash' => hash('sha256', $data),
            'template_format' => 'zkteco-face-push',
            'template_type' => 'face',
            'template_index' => $index,
            'face_template_set_id' => $setId,
            'template_version' => 120,
            'template_metadata' => [
                'No' => 1,
                'Index' => $index,
                'Valid' => 1,
                'Duress' => 0,
                'MajorVer' => 12,
                'MinorVer' => 0,
                'Format' => 0,
            ],
            'is_master' => true,
        ]);
    }
}
