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

        foreach ($records as $record) {
            try {
                $result = $this->ingestSingle($device, $record, $correlationId);

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
     * @return 'saved'|'skipped'
     */
    private function ingestSingle(
        ?FingerprintDevice $device,
        array $record,
        string $correlationId,
    ): string {
        $pin = $record['pin'];
        $contentBase64 = $record['content_base64'];

        Log::channel('biodata')->info('USERPIC_RECEIVED', [
            'correlation_id' => $correlationId,
            'device_serial' => $device?->serial_number,
            'pin' => $pin,
            'filename' => $record['filename'],
            'declared_size' => $record['size'],
            'base64_length' => strlen($contentBase64),
        ]);

        if ($contentBase64 === '') {
            Log::channel('biodata')->warning('USERPIC_EMPTY_CONTENT', [
                'correlation_id' => $correlationId,
                'pin' => $pin,
            ]);

            return 'skipped';
        }

        $imageData = base64_decode($contentBase64, true);

        if ($imageData === false || strlen($imageData) < 100) {
            Log::channel('biodata')->warning('USERPIC_INVALID_IMAGE', [
                'correlation_id' => $correlationId,
                'pin' => $pin,
                'decoded_length' => $imageData ? strlen($imageData) : 0,
            ]);

            return 'skipped';
        }

        $user = $this->resolveUser($pin);

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
            'user_name' => $user->full_name_ar ?? $user->name,
            'filename' => $filename,
            'image_size' => strlen($imageData),
        ]);

        return 'saved';
    }

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
        }

        return $user;
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
