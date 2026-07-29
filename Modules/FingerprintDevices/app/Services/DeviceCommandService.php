<?php

namespace Modules\FingerprintDevices\Services;

use Illuminate\Support\Facades\Log;
use Modules\FingerprintDevices\Models\DeviceCommand;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Repositories\DeviceCommandRepository;
use Modules\Users\Models\User;

class DeviceCommandService
{
    // ZKTeco ADMS command codes
    public const CMD_USER_WRQ = 10;

    public const CMD_DEL_USER = 11;

    public const CMD_SET_TIME = 18;

    public const CMD_REFRESHOPTION = 50;

    public const CMD_RESTART = 60;

    public function __construct(
        protected DeviceCommandRepository $commandRepo,
    ) {}

    // -----------------------------------------------------------------------
    // Command creation
    // -----------------------------------------------------------------------

    /**
     * Queue a command for a device.
     */
    public function queueCommand(
        int $deviceId,
        string $commandType,
        string $commandBody,
        int $priority = 5,
        ?string $correlationId = null,
        ?int $expiresInMinutes = null,
    ): DeviceCommand {
        return $this->commandRepo->create([
            'device_id' => $deviceId,
            'command_type' => $commandType,
            'command_body' => $commandBody,
            'priority' => $priority,
            'correlation_id' => $correlationId ?? uniqid('cmd-', true),
            'expires_at' => $expiresInMinutes ? now()->addMinutes($expiresInMinutes) : null,
        ]);
    }

    /**
     * Queue a CREATE USER command.
     *
     * ZKTeco ADMS format: C:10#PIN##Name##Privilege##Password##Card
     */
    public function queueUserCreate(
        int $deviceId,
        string $pin,
        string $name,
        int $privilege = 0,
        string $password = '',
        int $card = 0,
    ): DeviceCommand {
        $body = sprintf('C:%d#%s##%s##%d##%s##%d',
            self::CMD_USER_WRQ,
            $pin,
            $name,
            $privilege,
            $password,
            $card
        );

        return $this->queueCommand(
            $deviceId,
            DeviceCommand::TYPE_USER_CREATE,
            $body,
            priority: 3,
        );
    }

    /**
     * Queue an UPDATE USER command (same as create — ZKTeco uses SetUser for both).
     */
    public function queueUserUpdate(
        int $deviceId,
        string $pin,
        string $name,
        int $privilege = 0,
        string $password = '',
        int $card = 0,
    ): DeviceCommand {
        $body = sprintf('C:%d#%s##%s##%d##%s##%d',
            self::CMD_USER_WRQ,
            $pin,
            $name,
            $privilege,
            $password,
            $card
        );

        return $this->queueCommand(
            $deviceId,
            DeviceCommand::TYPE_USER_UPDATE,
            $body,
            priority: 3,
        );
    }

    /**
     * Queue a DELETE USER command.
     *
     * ZKTeco ADMS format: C:11#PIN
     */
    public function queueUserDelete(int $deviceId, string $pin): DeviceCommand
    {
        $body = sprintf('C:%d#%s', self::CMD_DEL_USER, $pin);

        return $this->queueCommand(
            $deviceId,
            DeviceCommand::TYPE_USER_DELETE,
            $body,
            priority: 2,
        );
    }

    /**
     * Queue a TIME SYNC command.
     *
     * ZKTeco ADMS format: C:18#YYYY-MM-DD HH:ii:ss
     */
    public function queueTimeSync(int $deviceId): DeviceCommand
    {
        $body = sprintf('C:%d#%s', self::CMD_SET_TIME, date('Y-m-d H:i:s'));

        return $this->queueCommand(
            $deviceId,
            DeviceCommand::TYPE_TIME_SYNC,
            $body,
            priority: 1,
            expiresInMinutes: 5,
        );
    }

    /**
     * Queue a RESTART command.
     *
     * ZKTeco ADMS format: C:60
     */
    public function queueRestart(int $deviceId): DeviceCommand
    {
        $body = sprintf('C:%d', self::CMD_RESTART);

        return $this->queueCommand(
            $deviceId,
            DeviceCommand::TYPE_RESTART,
            $body,
            priority: 1,
        );
    }

