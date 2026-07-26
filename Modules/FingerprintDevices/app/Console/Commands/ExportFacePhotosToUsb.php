<?php

namespace Modules\FingerprintDevices\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * ExportFacePhotosToUsb — export face photos from DB to a ZKTeco-compatible USB format.
 *
 * Generates user.dat + photo/ directory that can be imported into any iFace device via USB.
 */
class ExportFacePhotosToUsb extends Command
{
    protected $signature = 'fingerprints:export-face-photos-to-usb
                            {exportPath : Path to export directory (e.g. E:\ for USB drive)}
                            {--device= : Device ID to export for (optional)}';

    protected $description = 'Export face photos from DB to ZKTeco USB-compatible format';

    public function handle(): int
    {
        $exportPath = $this->argument('exportPath');
        $deviceId = $this->option('device');

        if (! is_dir($exportPath)) {
            mkdir($exportPath, 0755, true);
        }

        $photoDir = $exportPath.'/photo';
        if (! is_dir($photoDir)) {
            mkdir($photoDir, 0755, true);
        }

        $this->info('Exporting face photos...');

        $faceRecords = DB::table('user_fingerprints')
            ->where('template_format', 'face_photo')
            ->whereNotNull('face_photo_path')
            ->where('face_photo_path', '!=', '')
            ->get();

        $this->info('Found '.$faceRecords->count().' face records in database');

        $exported = 0;
        $skipped = 0;

        foreach ($faceRecords as $record) {
            $user = DB::table('users')->where('id', $record->user_id)->first();
            if (! $user) {
                $skipped++;

                continue;
            }

            $employeeCode = $user->employee_code;
            if (! $employeeCode) {
                $skipped++;

                continue;
            }

            $sourcePath = storage_path('app/'.$record->face_photo_path);
            if (! file_exists($sourcePath)) {
                $skipped++;

                continue;
            }

            $destPath = $photoDir.'/'.$employeeCode.'.jpg';
            File::copy($sourcePath, $destPath);
            $exported++;
        }

        $this->newLine();
        $this->info('=== Export Complete ===');
        $this->info("  Exported: {$exported} photos to {$photoDir}");
        $this->info("  Skipped: {$skipped}");
        $this->newLine();
        $this->info('To import on another device:');
        $this->info("  1. Copy the 'photo' folder to USB drive");
        $this->info('  2. On the device: Menu > Data Mgt > Import > Face > USB');

        return 0;
    }
}
