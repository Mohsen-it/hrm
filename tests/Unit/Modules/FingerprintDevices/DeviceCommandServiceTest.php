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

        $command = $service->queueUserUpdate($target->id, '100', 'Ahmad');

        $this->assertTrue($service->reportResult($command->id, $target->id, 'completed'));
        $this->assertTrue($command->fresh()->status === DeviceCommand::STATUS_COMPLETED);

        $command2 = $service->queueUserUpdate($other->id, '200', 'Sami');
        $this->assertFalse($service->reportResult($command2->id, $target->id, 'completed'));
    }

    public function test_failed_face_template_is_requeued_with_backoff(): void
    {
        [$target] = $this->makeDevices();
        $service = app(DeviceCommandService::class);

        $command = $service->queueFaceTemplate(
            $target->id,
            '20079',
            base64_encode('test-data'),
            ['index' => 0, 'valid' => 1],
            hash('sha256', 'test-data'),
        );
        $command->markSending();
        $command->update([
            'status' => DeviceCommand::STATUS_FAILED,
            'error_message' => 'Device returned -3',
        ]);

        $this->assertTrue($service->reportResult($command->id, $target->id, 'failed', 'Device returned -3'));

        $fresh = $command->fresh();
        $this->assertSame(DeviceCommand::STATUS_PENDING, $fresh->status);
        $this->assertSame(1, $fresh->retry_count);
        $this->assertNotNull($fresh->available_at);
        $this->assertNotNull($fresh->error_message);
    }

    public function test_failed_face_template_gives_up_after_max_retries(): void
    {
        [$target] = $this->makeDevices();
        $service = app(DeviceCommandService::class);

        $command = $service->queueFaceTemplate(
            $target->id,
            '20079',
            base64_encode('test-data'),
            ['index' => 0, 'valid' => 1],
            hash('sha256', 'test-data'),
        );
        $command->markSending();
        $command->update([
            'status' => DeviceCommand::STATUS_FAILED,
            'retry_count' => 15,
            'max_retries' => 15,
            'error_message' => 'Device returned -3',
        ]);

        $this->assertTrue($service->reportResult($command->id, $target->id, 'failed', 'Device returned -3'));
        $this->assertTrue($command->fresh()->status === DeviceCommand::STATUS_FAILED);
    }

    public function test_failed_user_update_is_not_requeued(): void
    {
        [$target] = $this->makeDevices();
        $service = app(DeviceCommandService::class);

        $command = $service->queueUserUpdate($target->id, '100', 'Ahmad');
        $command->markSending();
        $command->update([
            'status' => DeviceCommand::STATUS_FAILED,
            'error_message' => 'Device returned -1',
        ]);

        $this->assertTrue($service->reportResult($command->id, $target->id, 'failed', 'Device returned -1'));
        $this->assertTrue($command->fresh()->status === DeviceCommand::STATUS_FAILED);
    }

    public function test_retry_failed_face_commands_resets_to_pending(): void
    {
        [$target] = $this->makeDevices();
        $service = app(DeviceCommandService::class);

        // Create a face command and push it to FAILED
        $command = $service->queueFaceTemplate(
            $target->id,
            'EMP-300',
            'face-template-data',
            ['index' => 0, 'valid' => 1],
            hash('sha256', 'face-template-data'),
        );
        $command->markSending();
        $command->update([
            'status' => DeviceCommand::STATUS_FAILED,
            'retry_count' => 15,
            'error_message' => 'Device returned -3',
        ]);

        $result = $service->retryFailedFaceCommands(deviceId: $target->id);

        $this->assertSame(1, $result['requeued']);
        $this->assertSame(1, $result['total_failed']);

        $fresh = $command->fresh();
        $this->assertSame(DeviceCommand::STATUS_PENDING, $fresh->status);
        $this->assertSame(0, $fresh->retry_count);
        $this->assertSame(15, $fresh->max_retries);
        $this->assertNull($fresh->sent_at);
        $this->assertNull($fresh->error_message);
        $this->assertNull($fresh->expires_at);
        $this->assertNull($fresh->available_at);
    }

    public function test_retry_failed_face_commands_returns_zero_when_no_failures(): void
    {
        [$target] = $this->makeDevices();
        $service = app(DeviceCommandService::class);

        $result = $service->retryFailedFaceCommands(deviceId: $target->id);

        $this->assertSame(0, $result['requeued']);
        $this->assertSame(0, $result['total_failed']);
    }

    public function test_face_template_command_uses_correct_body_format(): void
    {
        [$target] = $this->makeDevices();
        $service = app(DeviceCommandService::class);
        $command = $service->queueFaceTemplate(
            $target->id,
            '20079',
            base64_encode('test-template-data'),
            [
                'no' => 0,
                'index' => 0,
                'valid' => 1,
                'duress' => 0,
                'major_ver' => 12,
                'minor_ver' => 0,
                'format' => 0,
            ],
            hash('sha256', 'test-template-data'),
        );

        $this->assertStringStartsWith('DATA UPDATE FACE', $command->command_body);
        $this->assertStringContainsString('PIN=20079', $command->command_body);
        $this->assertStringContainsString('FID=0', $command->command_body);
        $this->assertSame(15, $command->max_retries);
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
        foreach (['TARGET-CMD-001', 'OTHER-CMD-001'] as $index => $serial) {
            $devices[] = FingerprintDevice::create([
                'device_type_id' => $type->id,
                'name' => "Test Device {$serial}",
                'serial_number' => $serial,
                'ip_address' => '192.168.10.' . ($index + 20),
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