    /**
     * Queue a REFRESH CONFIG command.
     *
     * ZKTeco ADMS format: C:50
     */
    public function queueRefreshConfig(int $deviceId): DeviceCommand
    {
        $body = sprintf('C:%d', self::CMD_REFRESHOPTION);

        return $this->queueCommand(
            $deviceId,
            DeviceCommand::TYPE_REFRESH_CONFIG,
            $body,
            priority: 4,
        );
    }

    // -----------------------------------------------------------------------
    // Batch operations
    // -----------------------------------------------------------------------

    /**
     * Queue all active users for a device via ADMS commands.
     *
     * @return array{queued: int, skipped: int}
     */
    public function queueAllUsersForDevice(int $deviceId): array
    {
        $device = FingerprintDevice::find($deviceId);
        if (! $device) {
            return ['queued' => 0, 'skipped' => 0];
        }

        $users = User::query()
            ->where('status', 1)
            ->whereNotNull('employee_code')
            ->where('employee_code', '!=', '')
            ->get();

        $queued = 0;
        $skipped = 0;

        foreach ($users as $user) {
            $this->queueUserCreate(
                $deviceId,
                pin: (string) $user->employee_code,
                name: $user->name ?? $user->full_name_ar ?? $user->employee_code,
                privilege: 0,
                password: '',
                card: 0,
            );
            $queued++;
        }

        Log::info('QUEUE_ALL_USERS', [
            'device_id' => $deviceId,
            'serial' => $device->serial_number,
            'queued' => $queued,
            'skipped' => $skipped,
        ]);

        return ['queued' => $queued, 'skipped' => $skipped];
    }

    // -----------------------------------------------------------------------
    // Command lifecycle
    // -----------------------------------------------------------------------

    /**
     * Fetch next pending commands for ADMS getrequest.
     *
     * @return array<int, array{id: int, command_type: string, command_body: string}>
     */
    public function fetchPendingCommands(int $deviceId, int $limit = 10): array
    {
        $commands = $this->commandRepo->claimPending($deviceId, $limit);

        return $commands->map(fn (DeviceCommand $cmd) => [
            'id' => $cmd->id,
            'command_type' => $cmd->command_type,
            'command_body' => $cmd->command_body,
        ])->toArray();
    }

    /**
     * Report the result of a command execution.
     */
    public function reportResult(int $commandId, string $status, ?string $errorMessage = null): bool
    {
        $command = $this->commandRepo->findById($commandId);
        if (! $command) {
            Log::warning('COMMAND_RESULT_UNKNOWN', ['command_id' => $commandId, 'status' => $status]);

            return false;
        }

        match ($status) {
            'completed', 'success' => $command->markCompleted(),
            'failed' => $command->markFailed($errorMessage ?? 'Device reported failure'),
            default => null,
        };

        Log::info('COMMAND_RESULT', [
            'command_id' => $commandId,
            'type' => $command->command_type,
            'status' => $status,
            'device_id' => $command->device_id,
        ]);

        return true;
    }

    /**
     * Handle device heartbeat: update device status, return any immediate commands.
     */
    public function handleHeartbeat(int $deviceId, string $ip, array $info = []): void
    {
        $device = FingerprintDevice::find($deviceId);
        if (! $device) {
            return;
        }

        $update = [
            'last_seen_at' => now(),
            'status' => 'online',
        ];

        if (! empty($ip) && $device->ip_address !== $ip) {
            $update['ip_address'] = $ip;
        }

        if (! empty($info['firmware'])) {
            $caps = $device->capabilities ?? [];
            $caps['firmware'] = $info['firmware'];
            $update['capabilities'] = $caps;
        }

        if (! empty($info['user_count'])) {
            $update['user_count'] = (int) $info['user_count'];
        }

        if (! empty($info['face_count'])) {
            $caps = $device->capabilities ?? [];
            $caps['face_count'] = (int) $info['face_count'];
            $update['capabilities'] = $caps;
        }

        $device->update($update);

        Log::info('DEVICE_HEARTBEAT', [
            'device_id' => $deviceId,
            'serial' => $device->serial_number,
            'ip' => $ip,
        ]);
    }

    /**
     * Get queue statistics for a device.
     */
    public function getQueueStats(int $deviceId): array
    {
        return $this->commandRepo->getQueueStats($deviceId);
    }

    /**
     * Clean up stale commands.
     */
    public function cleanupStaleCommands(int $maxAgeMinutes = 60): int
    {
        return $this->commandRepo->expireStaleCommands($maxAgeMinutes);
    }
}
