<?php

namespace Modules\FingerprintDevices\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\FingerprintDevices\Models\FingerprintDevice;

class FaceTemplateDistributionService
{
    public function __construct(
        private DeviceCommandService $commandService,
    ) {}

    /**
     * Queue the freshest captured face sets of the given employees for
     * delivery to a terminal over the ADMS push channel.
     *
     * The write body built by DeviceCommandService::queueFaceTemplate is a
     * byte-exact mirror of the terminals' own BIODATA uploads (tab
     * separated, ``MajorVer``/``MinorVer`` casing) — verified with
     * Return=0 on the iFace880 Plus fleet.
     *
     * @param  array<int, int>  $userIds
     * @return array{queued_face_templates:int,duplicate_face_commands:int,skipped_face_templates:int,failed_face_templates:int,errors:array<int,string>}
     */
    public function queueForDevice(FingerprintDevice $device, array $userIds): array
    {
        if ($device->getDriverName() !== 'zkteco') {
            return $this->unsupportedDeviceResult();
        }

        return $this->pushForUsers($device, $userIds);
    }

    /**
     * Queue one validated enrollment set for a target device.
     *
     * @return array{queued_face_templates:int,duplicate_face_commands:int,skipped_face_templates:int,failed_face_templates:int,errors:array<int,string>}
     */
    public function queueSetForDevice(
        FingerprintDevice $device,
        int $userId,
        string $sourceSerial,
        string $setId,
    ): array {
        if ($device->getDriverName() !== 'zkteco') {
            return $this->unsupportedDeviceResult();
        }

        return $this->pushForUsers($device, [$userId]);
    }

    /**
     * @param  array<int, int>  $userIds
     * @return array{queued_face_templates:int,duplicate_face_commands:int,skipped_face_templates:int,failed_face_templates:int,errors:array<int,string>}
     */
    private function pushForUsers(FingerprintDevice $device, array $userIds): array
    {
        $totals = [
            'queued_face_templates' => 0,
            'duplicate_face_commands' => 0,
            'skipped_face_templates' => 0,
            'failed_face_templates' => 0,
        ];
        $errors = [];

        $pins = DB::table('users')
            ->whereIn('id', $userIds)
            ->whereNotNull('employee_code')
            ->where('employee_code', '!=', '')
            ->pluck('employee_code')
            ->all();

        foreach ($pins as $pin) {
            $pin = trim((string) $pin);
            if ($pin === '') {
                continue;
            }

            // Freshest captured template per index across all source devices.
            $rows = DB::select(
                'SELECT uf.template_index, uf.template_data, uf.template_metadata, uf.template_version
                 FROM user_fingerprints uf
                 JOIN users u ON u.id = uf.user_id
                 WHERE u.employee_code = ? AND uf.template_type = "face"
                   AND uf.captured_at = (
                       SELECT MAX(c2.captured_at) FROM user_fingerprints c2
                       JOIN users u3 ON u3.id = c2.user_id
                       WHERE u3.employee_code = ? AND c2.template_index = uf.template_index
                   )
                 ORDER BY uf.template_index',
                [$pin, $pin],
            );

            if (empty($rows)) {
                $totals['skipped_face_templates']++;

                continue;
            }

            foreach ($rows as $row) {
                if (trim((string) $row->template_data) === '') {
                    $totals['skipped_face_templates']++;

                    continue;
                }

                try {
                    $metadata = json_decode((string) $row->template_metadata, true) ?: [];
                    $index = (int) ($metadata['Index'] ?? $row->template_index);
                    $version = (int) $row->template_version;

                    $attributes = [
                        'no' => (int) ($metadata['No'] ?? 0),
                        'index' => max(0, $index),
                        'valid' => (int) ($metadata['Valid'] ?? 1),
                        'duress' => (int) ($metadata['Duress'] ?? 0),
                        // uploads carry MajorVer/MinorVer as separate ints
                        'major_ver' => (int) ($metadata['MajorVer'] ?? intdiv($version, 10)),
                        'minor_ver' => (int) ($metadata['MinorVer'] ?? ($version % 10)),
                        'format' => (int) ($metadata['Format'] ?? 0),
                    ];

                    $hash = hash('sha256', (string) $row->template_data);

                    $this->commandService->queueFaceTemplate(
                        $device->id,
                        $pin,
                        (string) $row->template_data,
                        $attributes,
                        $hash,
                    );

                    $totals['queued_face_templates']++;
                } catch (\Throwable $exception) {
                    $totals['failed_face_templates']++;
                    $errors[] = "Face template {$row->template_index} for pin {$pin}: {$exception->getMessage()}";
                }
            }

            Log::info('FACE_TEMPLATE_ADMS_QUEUED', [
                'device_id' => $device->id,
                'device_serial' => $device->serial_number,
                'employee_pin' => $pin,
                'templates' => count($rows),
            ]);
        }

        return [...$totals, 'errors' => $errors];
    }

    /**
     * @return array{queued_face_templates:int,duplicate_face_commands:int,skipped_face_templates:int,failed_face_templates:int,errors:array<int,string>}
     */
    private function unsupportedDeviceResult(): array
    {
        return [
            'queued_face_templates' => 0,
            'duplicate_face_commands' => 0,
            'skipped_face_templates' => 0,
            'failed_face_templates' => 1,
            'errors' => ['Face-template distribution is only supported for ZKTeco devices.'],
        ];
    }
}
