<?php

namespace Tests\Feature\Modules\FingerprintDevices;

use Modules\FingerprintDevices\Models\DeviceCommand;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;
use Modules\FingerprintDevices\Services\DeviceCommandService;
use Tests\TestCase;

class AdmsDeliveryOrderTest extends TestCase
{
    public function test_fetch_returns_pending_commands_without_claiming_them(): void
    {
        $device = $this->makeDevice();
        $user = $this->makeCommand($device, 'user_update', $this->userinfoBody('100', 'Ahmad'), 3);
        $face = $this->makeCommand($device, 'face_template', 'DATA UPDATE FACE PIN=100 FID=0 Size=3 Valid=1 TMP=abc', 4);

        $this->getJson('/api/adms/commands?SN='.$device->serial_number)
            ->assertOk()
            ->assertJsonCount(2, 'commands');

        $this->assertSame(DeviceCommand::STATUS_PENDING, $user->fresh()->status);
        $this->assertSame(DeviceCommand::STATUS_PENDING, $face->fresh()->status);
        $this->assertNull($user->fresh()->sent_at);
    }

    public function test_fetch_orders_set_user_before_face_template(): void
    {
        $device = $this->makeDevice();
        $this->makeCommand($device, 'face_template', 'DATA UPDATE FACE PIN=100 FID=0 Size=3 Valid=1 TMP=abc', 4);
        $this->makeCommand($device, 'user_update', $this->userinfoBody('100', 'Ahmad'), 3);

        $commands = $this->getJson('/api/adms/commands?SN='.$device->serial_number)
            ->assertOk()
            ->json('commands');

        $this->assertSame(['user_update', 'face_template'], array_column($commands, 'command_type'));
        $this->assertSame([3, 4], array_column($commands, 'priority'));
    }

    public function test_mark_sending_transitions_only_pending_commands(): void
    {
        $device = $this->makeDevice();
        $pending = $this->makeCommand($device, 'user_update', $this->userinfoBody('100', 'Ahmad'), 3);
        $completed = $this->makeCommand(
            $device,
            'face_template',
            'DATA UPDATE FACE PIN=100 FID=0 Size=3 Valid=1 TMP=abc',
            4,
            DeviceCommand::STATUS_COMPLETED,
        );

        $this->postJson('/api/adms/commands/sending', [
            'SN' => $device->serial_number,
            'command_ids' => [$pending->id, $completed->id],
        ])
            ->assertOk()
            ->assertJson(['success' => true, 'marked' => 1]);

        $this->assertSame(DeviceCommand::STATUS_SENDING, $pending->fresh()->status);
        $this->assertNotNull($pending->fresh()->sent_at);
        $this->assertSame(DeviceCommand::STATUS_COMPLETED, $completed->fresh()->status);
    }

    public function test_mark_sending_requires_an_existing_device(): void
    {
        $this->postJson('/api/adms/commands/sending', [
            'SN' => 'UNKNOWN-SERIAL',
            'command_ids' => [1],
        ])
            ->assertNotFound()
            ->assertJson(['success' => false]);
    }

    public function test_commands_stay_pending_until_acknowledged_then_complete(): void
    {
        $device = $this->makeDevice();
        $command = $this->makeCommand($device, 'user_update', $this->userinfoBody('100', 'Ahmad'), 3);

        $this->getJson('/api/adms/commands?SN='.$device->serial_number)
            ->assertOk()
            ->assertJsonCount(1, 'commands');

        $this->postJson('/api/adms/commands/sending', [
            'SN' => $device->serial_number,
            'command_ids' => [$command->id],
        ])->assertOk();

        $this->assertSame(DeviceCommand::STATUS_SENDING, $command->fresh()->status);

        $this->postJson('/api/adms/commands/result', [
            'SN' => $device->serial_number,
            'command_id' => $command->id,
            'status' => 'completed',
        ])->assertOk();

        $this->assertSame(DeviceCommand::STATUS_COMPLETED, $command->fresh()->status);
    }

    private function makeDevice(): FingerprintDevice
    {
        $type = FingerprintDeviceType::create([
            'name' => 'ADMS Ordering Test',
            'manufacturer' => 'ZKTeco',
            'protocol' => 'ADMS',
            'default_port' => 4370,
            'is_active' => true,
        ]);

        return FingerprintDevice::create([
            'device_type_id' => $type->id,
            'name' => 'Ordering Device '.uniqid(),
            'serial_number' => 'ADMS-ORDER-'.uniqid(),
            'ip_address' => '192.168.1.200',
            'port' => 4370,
            'comm_key' => '0',
            'connection_type' => 'tcp',
            'timeout' => 30,
            'status' => 'online',
            'is_push_enabled' => true,
        ]);
    }

    public function test_queued_user_commands_use_set_user_format(): void
    {
        $device = $this->makeDevice();
        $service = app(DeviceCommandService::class);

        $created = $service->queueUserCreate($device->id, '29083', 'شادي ابراهيم محلا', 0);
        $updated = $service->queueUserUpdate($device->id, '29083', 'شادي ابراهيم محلا', 0);
        $deleted = $service->queueUserDelete($device->id, '29083');

        $this->assertStringStartsWith('C:10#', $created->command_body);
        $this->assertStringContainsString('29083', $created->command_body);
        $this->assertStringStartsWith('C:10#', $updated->command_body);
        $this->assertStringStartsWith('C:11#', $deleted->command_body);
        $this->assertStringContainsString('29083', $deleted->command_body);
    }

    public function test_queued_face_template_uses_correct_face_format(): void
    {
        $device = $this->makeDevice();
        $service = app(DeviceCommandService::class);

        $template = base64_encode(str_repeat('A', 2016));
        $command = $service->queueFaceTemplate(
            $device->id,
            '29083',
            $template,
            ['index' => 3, 'valid' => 1],
            hash('sha256', $template),
        );

        $this->assertStringStartsWith('DATA UPDATE FACE', $command->command_body);
        $this->assertStringContainsString('PIN=29083', $command->command_body);
        $this->assertStringContainsString('FID=3', $command->command_body);
        $this->assertStringContainsString('Size=2016', $command->command_body);
        $this->assertStringContainsString('Valid=1', $command->command_body);
        $this->assertStringContainsString('TMP=', $command->command_body);
    }

    private function userinfoBody(string $pin, string $name): string
    {
        return "DATA UPDATE USERINFO PIN={$pin}\tName={$name}\tPri=0\tPasswd=\tGrp=1";
    }

    private function makeCommand(
        FingerprintDevice $device,
        string $type,
        string $body,
        int $priority,
        string $status = DeviceCommand::STATUS_PENDING,
    ): DeviceCommand {
        return DeviceCommand::create([
            'device_id' => $device->id,
            'command_type' => $type,
            'command_body' => $body,
            'priority' => $priority,
            'status' => $status,
            'max_retries' => 3,
            'correlation_id' => uniqid('tst-', true),
        ]);
    }
}
