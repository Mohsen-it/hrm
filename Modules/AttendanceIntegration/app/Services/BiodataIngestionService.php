<?php

namespace Modules\AttendanceIntegration\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Modules\AttendanceIntegration\Contracts\DeviceRepositoryInterface;
use Modules\AttendanceIntegration\Parsers\BiodataParser;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\Users\Models\User;

class BiodataIngestionService
{
    public function __construct(
        private DeviceRepositoryInterface $deviceRepository,
    ) {}

    /**
     * Process a batch of parsed BIODATA records and persist face templates.
     *
     * @return array{processed: int, saved: int, duplicates: int, skipped: int, errors: array<string>}
     */
    public function ingest(
        ?FingerprintDevice $device,
        array $records,
        string $correlationId = '',
    ): array {
        $stats = [
            'processed' => count($records),
            'saved' => 0,
            'duplicates' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($records as $record) {
            try {
                $result = $this->ingestSingle($device, $record, $correlationId);

                $stats[$result]++;
            } catch (\Throwable $e) {
                $stats['errors'][] = "Pin {$record['pin']}: {$e->getMessage()}";

                Log::channel('biodata')->error('BIODATA_INGEST_SINGLE_FAILED', [
                    'correlation_id' => $correlationId,
                    'device_serial' => $device?->serial_number,
                    'pin' => $record['pin'],
                    'type' => $record['type'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Process a single BIODATA record.
     *
     * @return 'saved'|'duplicates'|'skipped'
     */
    private function ingestSingle(
        ?FingerprintDevice $device,
        array $record,
        string $correlationId,
    ): string {
        $pin = $record['pin'];
        $type = $record['type'];
        $tmpData = $record['tmp'];

        Log::channel('biodata')->info('BIODATA_RECORD_RECEIVED', [
            'correlation_id' => $correlationId,
            'device_serial' => $device?->serial_number,
            'pin' => $pin,
            'type' => $type,
            'type_label' => BiodataParser::typeLabel($type),
            'template_length' => strlen($tmpData),
            'major_ver' => $record['major_ver'],
            'minor_ver' => $record['minor_ver'],
        ]);

        if ($tmpData === '') {
            Log::channel('biodata')->warning('BIODATA_EMPTY_TEMPLATE', [
                'correlation_id' => $correlationId,
                'device_serial' => $device?->serial_number,
                'pin' => $pin,
            ]);

            return 'skipped';
        }

        if ($type !== BiodataParser::TYPE_FACE) {
            Log::channel('biodata')->info('BIODATA_NON_FACE_SKIPPED', [
                'correlation_id' => $correlationId,
                'device_serial' => $device?->serial_number,
                'pin' => $pin,
                'type' => $type,
                'type_label' => BiodataParser::typeLabel($type),
            ]);

            return 'skipped';
        }

        Log::channel('biodata')->info('FACE_TEMPLATE_DETECTED', [
            'correlation_id' => $correlationId,
            'device_serial' => $device?->serial_number,
            'pin' => $pin,
            'template_length' => strlen($tmpData),
            'version' => "{$record['major_ver']}.{$record['minor_ver']}",
        ]);

        $user = $this->resolveUser($pin);

        if (! $user) {
            Log::channel('biodata')->warning('BIODATA_EMPLOYEE_NOT_FOUND', [
                'correlation_id' => $correlationId,
                'device_serial' => $device?->serial_number,
                'pin' => $pin,
                'message' => "No employee found with employee_code or id matching PIN {$pin}",
            ]);

            return 'skipped';
        }

        Log::channel('biodata')->info('BIODATA_EMPLOYEE_RESOLVED', [
            'correlation_id' => $correlationId,
            'device_serial' => $device?->serial_number,
            'pin' => $pin,
            'user_id' => $user->id,
            'user_name' => $user->full_name_ar ?? $user->name,
        ]);

        $existingTemplate = $this->findExistingTemplate($user->id, $device?->id, $tmpData);

        if ($existingTemplate) {
            Log::channel('biodata')->info('DUPLICATE_TEMPLATE_IGNORED', [
                'correlation_id' => $correlationId,
                'device_serial' => $device?->serial_number,
                'pin' => $pin,
                'user_id' => $user->id,
                'existing_template_id' => $existingTemplate->id,
                'template_length' => strlen($tmpData),
            ]);

            return 'duplicates';
        }

        $this->saveTemplate($user, $device, $record, $correlationId);

        Log::channel('biodata')->info('TEMPLATE_SAVED_SUCCESSFULLY', [
            'correlation_id' => $correlationId,
            'device_serial' => $device?->serial_number,
            'pin' => $pin,
            'user_id' => $user->id,
            'user_name' => $user->full_name_ar ?? $user->name,
            'template_length' => strlen($tmpData),
            'version' => "{$record['major_ver']}.{$record['minor_ver']}",
            'format' => $record['format'],
        ]);

        return 'saved';
    }

    /**
     * Resolve a device PIN to a system user.
     *
     * Strategy:
     *  1. Match against `users.employee_code` (case-insensitive)
     *  2. Match against `users.id` if PIN is numeric
     */
    private function resolveUser(string $pin): ?User
    {
        if ($pin === '') {
            return null;
        }

        $user = User::query()
            ->whereRaw('LOWER(employee_code) = LOWER(?)', [$pin])
            ->first();

        if ($user) {
            return $user;
        }

        if (is_numeric($pin)) {
            $user = User::query()->where('id', (int) $pin)->first();

            if ($user) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Find an existing template that matches the same user, device, and data.
     */
    private function findExistingTemplate(int $userId, ?int $deviceId, string $tmpData): ?UserFingerprint
    {
        $query = UserFingerprint::query()
            ->where('user_id', $userId)
            ->where('template_format', 'zkteco-face-push')
            ->where('template_data', $tmpData);

        if ($deviceId) {
            $query->where('device_id', $deviceId);
        }

        return $query->first();
    }

    /**
     * Persist a face template to the user_fingerprints table.
     */
    private function saveTemplate(
        User $user,
        ?FingerprintDevice $device,
        array $record,
        string $correlationId,
    ): UserFingerprint {
        $templateVersion = (int) "{$record['major_ver']}{$record['minor_ver']}";
        $versionLabel = "{$record['major_ver']}.{$record['minor_ver']}";

        $nextFaceId = $this->getNextFaceId($user->id, $device?->id);

        $payload = [
            'user_id' => $user->id,
            'device_id' => $device?->id,
            'finger_id' => $nextFaceId,
            'template_data' => $record['tmp'],
            'template_format' => 'zkteco-face-push',
            'template_version' => $templateVersion,
            'quality' => 0,
            'is_master' => $nextFaceId === 50,
            'captured_at' => now(),
            'synced_at' => now(),
        ];

        $template = UserFingerprint::create($payload);

        $this->saveDebugSnapshot($user, $device, $record, $template, $correlationId);

        return $template;
    }

    /**
     * Get the next available face_id for this user+device combination.
     * Face templates use finger_id range 50-59.
     */
    private function getNextFaceId(int $userId, ?int $deviceId): int
    {
        $query = UserFingerprint::query()
            ->where('user_id', $userId)
            ->where('template_format', 'like', '%face%')
            ->whereBetween('finger_id', [50, 59]);

        if ($deviceId) {
            $query->where('device_id', $deviceId);
        }

        $existingIds = $query->pluck('finger_id')->sort()->values()->all();

        for ($id = 50; $id <= 59; $id++) {
            if (! in_array($id, $existingIds)) {
                return $id;
            }
        }

        return 50;
    }

    /**
     * Save a debug snapshot of the received template for debugging purposes.
     */
    private function saveDebugSnapshot(
        User $user,
        ?FingerprintDevice $device,
        array $record,
        UserFingerprint $template,
        string $correlationId,
    ): void {
        try {
            $debugDir = storage_path('app/debug/biodata');

            if (! is_dir($debugDir)) {
                File::makeDirectory($debugDir, 0755, true, true);
            }

            $filename = sprintf(
                '%s_%s_pin%s_user%d_tpl%d.txt',
                date('Y-m-d_His'),
                substr($correlationId, 0, 8),
                $record['pin'],
                $user->id,
                $template->id,
            );

            $content = "=== BIODATA DEBUG SNAPSHOT ===\n";
            $content .= 'Timestamp: '.now()->toIso8601String()."\n";
            $content .= "Correlation ID: {$correlationId}\n";
            $content .= "Device: {$device?->serial_number} ({$device?->ip_address})\n";
            $content .= "User: {$user->id} ({$user->employee_code}) - ".($user->full_name_ar ?? $user->name)."\n";
            $content .= "Template ID: {$template->id}\n";
            $content .= 'Type: '.BiodataParser::typeLabel($record['type'])." ({$record['type']})\n";
            $content .= "Version: {$record['major_ver']}.{$record['minor_ver']}\n";
            $content .= "Format: {$record['format']}\n";
            $content .= 'Template Length: '.strlen($record['tmp'])." chars\n";
            $content .= "\n=== RAW BIODATA ===\n";
            $content .= $record['raw']."\n";

            file_put_contents($debugDir.'/'.$filename, $content);
        } catch (\Throwable) {
            // Non-critical — ignore debug save failures
        }
    }
}
