<?php

namespace Modules\FingerprintDevices\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\FingerprintDevices\Repositories\UserFingerprintRepository;

class FaceTemplateDistributionService
{
    public function __construct(
        private UserFingerprintRepository $fingerprintRepository,
        private DeviceCommandService $commandService,
    ) {}

    /**
     * Queue saved ADMS face templates for delivery on the target device's next poll.
     *
     * @param  array<int, int>  $userIds
     * @return array{queued_face_templates:int,duplicate_face_commands:int,skipped_face_templates:int,failed_face_templates:int,errors:array<int,string>}
     */
    public function queueForDevice(FingerprintDevice $device, array $userIds): array
    {
        if ($device->getDriverName() !== 'zkteco') {
            return $this->unsupportedDeviceResult();
        }

        $templates = $this->fingerprintRepository->getFaceTemplatesForDistribution(
            $userIds,
            (string) $device->serial_number,
        );

        return $this->queueTemplates($device, $templates);
    }

    /**
     * Queue exactly one validated enrollment set for a target device.
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

        $templates = $this->fingerprintRepository->getFaceTemplateSetForDistribution(
            $userId,
            $sourceSerial,
            $setId,
        );

        return $this->queueTemplates($device, $templates);
    }

    /**
     * @param  Collection<int, UserFingerprint>  $templates
     * @return array{queued_face_templates:int,duplicate_face_commands:int,skipped_face_templates:int,failed_face_templates:int,errors:array<int,string>}
     */
    private function queueTemplates(FingerprintDevice $device, $templates): array
    {
        $totals = [
            'queued_face_templates' => 0,
            'duplicate_face_commands' => 0,
            'skipped_face_templates' => 0,
            'failed_face_templates' => 0,
        ];
        $errors = [];

        foreach ($templates as $template) {
            $pin = trim((string) $template->user?->employee_code);
            if ($pin === '' || trim((string) $template->template_data) === '') {
                $totals['skipped_face_templates']++;

                continue;
            }

            try {
                $attributes = $this->attributes($template);
                $hash = $template->template_hash ?: hash('sha256', (string) $template->template_data);
                $command = $this->commandService->queueFaceTemplate(
                    $device->id,
                    $pin,
                    (string) $template->template_data,
                    $attributes,
                    $hash,
                );

                if ($command->wasRecentlyCreated) {
                    $totals['queued_face_templates']++;
                    Log::info('FACE_TEMPLATE_SYNC_QUEUED', [
                        'device_id' => $device->id,
                        'device_serial' => $device->serial_number,
                        'employee_pin' => $pin,
                        'template_id' => $template->id,
                        'template_hash' => $hash,
                        'command_id' => $command->id,
                        'version' => "{$attributes['major_ver']}.{$attributes['minor_ver']}",
                    ]);
                } else {
                    $totals['duplicate_face_commands']++;
                    Log::info('FACE_TEMPLATE_SYNC_DUPLICATE_IGNORED', [
                        'device_id' => $device->id,
                        'employee_pin' => $pin,
                        'template_id' => $template->id,
                        'command_id' => $command->id,
                    ]);
                }
            } catch (\Throwable $exception) {
                $totals['failed_face_templates']++;
                $errors[] = "Face template {$template->id}: {$exception->getMessage()}";
                Log::error('FACE_TEMPLATE_SYNC_QUEUE_FAILED', [
                    'device_id' => $device->id,
                    'employee_pin' => $pin,
                    'template_id' => $template->id,
                    'reason' => $exception->getMessage(),
                ]);
            }
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
            'errors' => ['ADMS face-template distribution is only supported for ZKTeco devices.'],
        ];
    }

    /**
     * @return array{no:int,index:int,valid:int,duress:int,major_ver:int,minor_ver:int,format:int}
     */
    private function attributes(UserFingerprint $template): array
    {
        $metadata = [];
        foreach ((array) $template->template_metadata as $key => $value) {
            $metadata[strtolower((string) $key)] = $value;
        }

        [$fallbackMajor, $fallbackMinor] = $this->decodeVersion((int) $template->template_version);

        return [
            'no' => (int) ($metadata['no'] ?? 0),
            'index' => (int) ($metadata['index'] ?? max(0, (int) $template->finger_id - 50)),
            'valid' => (int) ($metadata['valid'] ?? 1),
            'duress' => (int) ($metadata['duress'] ?? 0),
            'major_ver' => (int) ($metadata['majorver'] ?? $fallbackMajor),
            'minor_ver' => (int) ($metadata['minorver'] ?? $fallbackMinor),
            'format' => (int) ($metadata['format'] ?? 0),
        ];
    }

    /**
     * Older records stored the version as concatenated digits, for example 12.0 as 120.
     *
     * @return array{int, int}
     */
    private function decodeVersion(int $version): array
    {
        if ($version <= 0) {
            return [0, 0];
        }

        if ($version < 10) {
            return [$version, 0];
        }

        return [intdiv($version, 10), $version % 10];
    }
}
