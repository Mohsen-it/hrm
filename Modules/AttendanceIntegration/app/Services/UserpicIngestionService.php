<?php

namespace Modules\AttendanceIntegration\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\Users\Models\User;

class UserpicIngestionService
{
    /**
     * Process a batch of parsed USERPIC records and save face photos.
     *
     * @return array{saved: int, skipped: int, errors: array<string>}
     */
    public function ingest(
        ?FingerprintDevice $device,
        array $records,
        string $correlationId = '',
    ): array {
        $stats = [
            'saved' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $uniquePins = array_unique(array_map(fn (array $r) => (string) $r['pin'], $records));
        $userMap = $this->resolveUsersBatch($uniquePins);

        foreach ($records as $record) {
            try {
                $result = $this->ingestSingle($device, $record, $correlationId, $userMap);
                $stats[$result]++;
            } catch (\Throwable $e) {
                $stats['errors'][] = "Pin {$record['pin']}: {$e->getMessage()}";
                Log::channel('biodata')->error('USERPIC_INGEST_FAILED', [
                    'correlation_id' => $correlationId,
                    'device_serial' => $device?->serial_number,
                    'pin' => $record['pin'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

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
     * @param  array<string, User>  $userMap
     * @return 'saved'|'skipped'
     */
    private function ingestSingle(
        ?FingerprintDevice $device,
        array $record,
        string $correlationId,
        array $userMap,
    ): string {
        $pin = $record['pin'];
        $contentBase64 = $record['content_base64'];

        if ($contentBase64 === '') {
            return 'skipped';
        }

        $imageData = base64_decode($contentBase64, true);

        if ($imageData === false || strlen($imageData) < 100) {
            return 'skipped';
        }

        $user = $userMap[$pin] ?? null;

        if (! $user) {
            Log::channel('biodata')->warning('USERPIC_EMPLOYEE_NOT_FOUND', [
                'correlation_id' => $correlationId,
                'device_serial' => $device?->serial_number,
                'pin' => $pin,
            ]);

            return 'skipped';
        }

        $filename = $this->savePhoto($user, $device, $imageData, $record['filename']);

        $this->updateUserFacePath($user, $filename, $device);
        $this->updateTemplateFacePath($user, $device, $filename);

        Log::channel('biodata')->info('USERPIC_SAVED_SUCCESSFULLY', [
            'correlation_id' => $correlationId,
            'device_serial' => $device?->serial_number,
            'pin' => $pin,
            'user_id' => $user->id,
            'filename' => $filename,
        ]);

        return 'saved';
    }

    private function savePhoto(
        User $user,
        ?FingerprintDevice $device,
        string $imageData,
        string $originalFilename,
    ): string {
        $serial = $device?->serial_number ?? 'unknown';
        $dir = storage_path("app/face_photos/{$serial}");

        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0755, true, true);
        }

        $filename = "{$user->employee_code}.jpg";
        $filepath = "{$dir}/{$filename}";

        file_put_contents($filepath, $imageData);

        return $filename;
    }

    private function updateUserFacePath(User $user, string $filename, ?FingerprintDevice $device): void
    {
        $serial = $device?->serial_number ?? 'unknown';
        $relativePath = "face_photos/{$serial}/{$filename}";

        $user->update(['face_photo_path' => $relativePath]);
    }

    private function updateTemplateFacePath(User $user, ?FingerprintDevice $device, string $filename): void
    {
        if (! $device) {
            return;
        }

        $serial = $device->serial_number;
        $relativePath = "face_photos/{$serial}/{$filename}";

        UserFingerprint::query()
            ->where('user_id', $user->id)
            ->where('device_id', $device->id)
            ->where('template_format', 'like', '%face%')
            ->update(['face_photo_path' => $relativePath]);
    }
}
