<?php

namespace Modules\FingerprintDevices\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\UserFingerprint;

class FingerprintTemplateDistributionService
{
    public function __construct(
        private DeviceCommandService $commandService,
    ) {}

    /**
     * Queue the freshest captured fingerprint templates of the given
     * employees for delivery to a terminal over the ADMS push channel.
     *
     * This is the fingerprint mirror of FaceTemplateDistributionService:
     * one device_commands row (type fp_template) per finger index, with
     * correlation-id idempotency handled inside
     * DeviceCommandService::queueFingerprintTemplate. The write body is a
     * byte-exact mirror of the terminals' own Type=1 BIODATA uploads
     * (``MajorVer=10`` / ``Format=ZK``).
     *
     * @param  array<int, int>  $userIds
     * @return array{queued_fp_templates:int,duplicate_fp_commands:int,skipped_fp_templates:int,failed_fp_templates:int,errors:array<int,string>}
     */
    public function queueForDevice(FingerprintDevice $device, array $userIds): array
    {
        if ($device->getDriverName() !== 'zkteco') {
            return $this->unsupportedDeviceResult();
        }

        return $this->pushForUsers($device, $userIds);
    }

    /**
     * Queue the freshest fingerprint templates of a single employee PIN.
     *
     * @return array{queued_fp_templates:int,duplicate_fp_commands:int,skipped_fp_templates:int,failed_fp_templates:int,errors:array<int,string>}
     */
    public function queuePinForDevice(FingerprintDevice $device, string $pin): array
    {
        if ($device->getDriverName() !== 'zkteco') {
            return $this->unsupportedDeviceResult();
        }

        $userId = DB::table('users')
            ->where('employee_code', trim($pin))
            ->value('id');

        if (! $userId) {
            return [
                'queued_fp_templates' => 0,
                'duplicate_fp_commands' => 0,
                'skipped_fp_templates' => 1,
                'failed_fp_templates' => 0,
                'errors' => [],
            ];
        }

        return $this->pushForUsers($device, [(int) $userId]);
    }

    /**
     * @param  array<int, int>  $userIds
     * @return array{queued_fp_templates:int,duplicate_fp_commands:int,skipped_fp_templates:int,failed_fp_templates:int,errors:array<int,string>}
     */
    private function pushForUsers(FingerprintDevice $device, array $userIds): array
    {
        $totals = [
            'queued_fp_templates' => 0,
            'duplicate_fp_commands' => 0,
            'skipped_fp_templates' => 0,
            'failed_fp_templates' => 0,
        ];
        $errors = [];

        $pins = DB::table('users')
            ->whereIn('id', $userIds)
            ->whereNotNull('employee_code')
            ->where('employee_code', '!=', '')
            ->pluck('employee_code', 'id')
            ->all();

        foreach ($pins as $userId => $pin) {
            $pin = trim((string) $pin);
            if ($pin === '') {
                continue;
            }

            // Freshest captured template per finger index (0-9) for this
            // employee. Result set is tiny (<= 10 rows), so PHP-side
            // de-duplication keeps the query simple and portable.
            $rows = UserFingerprint::query()
                ->where('user_id', $userId)
                ->where('template_type', 'fingerprint')
                ->orderByDesc('captured_at')
                ->orderByDesc('id')
                ->get(['template_index', 'finger_id', 'template_data', 'template_metadata', 'template_version']);

            $freshest = [];
            foreach ($rows as $row) {
                $index = $this->normalizeIndex($row);
                if ($index === null || isset($freshest[$index])) {
                    continue;
                }
                $freshest[$index] = $row;
            }
            ksort($freshest);

            if (empty($freshest)) {
                $totals['skipped_fp_templates']++;

                continue;
            }

            foreach ($freshest as $index => $row) {
                if (trim((string) $row->template_data) === '') {
                    $totals['skipped_fp_templates']++;

                    continue;
                }

                try {
                    $metadata = is_array($row->template_metadata)
                        ? $row->template_metadata
                        : (json_decode((string) $row->template_metadata, true) ?: []);
                    $version = (int) $row->template_version;

                    $attributes = [
                        'no' => (int) ($metadata['No'] ?? $metadata['no'] ?? 0),
                        'index' => $index,
                        'valid' => (int) ($metadata['Valid'] ?? $metadata['valid'] ?? 1),
                        'duress' => (int) ($metadata['Duress'] ?? $metadata['duress'] ?? 0),
                        'major_ver' => (int) ($metadata['MajorVer'] ?? $metadata['major_ver'] ?? 10),
                        'minor_ver' => (int) ($metadata['MinorVer'] ?? $metadata['minor_ver'] ?? 0),
                    ];

                    $hash = hash('sha256', (string) $row->template_data);

                    $command = $this->commandService->queueFingerprintTemplate(
                        $device->id,
                        $pin,
                        (string) $row->template_data,
                        $attributes,
                        $hash,
                    );

                    // wasRecentlyCreated=false means idempotency kicked in
                    // (already pending/completed/failed) — count as duplicate
                    // so bulk runs report honestly instead of looking "new".
                    if ($command->wasRecentlyCreated) {
                        $totals['queued_fp_templates']++;
                    } else {
                        $totals['duplicate_fp_commands']++;
                    }
                } catch (\Throwable $exception) {
                    $totals['failed_fp_templates']++;
                    $errors[] = "Fingerprint template index {$index} for pin {$pin}: {$exception->getMessage()}";
                }
            }

            Log::info('FINGERPRINT_TEMPLATE_ADMS_QUEUED', [
                'device_id' => $device->id,
                'device_serial' => $device->serial_number,
                'employee_pin' => $pin,
                'templates' => count($freshest),
            ]);
        }

        return [...$totals, 'errors' => $errors];
    }

    /**
     * Normalize a stored row to a finger index in 0-9, or null when the
     * row carries no usable index.
     */
    private function normalizeIndex(UserFingerprint $row): ?int
    {
        $metadata = is_array($row->template_metadata)
            ? $row->template_metadata
            : (json_decode((string) $row->template_metadata, true) ?: []);

        $candidates = [
            $metadata['Index'] ?? null,
            $metadata['index'] ?? null,
            $row->template_index,
            $row->finger_id,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === null || ! is_numeric($candidate)) {
                continue;
            }
            $index = (int) $candidate;
            if ($index >= 0 && $index <= 9) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return array{queued_fp_templates:int,duplicate_fp_commands:int,skipped_fp_templates:int,failed_fp_templates:int,errors:array<int,string>}
     */
    private function unsupportedDeviceResult(): array
    {
        return [
            'queued_fp_templates' => 0,
            'duplicate_fp_commands' => 0,
            'skipped_fp_templates' => 0,
            'failed_fp_templates' => 1,
            'errors' => ['Fingerprint-template distribution is only supported for ZKTeco devices.'],
        ];
    }
}
