<?php

namespace Tests\Unit\Modules\FingerprintDevices;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FingerprintDevices\Models\DeviceCommand;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;
use Modules\FingerprintDevices\Services\DeviceCommandService;
use Tests\TestCase;

class DeviceCommandServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_target_device_can_acknowledge_a_command(): void
    {
        [$target, $other] = $this->makeDevices();
        $service = app(DeviceCommandService::class);
        $command = $service->queueFaceTemplate(
            $target->id,
            'EMP-200',
            'template-data',
            [
                'no' => 1,
                'index' => 0,
                'valid' => 1,
                'duress' => 0,
                'major_ver' => 12,
                'minor_ver' => 0,
                'format' => 0,
            ],
            hash('sha256', 'template-data'),
        );
        $command->markSending();

        $this->assertFalse($service->reportResult($command->id, $other->id, 'completed'));
        $this->assertSame(DeviceCommand::STATUS_SENDING, $command->fresh()->status);

        $this->assertTrue($service->reportResult($command->id, $target->id, 'completed'));
        $this->assertSame(DeviceCommand::STATUS_COMPLETED, $command->fresh()->status);
        $this->assertNotNull($command->fresh()->completed_at);
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

        $devices = [];
        foreach (['TARGET-CMD-001', 'OTHER-CMD-001'] as $index => $serial) {
            $devices[] = FingerprintDevice::create([
                'device_type_id' => $type->id,
                'name' => $serial,
                'serial_number' => $serial,
                'ip_address' => '192.168.20.'.($index + 10),
                'port' => 4370,
                'comm_key' => '0',
                'timeout' => 30,
                'status' => 'online',
                'is_push_enabled' => true,
            ]);
        }

        return $devices;
    }
}
