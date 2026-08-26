<?php

namespace Modules\AttendanceIntegration\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Modules\AttendanceIntegration\Parsers\BiodataParser;
use Modules\FingerprintDevices\Jobs\DistributeFaceTemplateSetJob;
use Modules\FingerprintDevices\Jobs\DistributeFingerprintJob;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\FingerprintDevices\Repositories\UserFingerprintRepository;
use Modules\Users\Models\User;
use Modules\Users\Repositories\UserRepository;

class BiodataIngestionService
{
    /** A ZKTeco face enrollment is only distributable when all components arrive. */
    private const EXPECTED_FACE_TEMPLATE_INDICES = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14];

    public function __construct(
        private UserFingerprintRepository $fingerprintRepository,
        private UserRepository $userRepository,
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

        $bioRecords = array_filter($records, fn (array $r) => in_array((int) ($r['type'] ?? 0), [BiodataParser::TYPE_FACE, BiodataParser::TYPE_FINGERPRINT], true) && (string) ($r['tmp'] ?? '') !== '');
        $uniquePins = array_unique(array_map(fn (array $r) => (string) $r['pin'], $bioRecords));

        $userMap = $this->resolveUsersBatch($uniquePins);
        $existingHashMap = $this->findExistingTemplatesBatch($userMap, $device, $bioRecords);
        $existingFaceIdsMap = $this->getExistingFaceIdsBatch($userMap, $device);

        foreach ($records as $record) {
            try {
                $result = $this->ingestSingle($device, $record, $correlationId, $userMap, $existingHashMap, $existingFaceIdsMap);
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

        $this->queueCompleteFaceTemplateSets($device, $records, $correlationId, $stats, $userMap);

        return $stats;
    }

    /**
     * Resolve multiple PINs to users in a single query.
     *
     * @return array<string, User>
     */
    private function resolveUsersBatch(array $pins): array
    {
        if (empty($pins)) {
            return [];
        }

        $numericPins = array_filter($pins, fn (string $pin) => is_numeric($pin));

        $users = User::query()
            ->where(function ($q) use ($pins, $numericPins) {
                $q->whereIn('employee_code', $pins);
                if (! empty($numericPins)) {
                    $q->orWhereIn('id', array_map('intval', $numericPins));
                }
            })
            ->get()
            ->keyBy(fn (User $u) => $u->employee_code);

        $result = [];
        foreach ($pins as $pin) {
            $result[$pin] = $users->get($pin) ?? $users->get((int) $pin);
        }

        return array_filter($result);
    }

    /**
     * Find existing templates for all users+records in batch.
     *
     * @param  array<string, User>  $userMap
     * @param  array<int, array<string, mixed>>  $faceRecords
     * @return array<string, UserFingerprint> key = "userId:hash"
     */
    private function findExistingTemplatesBatch(array $userMap, ?FingerprintDevice $device, array $faceRecords): array
    {
        if (empty($userMap) || empty($faceRecords)) {
            return [];
        }

        $userIds = array_map(fn (User $u) => $u->id, $userMap);
        $hashes = array_map(fn (array $r) => hash('sha256', $r['tmp']), $faceRecords);
        $deviceSerial = $device?->serial_number ?? 'unknown';

        $existing = UserFingerprint::query()
            ->whereIn('user_id', $userIds)
            ->where('device_serial', $deviceSerial)
            ->whereIn('template_hash', $hashes)
            ->get()
            ->keyBy(fn (UserFingerprint $f) => "{$f->user_id}:{$f->template_hash}");

        return $existing->toArray();
    }

    /**
     * Get existing face IDs for all users in batch.
     *
     * @param  array<string, User>  $userMap
     * @return array<int, array<int, int>> key = userId, value = sorted face_ids
     */
    private function getExistingFaceIdsBatch(array $userMap, ?FingerprintDevice $device): array
    {
        if (empty($userMap)) {
            return [];
        }

        $userIds = array_map(fn (User $u) => $u->id, $userMap);

        $query = UserFingerprint::query()
            ->whereIn('user_id', $userIds)
            ->where('template_format', 'like', '%face%')
            ->whereBetween('finger_id', [50, 59]);

        if ($device) {
            $query->where('device_id', $device->id);
        }

        $rows = $query->select('user_id', 'finger_id')->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->user_id][] = $row->finger_id;
        }

        foreach ($map as $uid => $ids) {
            sort($map[$uid]);
        }

        return $map;
    }

    /**
     * Process a single BIODATA record.
     *
     * @param  array<string, User>  $userMap
     * @param  array<string, UserFingerprint>  $existingHashMap
     * @param  array<int, array<int, int>>  $existingFaceIdsMap
     * @return 'saved'|'duplicates'|'skipped'
     */
    private function ingestSingle(
        ?FingerprintDevice $device,
        array $record,
        string $correlationId,
        array $userMap,
        array $existingHashMap,
        array $existingFaceIdsMap,
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
            return 'skipped';
        }

        // Fingerprints are stored and auto-distributed exactly like faces
        // (push write verified Return=0 with Type=1 / MajorVer=10 / Format=ZK).
        if ($type === BiodataParser::TYPE_FINGERPRINT) {
            $user = $userMap[$pin] ?? null;

            if (! $user) {
                Log::channel('biodata')->warning('FINGERPRINT_EMPLOYEE_NOT_FOUND', [
                    'correlation_id' => $correlationId,
                    'device_serial' => $device?->serial_number,
                    'pin' => $pin,
                ]);

                return 'skipped';
            }

            return $this->ingestFingerprint($device, $record, $correlationId, $user, $existingHashMap);
        }

        if ($type !== BiodataParser::TYPE_FACE) {
            return 'skipped';
        }

        $user = $userMap[$pin] ?? null;

        if (! $user) {
            Log::channel('biodata')->warning('BIODATA_EMPLOYEE_NOT_FOUND', [
                'correlation_id' => $correlationId,
                'device_serial' => $device?->serial_number,
                'pin' => $pin,
            ]);

            return 'skipped';
        }

        $hash = hash('sha256', $tmpData);
        $existingKey = "{$user->id}:{$hash}";

        if (isset($existingHashMap[$existingKey])) {
            Log::channel('biodata')->info('DUPLICATE_TEMPLATE_IGNORED', [
                'correlation_id' => $correlationId,
                'device_serial' => $device?->serial_number,
                'pin' => $pin,
                'user_id' => $user->id,
            ]);

            return 'duplicates';
        }

        $templateIndex = $this->faceTemplateIndex($record);
        $existingIds = $existingFaceIdsMap[$user->id] ?? [];

        if ($templateIndex === null) {
            $nextFaceId = 50;
            for ($id = 50; $id <= 59; $id++) {
                if (! in_array($id, $existingIds)) {
                    $nextFaceId = $id;
                    break;
                }
            }
        } else {
            $nextFaceId = 50 + $templateIndex;
        }

        $templateVersion = (int) "{$record['major_ver']}{$record['minor_ver']}";

        $payload = [
            'user_id' => $user->id,
            'device_id' => $device?->id,
            'finger_id' => $nextFaceId,
            'template_data' => $record['tmp'],
            'template_format' => 'zkteco-face-push',
            'template_type' => 'face',
            'template_index' => $templateIndex,
            'face_template_set_id' => $correlationId !== '' ? $correlationId : null,
            'device_serial' => $device?->serial_number ?? 'unknown',
            'template_hash' => $hash,
            'template_metadata' => array_merge($record['extra_fields'] ?? [], [
                'Pin' => $record['pin'],
                'Type' => $record['type'],
                'MajorVer' => $record['major_ver'],
                'MinorVer' => $record['minor_ver'],
                'Format' => $record['format'],
            ]),
            'template_version' => $templateVersion,
            'quality' => 0,
            'is_master' => $templateIndex === null ? $nextFaceId === 50 : $templateIndex === 0,
            'captured_at' => now(),
            'synced_at' => now(),
        ];

        $template = $this->fingerprintRepository->create($payload);

        $this->saveDebugSnapshot($user, $device, $record, $template, $correlationId);

        Log::channel('biodata')->info('TEMPLATE_SAVED_SUCCESSFULLY', [
            'correlation_id' => $correlationId,
            'device_serial' => $device?->serial_number,
            'pin' => $pin,
            'user_id' => $user->id,
            'template_length' => strlen($tmpData),
        ]);

        return 'saved';
    }

    /**
     * Store a captured fingerprint template and queue auto-distribution.
     */
    private function ingestFingerprint(
        ?FingerprintDevice $device,
        array $record,
        string $correlationId,
        $user,
        array $existingHashMap,
    ): string {
        $pin = (string) $record['pin'];
        $tmpData = (string) $record['tmp'];
        $hash = hash('sha256', $tmpData);
        $existingKey = "{$user->id}:{$hash}";

        if (isset($existingHashMap[$existingKey])) {
            Log::channel('biodata')->info('FINGERPRINT_DUPLICATE_IGNORED', [
                'correlation_id' => $correlationId,
                'device_serial' => $device?->serial_number,
                'pin' => $pin,
            ]);

            return 'duplicates';
        }

        $extra = array_merge($record['extra_fields'] ?? [], [
            'MajorVer' => $record['major_ver'],
            'MinorVer' => $record['minor_ver'],
        ]);
        $templateIndex = isset($extra['Index']) && is_numeric($extra['Index'])
            ? max(0, min(9, (int) $extra['Index']))
            : 0;

        UserFingerprint::create([
            'user_id' => $user->id,
            'device_id' => $device?->id,
            'finger_id' => $templateIndex,
            'template_data' => $tmpData,
            'template_format' => 'zkteco-fp-push',
            'template_type' => 'fingerprint',
            'template_index' => $templateIndex,
            'device_serial' => $device?->serial_number ?? 'unknown',
            'template_hash' => $hash,
            'template_metadata' => $extra,
            'template_version' => (int) "{$record['major_ver']}{$record['minor_ver']}",
            'captured_at' => now(),
        ]);

        Log::channel('biodata')->info('FINGERPRINT_STORED_FOR_DISTRIBUTION', [
            'correlation_id' => $correlationId,
            'device_serial' => $device?->serial_number,
            'pin' => $pin,
            'finger_id' => $templateIndex,
        ]);

        DistributeFingerprintJob::dispatch(
            $pin,
            (int) $device?->id,
            $templateIndex,
            $tmpData,
            [
                'no' => (int) ($extra['No'] ?? 0),
                'valid' => (int) ($extra['Valid'] ?? 1),
                'duress' => (int) ($extra['Duress'] ?? 0),
                'major_ver' => (int) $record['major_ver'],
                'minor_ver' => (int) $record['minor_ver'],
            ],
        );

        return 'saved';
    }

    /**
     * Queue automatic delivery only when the source sent the full 15-part enrollment.
     */
    private function queueCompleteFaceTemplateSets(
        ?FingerprintDevice $device,
        array $records,
        string $correlationId,
        array $stats,
        array $userMap,
    ): void {
        if (! $device || $correlationId === '' || ! empty($stats['errors'])) {
            return;
        }

        $pins = collect($records)
            ->filter(fn (array $record) => (int) ($record['type'] ?? 0) === BiodataParser::TYPE_FACE)
            ->pluck('pin')
            ->filter()
            ->unique();

        foreach ($pins as $pin) {
            $user = $userMap[$pin] ?? null;
            if (! $user || ! $this->hasCompleteFaceTemplateSet($user->id, $device->id, $correlationId)) {
                continue;
            }

            DistributeFaceTemplateSetJob::dispatch(
                $user->id,
                $device->id,
                (string) $device->serial_number,
                $correlationId,
            );

            Log::channel('biodata')->info('FACE_TEMPLATE_SET_READY_FOR_AUTO_DISTRIBUTION', [
                'correlation_id' => $correlationId,
                'device_serial' => $device->serial_number,
                'user_id' => $user->id,
                'component_count' => count(self::EXPECTED_FACE_TEMPLATE_INDICES),
            ]);
        }
    }

    /** Determine whether a persisted source set contains every ZKTeco component exactly once. */
    private function hasCompleteFaceTemplateSet(int $userId, int $deviceId, string $setId): bool
    {
        $indices = UserFingerprint::query()
            ->where('user_id', $userId)
            ->where('device_id', $deviceId)
            ->where('face_template_set_id', $setId)
            ->where('template_type', 'face')
            ->pluck('template_index')
            ->filter(fn ($index) => $index !== null)
            ->map(fn ($index) => (int) $index)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $indices === self::EXPECTED_FACE_TEMPLATE_INDICES;
    }

    /** Read the original ADMS BIODATA Index field without relying on its casing. */
    private function faceTemplateIndex(array $record): ?int
    {
        foreach ((array) ($record['extra_fields'] ?? []) as $key => $value) {
            if (strtolower((string) $key) === 'index' && is_numeric($value)) {
                $index = (int) $value;

                return $index >= 0 && $index <= 77 ? $index : null;
            }
        }

        return null;
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
        if (! config('attendanceintegration.push.debug_snapshots', false)) {
            return;
        }

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
