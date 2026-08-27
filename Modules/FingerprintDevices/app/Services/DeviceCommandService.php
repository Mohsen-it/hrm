<?php

namespace Modules\FingerprintDevices\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\FingerprintDevices\Jobs\VerifyFaceTemplateOnDevice;
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
        int $maxRetries = 3,
    ): DeviceCommand {
        return $this->commandRepo->create([
            'device_id' => $deviceId,
            'command_type' => $commandType,
            'command_body' => $commandBody,
            'priority' => $priority,
            'correlation_id' => $correlationId ?? uniqid('cmd-', true),
            'expires_at' => $expiresInMinutes ? now()->addMinutes($expiresInMinutes) : null,
            'max_retries' => $maxRetries,
        ]);
    }

    /**
     * Queue a CREATE USER command.
     *
     * ZKTeco Push SDK v2 format understood by iFace firmware
     * (PushVersion 2.0.33S): the user record must exist on the terminal
     * before any fingerprint/face template can be written for it.
     */
    public function queueUserCreate(
        int $deviceId,
        string $pin,
        string $name,
        int $privilege = 0,
        string $password = '',
        int $card = 0,
    ): DeviceCommand {
        $existing = $this->findPendingUserCommand($deviceId, $pin);
        if ($existing) {
            return $existing;
        }

        $hasArabic = (bool) preg_match('/\p{Arabic}/u', $name);
        // Arabic names: send directly via buildUserInfoBodyAllowArabic.
        // adms_server.py encodes the response as CP1256 automatically.
        // Single command only — iFace firmware does NOT upsert on ADMS,
        // so a second UPDATE would create a duplicate user entry.
        $body = $hasArabic
            ? $this->buildUserInfoBodyAllowArabic($pin, $name, $privilege, $password, $card)
            : $this->buildUserInfoBody($pin, $name, $privilege, $password, $card);

        return $this->queueCommand(
            $deviceId,
            DeviceCommand::TYPE_USER_CREATE,
            $body,
            priority: 3,
            maxRetries: 10,
        );
    }

    /**
     * Queue an UPDATE USER command (same payload as create — ZKTeco uses
     * DATA UPDATE USERINFO as an upsert).
     */
    public function queueUserUpdate(
        int $deviceId,
        string $pin,
        string $name,
        int $privilege = 0,
        string $password = '',
        int $card = 0,
    ): DeviceCommand {
        $hasArabic = (bool) preg_match('/\p{Arabic}/u', $name);
        $body = $hasArabic
            ? $this->buildUserInfoBodyAllowArabic($pin, $name, $privilege, $password, $card)
            : $this->buildUserInfoBody($pin, $name, $privilege, $password, $card);

        $existing = $this->findPendingUserCommand($deviceId, $pin);
        if ($existing) {
            $existing->update(['command_body' => $body]);

            return $existing->fresh();
        }

        return $this->queueCommand(
            $deviceId,
            DeviceCommand::TYPE_USER_UPDATE,
            $body,
            priority: 3,
            maxRetries: 10,
        );
    }

    private function findPendingUserCommand(int $deviceId, string $pin): ?DeviceCommand
    {
        $pin = $this->sanitizeField($pin);

        return DeviceCommand::where('device_id', $deviceId)
            ->whereIn('status', [DeviceCommand::STATUS_PENDING, DeviceCommand::STATUS_SENDING])
            ->whereIn('command_type', [DeviceCommand::TYPE_USER_CREATE, DeviceCommand::TYPE_USER_UPDATE])
            ->where('command_body', 'like', "%PIN={$pin}\t%")
            ->first();
    }

    /**
     * Queue a DELETE USER command.
     *
     * iFace firmware rejects legacy C:11#PIN (returns -1002).
     * Use DATA DELETE USERINFO with tab-separated fields instead.
     */
    public function queueUserDelete(int $deviceId, string $pin): DeviceCommand
    {
        $body = 'DATA DELETE USERINFO '.implode("\t", [
            'PIN='.$this->sanitizeField($pin),
        ]);

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

    /**
     * Queue a ZKTeco Push SDK BIODATA face-template update.
     *
     * The ADMS listener adds the transport envelope `C:{command_id}:`.
     *
     * @param  array<string, int>  $attributes
     */
    public function queueFaceTemplate(
        int $deviceId,
        string $pin,
        string $template,
        array $attributes,
        string $templateHash,
    ): DeviceCommand {
        $index = (int) ($attributes['index'] ?? 0);
        $correlationId = 'face-'.substr(
            hash('sha256', $deviceId.':'.$pin.':'.$index.':'.$templateHash),
            0,
            56,
        );
        $existing = $this->commandRepo->findActiveByCorrelation($deviceId, $correlationId);

        if ($existing) {
            return $existing;
        }

        // ZKTeco multi-bio push write — byte-exact mirror of what these
        // terminals themselves transmit in their BIODATA uploads.  Field
        // casing is CRITICAL: this firmware requires ``MajorVer`` /
        // ``MinorVer`` (capital V); ``Majorver=`` is silently rejected
        // with Return=-30.  Fields are TAB-separated.
        $body = 'DATA UPDATE biodata '.implode("\t", [
            'Pin='.$this->sanitizeField($pin),
            'No='.(int) ($attributes['no'] ?? 0),
            'Index='.$index,
            'Valid='.(int) ($attributes['valid'] ?? 1),
            'Duress='.(int) ($attributes['duress'] ?? 0),
            'Type=2',
            'MajorVer='.(int) ($attributes['major_ver'] ?? 12),
            'MinorVer='.(int) ($attributes['minor_ver'] ?? 0),
            'Format='.(int) ($attributes['format'] ?? 0),
            'Tmp='.$this->sanitizeTemplate($template),
        ]);

        return $this->queueCommand(
            $deviceId,
            DeviceCommand::TYPE_FACE_TEMPLATE,
            $body,
            priority: 4,
            correlationId: $correlationId,
            expiresInMinutes: 1440,
            maxRetries: 30,
        );
    }

    /**
     * Queue a ZKTeco multi-bio push FINGERPRINT template write.
     *
     * Byte-exact mirror of the terminals' own Type=1 BIODATA uploads
     * (verified Return=0 on this fleet): ``Format=ZK`` and
     * ``MajorVer=10`` are what these terminals emit for fingerprints.
     *
     * @param  array<string, int|string>  $attributes
     */
    public function queueFingerprintTemplate(
        int $deviceId,
        string $pin,
        string $template,
        array $attributes,
        string $templateHash,
    ): DeviceCommand {
        $index = max(0, min(9, (int) ($attributes['index'] ?? 0)));
        $correlationId = 'fp-'.substr(
            hash('sha256', $deviceId.':'.$pin.':'.$index.':'.$templateHash),
            0,
            56,
        );
        $existing = $this->commandRepo->findActiveByCorrelation($deviceId, $correlationId);

        if ($existing) {
            return $existing;
        }

        $body = 'DATA UPDATE biodata '.implode("\t", [
            'Pin='.$this->sanitizeField($pin),
            'No='.(int) ($attributes['no'] ?? 0),
            'Index='.$index,
            'Valid='.(int) ($attributes['valid'] ?? 1),
            'Duress='.(int) ($attributes['duress'] ?? 0),
            'Type=1',
            'MajorVer='.(int) ($attributes['major_ver'] ?? 10),
            'MinorVer='.(int) ($attributes['minor_ver'] ?? 0),
            'Format=ZK',
            'Tmp='.$this->sanitizeTemplate($template),
        ]);

        return $this->queueCommand(
            $deviceId,
            DeviceCommand::TYPE_FP_TEMPLATE,
            $body,
            priority: 4,
            correlationId: $correlationId,
            expiresInMinutes: 1440,
            maxRetries: 30,
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
        // Peek without claiming: the ADMS caches commands locally and
        // reports which ones were actually served via POST /commands/sending.
        $this->commandRepo->releaseStaleSending($deviceId);
        $commands = $this->commandRepo->fetchPendingForDevice($deviceId, $limit);

        return $commands->map(fn (DeviceCommand $cmd) => [
            'id' => $cmd->id,
            'command_type' => $cmd->command_type,
            'command_body' => $cmd->command_body,
            'priority' => (int) $cmd->priority,
        ])->toArray();
    }

    /**
     * Report the result of a command execution.
     */
    public function reportResult(
        int $commandId,
        int $deviceId,
        string $status,
        ?string $errorMessage = null,
    ): bool {
        $command = $this->commandRepo->findByIdForDevice($commandId, $deviceId);
        if (! $command) {
            Log::warning('COMMAND_RESULT_UNKNOWN', [
                'command_id' => $commandId,
                'device_id' => $deviceId,
                'status' => $status,
            ]);

            return false;
        }

        $handled = match ($status) {
            'completed', 'success' => $command->markCompleted(),
            'failed' => $this->handleFailure($command, $errorMessage),
            default => false,
        };

        Log::info('COMMAND_RESULT', [
            'command_id' => $commandId,
            'type' => $command->command_type,
            'status' => $status,
            'device_id' => $command->device_id,
        ]);

        // Post-delivery verification for face templates: schedule a
        // background check that the template is actually on the device.
        if ($handled && $command->command_type === DeviceCommand::TYPE_FACE_TEMPLATE && $status !== 'failed') {
            $this->scheduleFaceVerification($command, $deviceId);
        }

        return $handled;
    }

    /**
     * After a face template command completes, verify it's actually stored
     * on the device. If verification fails, re-queue the command.
     *
     * This catches cases where the device ACKs the command but silently
     * drops the template (known iFace firmware quirk).
     */
    private function scheduleFaceVerification(DeviceCommand $command, int $deviceId): void
    {
        try {
            $device = FingerprintDevice::find($deviceId);
            if (! $device || ! $device->is_push_enabled) {
                return;
            }

            // Extract PIN from the command body
            $pin = $this->extractPinFromBody($command->command_body);
            if (! $pin) {
                return;
            }

            // Dispatch async verification job
            VerifyFaceTemplateOnDevice::dispatch(
                $deviceId,
                $pin,
                $command->id,
            )->delay(now()->addSeconds(10)); // Wait 10s for device to settle
        } catch (\Throwable $e) {
            // Non-critical: log and continue
            Log::warning('FACE_VERIFICATION_SCHEDULE_FAILED', [
                'command_id' => $command->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract the employee PIN from a face template command body.
     */
    private function extractPinFromBody(string $body): ?string
    {
        if (preg_match('/PIN=([\w-]+)/', $body, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Handle a device-reported failure, re-queueing recoverable writes.
     *
     * iFace 880 Plus firmware intermittently rejects ``DATA UPDATE FACE`` with
     * Return=-3 even on otherwise healthy devices.  Instead of failing outright
     * we return the command to the queue with exponential backoff so the next
     * poll retries it; retries are bounded by max_retries.
     *
     * User create/update commands are retried too: the terminal refuses face
     * and fingerprint templates with Return=-3 while the user record is
     * missing, so a failed user write would cascade into template failures.
     *
     * When a device accumulates too many -3 errors, we trigger a full
     * re-distribution of face templates to recover gracefully.
     */
    private function handleFailure(DeviceCommand $command, ?string $errorMessage): bool
    {
        $message = $errorMessage ?? 'Device reported failure';

        $retryableTypes = [
            DeviceCommand::TYPE_FACE_TEMPLATE,
            DeviceCommand::TYPE_USER_CREATE,
            DeviceCommand::TYPE_USER_UPDATE,
        ];

        $retryable = in_array($command->command_type, $retryableTypes, true)
            && $command->retry_count < $command->max_retries;

        if (! $retryable) {
            // For non-retryable face commands, track the failure and
            // potentially trigger a full re-sync.
            $this->trackDeviceFailure($command->device_id, $message);

            return $command->markFailed($message);
        }

        // Atomic increment + requeue to avoid race conditions between concurrent
        // workers reporting the same device failure.
        $newRetryCount = DB::table('device_commands')
            ->where('id', $command->id)
            ->where('retry_count', $command->retry_count)
            ->increment('retry_count');

        if ($newRetryCount === 0) {
            // Another worker already incremented — do not double-increment.
            return false;
        }

        $effectiveRetry = $command->retry_count + 1;
        // iFace -3 errors are often transient: the device just needs a moment
        // to clear its buffer. Start with a short 3-second delay and ramp up
        // only if the error persists: 3s, 6s, 12s, 24s, 48s, ... 5 min cap.
        $isTransient = str_contains($message, '-3') || str_contains($message, 'transient');
        $baseDelay = $isTransient ? 3 : 30;
        $backoffSeconds = (int) min(300, $baseDelay * 2 ** ($effectiveRetry - 1));

        $requeued = $command->update([
            'status' => DeviceCommand::STATUS_PENDING,
            'retry_count' => $effectiveRetry,
            'sent_at' => null,
            'error_message' => $message,
            'available_at' => now()->addSeconds($backoffSeconds),
        ]);

        Log::info('COMMAND_REQUEUED', [
            'command_id' => $command->id,
            'retry_count' => $effectiveRetry,
            'backoff_seconds' => $backoffSeconds,
            'is_transient' => $isTransient,
        ]);

        // Track failures per device — if a device accumulates too many,
        // trigger a full re-sync via the fallback path.
        $this->trackDeviceFailure($command->device_id, $message);

        return $requeued;
    }

    /**
     * Track consecutive failures per device.  When a device accumulates
     * more than 10 consecutive -3 errors, log a critical alert so
     * operators know the device needs attention or a direct TCP push
     * fallback should be used.
     */
    private function trackDeviceFailure(int $deviceId, string $message): void
    {
        $cacheKey = "device_failures:{$deviceId}";
        $count = (int) cache()->increment($cacheKey);

        // Reset counter after 30 minutes of no failures
        cache()->put($cacheKey, $count, now()->addMinutes(30));

        if ($count >= 10) {
            Log::critical('DEVICE_CONSECUTIVE_FAILURES', [
                'device_id' => $deviceId,
                'consecutive_failures' => $count,
                'last_error' => $message,
                'action' => 'Device may need direct TCP push fallback or manual inspection',
            ]);
        }
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
     * Re-queue failed face-template commands for retry.
     *
     * Resets retry_count, clears error state, and marks them as pending
     * so the next device poll retries them. Useful for "Device returned -3"
     * failures on iFace devices that are intermittent and recoverable.
     *
     * @return array{requeued: int, total_failed: int}
     */
    public function retryFailedFaceCommands(
        ?int $deviceId = null,
        int $limit = 200,
        int $hours = 720,
    ): array {
        // 720 hours = 30 days. Face templates are critical for employee
        // attendance — we retry for up to a month before giving up.
        $query = DB::table('device_commands')
            ->where('command_type', DeviceCommand::TYPE_FACE_TEMPLATE)
            ->where('status', DeviceCommand::STATUS_FAILED)
            ->where(function ($q) {
                $q->where('command_body', 'like', 'DATA UPDATE biodata%')
                    ->orWhere('command_body', 'like', 'DATA UPDATE FACE%');
            })
            ->where('updated_at', '>=', now()->subHours($hours));

        if ($deviceId !== null) {
            $query->where('device_id', $deviceId);
        }

        $totalFailed = (clone $query)->count();

        $ids = (clone $query)
            ->orderBy('updated_at')
            ->limit($limit)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return ['requeued' => 0, 'total_failed' => $totalFailed];
        }

        $requeued = DB::table('device_commands')
            ->whereIn('id', $ids)
            ->update([
                'status' => DeviceCommand::STATUS_PENDING,
                'retry_count' => 0,
                'max_retries' => 30,
                'sent_at' => null,
                'error_message' => null,
                'expires_at' => null,
                'available_at' => null,
            ]);

        Log::info('FACE_COMMANDS_RETRY', [
            'device_id' => $deviceId,
            'requeued' => $requeued,
            'total_failed' => $totalFailed,
        ]);

        return ['requeued' => $requeued, 'total_failed' => $totalFailed];
    }

    /**
     * Clean up stale commands.
     */
    public function cleanupStaleCommands(int $maxAgeMinutes = 60): int
    {
        return $this->commandRepo->expireStaleCommands($maxAgeMinutes);
    }

    private function sanitizeField(string $value): string
    {
        return preg_replace('/[\r\n\t]/', '', trim($value)) ?? '';
    }

    /**
     * Build a `DATA UPDATE USERINFO` command body (Push SDK v2 upsert).
     *
     * iFace firmware requires TAB-separated KEY=VALUE tokens for USERINFO
     * (same as BIODATA). Space-separated format is NOT parsed — the firmware
     * treats the entire text as the raw name value.
     */
    private function buildUserInfoBody(
        string $pin,
        string $name,
        int $privilege,
        string $password,
        int $card,
    ): string {
        return 'DATA UPDATE USERINFO '.implode("\t", [
            'PIN='.$this->sanitizeField($pin),
            'Name='.$this->sanitizeUserInfoValue($this->safeNameForDevice($name, $pin)),
            'Privilege='.(int) $privilege,
            'Password='.$this->sanitizeUserInfoValue($password),
            'Card='.(int) $card,
        ]);
    }

    /**
     * Sanitize a user name for ADMS USERINFO on iFace firmware.
     *
     * Arabic names are handled separately via buildUserInfoBodyAllowArabic()
     * which sends them with CP1256 encoding (applied by adms_server.py).
     * This method is only called for ASCII/Latin names.
     */
    private function safeNameForDevice(string $name, string $pin): string
    {
        $name = trim($name);
        if ($name === '') {
            return $pin;
        }
        $name = str_replace([' ', "\t"], '_', $name);
        $name = preg_replace('/[^\w\-\.]/u', '', $name) ?? $pin;

        return $name !== '' ? $name : $pin;
    }

    private function buildUserInfoBodyAllowArabic(
        string $pin,
        string $name,
        int $privilege,
        string $password,
        int $card,
    ): string {
        $name = trim($name);
        $name = str_replace([' ', "\t"], '_', $name);
        $name = preg_replace('/[\r\n#=&]+/u', '', $name) ?? $pin;
        $name = mb_substr($name, 0, 30);

        return 'DATA UPDATE USERINFO '.implode("\t", [
            'PIN='.$this->sanitizeField($pin),
            'Name='.$this->sanitizeUserInfoValue($name),
            'Privilege='.(int) $privilege,
            'Password='.$this->sanitizeUserInfoValue($password),
            'Card='.(int) $card,
        ]);
    }

    private function sanitizeUserInfoValue(string $value): string
    {
        return preg_replace('/[\r\n\t#=&]+/', '', trim($value)) ?? '';
    }

    private function sanitizeTemplate(string $template): string
    {
        return preg_replace('/\s+/', '', $template) ?? '';
    }
}
