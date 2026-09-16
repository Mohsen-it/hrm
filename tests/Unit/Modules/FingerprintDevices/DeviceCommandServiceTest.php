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

    /**
     * Phase 1: user create/update failures ARE retried (bounded) — the
     * terminal refuses templates with Return=-3 while the user record is
     * missing, so a failed user write would cascade. See handleFailure().
     */
    public function test_failed_user_update_is_requeued_with_backoff(): void
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

        $fresh = $command->fresh();
        $this->assertSame(DeviceCommand::STATUS_PENDING, $fresh->status);
        $this->assertSame(1, $fresh->retry_count);
        $this->assertNotNull($fresh->available_at);
    }

    public function test_failed_user_update_gives_up_after_max_retries(): void
    {
        [$target] = $this->makeDevices();
        $service = app(DeviceCommandService::class);

        $command = $service->queueUserUpdate($target->id, '101', 'Layla');
        $command->markSending();
        $command->update([
            'status' => DeviceCommand::STATUS_FAILED,
            'retry_count' => 10,
            'max_retries' => 10,
            'error_message' => 'Device returned -1',
        ]);

        $this->assertTrue($service->reportResult($command->id, $target->id, 'failed', 'Device returned -1'));
        $this->assertSame(DeviceCommand::STATUS_FAILED, $command->fresh()->status);
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
            'retry_count' => 5,
            'error_message' => 'Device returned -3',
        ]);

        $result = $service->retryFailedFaceCommands(deviceId: $target->id);

        $this->assertSame(1, $result['requeued']);
        $this->assertSame(1, $result['total_failed']);

        $fresh = $command->fresh();
        $this->assertSame(DeviceCommand::STATUS_PENDING, $fresh->status);
        // Bounded retry: budget preserved (never reset), backoff scheduled.
        $this->assertSame(5, $fresh->retry_count);
        $this->assertSame(30, $fresh->max_retries);
        $this->assertNull($fresh->sent_at);
        $this->assertNotNull($fresh->available_at);
    }

    public function test_retry_failed_face_commands_skips_exhausted_budget(): void
    {
        [$target] = $this->makeDevices();
        $service = app(DeviceCommandService::class);

        $command = $service->queueFaceTemplate(
            $target->id,
            'EMP-301',
            'face-template-exhausted',
            ['index' => 1, 'valid' => 1],
            hash('sha256', 'face-template-exhausted'),
        );
        $command->markSending();
        $command->update([
            'status' => DeviceCommand::STATUS_FAILED,
            'retry_count' => 30,
            'max_retries' => 30,
            'error_message' => 'Device returned -3',
        ]);

        $result = $service->retryFailedFaceCommands(deviceId: $target->id);

        $this->assertSame(0, $result['requeued']);
        $this->assertSame(DeviceCommand::STATUS_FAILED, $command->fresh()->status);
    }

    public function test_identical_face_template_is_not_duplicated_after_completion(): void
    {
        [$target] = $this->makeDevices();
        $service = app(DeviceCommandService::class);

        $first = $service->queueFaceTemplate(
            $target->id,
            'EMP-302',
            'same-face-bytes',
            ['index' => 0, 'valid' => 1],
            hash('sha256', 'same-face-bytes'),
        );
        $first->markSending();
        $first->markCompleted();

        $second = $service->queueFaceTemplate(
            $target->id,
            'EMP-302',
            'same-face-bytes',
            ['index' => 0, 'valid' => 1],
            hash('sha256', 'same-face-bytes'),
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, DeviceCommand::query()->count());
    }

    public function test_identical_user_create_is_not_duplicated_after_completion(): void
    {
        [$target] = $this->makeDevices();
        $service = app(DeviceCommandService::class);

        $first = $service->queueUserCreate($target->id, '400', 'New Employee');
        $first->markSending();
        $first->markCompleted();

        // Same exact payload → already delivered → no new row (new PINs still queue).
        $second = $service->queueUserCreate($target->id, '400', 'New Employee');

        $this->assertSame($first->id, $second->id);
    }

    public function test_new_pin_still_queues_user_command(): void
    {
        [$target] = $this->makeDevices();
        $service = app(DeviceCommandService::class);

        $command = $service->queueUserCreate($target->id, '401', 'Brand New');

        $this->assertTrue($command->wasRecentlyCreated);
        $this->assertSame(DeviceCommand::STATUS_PENDING, $command->fresh()->status);
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

        // Phase 1: face templates use the unified biodata table (Type=2),
        // byte-exact mirror of terminal uploads — iFace firmware rejects
        // DATA UPDATE FACE intermittently. FID belongs to FINGERTMP only.
        $this->assertStringStartsWith('DATA UPDATE biodata', $command->command_body);
        $this->assertStringContainsString('Pin=20079', $command->command_body);
        $this->assertStringContainsString('Type=2', $command->command_body);
        $this->assertStringContainsString('Index=0', $command->command_body);
        $this->assertSame(30, $command->max_retries);
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
                'ip_address' => '192.168.10.'.($index + 20),
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
