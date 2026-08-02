<?php

namespace Tests\Unit\Modules\FingerprintDevices;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FingerprintDevices\Jobs\DistributeFaceTemplateSetJob;
use Modules\FingerprintDevices\Models\DeviceCommand;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\FingerprintDevices\Services\DeviceCommandService;
use Modules\FingerprintDevices\Services\FaceTemplateDistributionService;
use Modules\Users\Models\User;
use Tests\TestCase;

class DistributeFaceTemplateSetJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_queues_the_employee_before_every_component_on_each_target(): void
    {
        [$source, $target] = $this->makeDevices();
        $user = $this->makeUser();

        foreach (range(0, 14) as $index) {
            $data = "face-component-{$index}";
            UserFingerprint::create([
                'user_id' => $user->id,
                'device_id' => $source->id,
                'device_serial' => $source->serial_number,
                'finger_id' => 50 + $index,
                'template_data' => $data,
                'template_hash' => hash('sha256', $data),
                'template_format' => 'zkteco-face-push',
                'template_type' => 'face',
                'template_index' => $index,
                'face_template_set_id' => 'enrollment-20010',
                'template_version' => 120,
                'template_metadata' => [
                    'No' => 0,
                    'Index' => $index,
                    'Valid' => 1,
                    'Duress' => 0,
                    'MajorVer' => 12,
                    'MinorVer' => 0,
                    'Format' => 0,
                ],
            ]);
        }

        $job = new DistributeFaceTemplateSetJob(
            $user->id,
            $source->id,
            $source->serial_number,
            'enrollment-20010',
        );
        $job->handle(
            app(FaceTemplateDistributionService::class),
            app(DeviceCommandService::class),
        );

        $commands = DeviceCommand::query()
            ->where('device_id', $target->id)
            ->orderBy('priority')
            ->orderBy('created_at')
            ->get();

        $this->assertCount(16, $commands);
        $this->assertSame(DeviceCommand::TYPE_USER_UPDATE, $commands->first()->command_type);
        $this->assertSame(3, $commands->first()->priority);
        $this->assertCount(15, $commands->where('command_type', DeviceCommand::TYPE_FACE_TEMPLATE));
        $this->assertSame(range(0, 14), $commands
            ->where('command_type', DeviceCommand::TYPE_FACE_TEMPLATE)
            ->map(fn (DeviceCommand $command) => (int) preg_replace('/.*Index=(\d+).*/', '$1', $command->command_body))
            ->values()
            ->all());
        $this->assertSame(0, DeviceCommand::query()->where('device_id', $source->id)->count());
    }

    /** @return array{FingerprintDevice, FingerprintDevice} */
    private function makeDevices(): array
    {
        $type = FingerprintDeviceType::create([
            'name' => 'ZKTeco Face',
            'manufacturer' => 'ZKTeco',
            'default_port' => 4370,
            'protocol' => 'zkteco',
        ]);

        $devices = [];
        foreach (['SOURCE-FACE-20010', 'TARGET-FACE-20010'] as $index => $serial) {
            $devices[] = FingerprintDevice::create([
                'device_type_id' => $type->id,
                'name' => $serial,
                'serial_number' => $serial,
                'ip_address' => '192.168.30.'.($index + 10),
                'port' => 4370,
                'comm_key' => '0',
                'timeout' => 30,
                'status' => 'online',
                'is_push_enabled' => true,
            ]);
        }

        return $devices;
    }

    private function makeUser(): User
    {
        return User::create([
            'employee_code' => '20010',
            'name' => 'Employee 20010',
            'full_name_ar' => 'Employee 20010',
            'email' => 'employee-20010@test.local',
            'password' => bcrypt('password'),
            'status' => 1,
            'is_active_employee' => true,
        ]);
    }
}
