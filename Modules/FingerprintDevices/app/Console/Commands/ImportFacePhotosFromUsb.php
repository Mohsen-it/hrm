<?php

namespace Modules\FingerprintDevices\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Modules\Users\Models\User;

/**
 * ImportFacePhotosFromUsb — import face photos from a ZKTeco USB backup.
 *
 * Reads user.dat to map uid → employee_id, then copies matching
 * JPG photos from the photo/ directory into storage/app/face_photos/
 * and creates user_fingerprints records with template_format='face_photo'.
 */
class ImportFacePhotosFromUsb extends Command
{
    protected $signature = 'fingerprints:import-face-photos
                            {backupPath : Path to the ZKTeco USB backup directory}
                            {--device= : Device ID to associate with}';

    protected $description = 'Import face photos from ZKTeco USB backup into the database';

    public function handle(): int
    {
        $backupPath = $this->argument('backupPath');
        $deviceId = $this->option('device');

        if (! is_dir($backupPath)) {
            $this->error("Backup path not found: {$backupPath}");

            return 1;
        }

        $userDatPath = $backupPath.'/user.dat';
        $photoDir = $backupPath.'/photo';

        if (! file_exists($userDatPath)) {
            $this->error("user.dat not found in {$backupPath}");

            return 1;
        }

        if (! is_dir($photoDir)) {
            $this->error("photo/ directory not found in {$backupPath}");

            return 1;
        }

        $this->info('Parsing user.dat...');

        $users = $this->parseUserDat($userDatPath);
        $this->info('Found '.count($users).' users in user.dat');

        $photos = collect(File::files($photoDir))
            ->filter(fn ($f) => strtolower($f->getExtension()) === 'jpg')
            ->keyBy(fn ($f) => strtolower($f->getFilenameWithoutExtension()));

        $this->info('Found '.count($photos).' photos in photo/');

        $storagePath = storage_path('app/face_photos');
        if (! is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $imported = 0;
        $skipped = 0;
        $notFound = 0;

        foreach ($users as $user) {
            $employeeId = $user['user_id'];
            $photoKey = strtolower($employeeId);

            if (! isset($photos[$photoKey])) {
                $notFound++;

                continue;
            }

            $dbUser = User::whereRaw('LOWER(employee_code) = LOWER(?)', [$employeeId])->first();
            if (! $dbUser) {
                $this->line("  Skip: employee_code '{$employeeId}' not found in users table");
                $skipped++;

                continue;
            }

            $photoFile = $photos[$photoKey];
            $destPath = $storagePath."/{$dbUser->id}.jpg";
            File::copy($photoFile->getPathname(), $destPath);

            $existing = DB::table('user_fingerprints')
                ->where('user_id', $dbUser->id)
                ->where('finger_id', 50)
                ->where('template_format', 'face_photo')
                ->first();

            if (! $existing) {
                DB::table('user_fingerprints')->insert([
                    'user_id' => $dbUser->id,
                    'device_id' => $deviceId,
                    'finger_id' => 50,
                    'template_data' => null,
                    'template_format' => 'face_photo',
                    'template_version' => 0,
                    'quality' => 0,
                    'is_master' => true,
                    'face_photo_path' => "face_photos/{$dbUser->id}.jpg",
                    'captured_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('user_fingerprints')
                    ->where('id', $existing->id)
                    ->update([
                        'face_photo_path' => "face_photos/{$dbUser->id}.jpg",
                        'device_id' => $deviceId,
                        'updated_at' => now(),
                    ]);
            }

            $imported++;
            if ($imported % 50 === 0) {
                $this->line("  Imported {$imported}...");
            }
        }

        $this->newLine();
        $this->info('=== Import Complete ===');
        $this->info("  Imported: {$imported}");
        $this->info("  Skipped (no DB user): {$skipped}");
        $this->info("  Not found (no photo): {$notFound}");

        return 0;
    }

    private function parseUserDat(string $path): array
    {
        $bytes = file_get_contents($path);
        $users = [];
        $recordSize = 72;
        $total = (int) (strlen($bytes) / $recordSize);

        for ($i = 0; $i < $total; $i++) {
            $offset = $i * $recordSize;
            $uid = unpack('v', substr($bytes, $offset, 2))[1];
            $userId = rtrim(substr($bytes, $offset + 48, 9), "\0");

            $users[] = [
                'uid' => $uid,
                'user_id' => $userId,
            ];
        }

        return $users;
    }
}
