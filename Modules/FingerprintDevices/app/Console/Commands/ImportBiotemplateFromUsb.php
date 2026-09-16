<?php

namespace Modules\FingerprintDevices\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\Users\Models\User;

/**
 * ImportBiotemplateFromUsb — import ZKTeco USB biotemplate.dat into user_fingerprints.
 *
 * The file is a text dump, one record per line:
 *   Pin=20061  No=0  Index=12  Valid=1  Duress=0  Type=2  MajorVer=12  MinorVer=0  Format=0  Tmp=<base64>
 *
 * Type=2 (face) and Type=1 (fingerprint) are both supported. Storage mirrors
 * BiodataIngestionService so downstream ADMS distribution (queueFaceTemplate /
 * queueFingerprintTemplate) works unchanged: dedupe is by user_id + sha256(tmp).
 */
class ImportBiotemplateFromUsb extends Command
{
    protected $signature = 'fingerprints:import-biotemplate
                            {file=D:\hrm\data\biotemplate.dat : Path to biotemplate.dat}
                            {--device= : Device ID to associate (nullable)}
                            {--serial=usb-import : device_serial value stored on rows}
                            {--dry-run : Parse and report only, write nothing}';

    protected $description = 'Import ZKTeco USB biotemplate.dat (face/fp templates) into user_fingerprints';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $deviceId = $this->option('device') !== null ? (int) $this->option('device') : null;
        $serial = (string) ($this->option('serial') ?: 'usb-import');
        $dryRun = (bool) $this->option('dry-run');

        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        // Preload PIN -> User map in one query (case-insensitive via lower()).
        $usersByPin = User::query()
            ->whereNotNull('employee_code')
            ->where('employee_code', '!=', '')
            ->get(['id', 'employee_code'])
            ->keyBy(fn (User $u) => strtolower(trim((string) $u->employee_code)));

        $this->info('Users in DB: '.$usersByPin->count());

        $handle = fopen($file, 'r');
        if ($handle === false) {
            $this->error("Cannot open: {$file}");

            return self::FAILURE;
        }

        $totals = ['lines' => 0, 'face' => 0, 'fp' => 0, 'other' => 0, 'no_user' => 0, 'empty_tmp' => 0, 'duplicate' => 0, 'saved' => 0];
        $noUserPins = [];
        $batchHashes = []; // hashes seen in this file per user, to skip intra-file dupes in dry-run too
        $existingCache = []; // "userId:hash" => true (lazy-loaded in chunks)

        // Collect all hashes first? File is ~37MB / 14k lines — stream it, lazy-check DB per 500.
        $pending = [];

        $flush = function () use (&$pending, &$totals, &$existingCache, $dryRun): void {
            if (empty($pending)) {
                return;
            }
            $hashes = array_unique(array_column($pending, 'hash'));
            $userIds = array_unique(array_column($pending, 'user_id'));
            $rows = UserFingerprint::query()
                ->whereIn('user_id', $userIds)
                ->whereIn('template_hash', $hashes)
                ->pluck('template_hash', 'user_id')
                ->toArray();
            // pluck gives user_id=>hash (last wins); rebuild as set of "uid:hash"
            $dbSet = [];
            $check = UserFingerprint::query()
                ->whereIn('user_id', $userIds)
                ->whereIn('template_hash', $hashes)
                ->get(['user_id', 'template_hash']);
            foreach ($check as $r) {
                $dbSet[$r->user_id.':'.$r->template_hash] = true;
            }
            $existingCache += $dbSet;

            $toInsert = [];
            foreach ($pending as $rec) {
                $key = $rec['user_id'].':'.$rec['hash'];
                if (isset($existingCache[$key])) {
                    $totals['duplicate']++;

                    continue;
                }
                $existingCache[$key] = true;
                $totals['saved']++;
                if (! $dryRun) {
                    $toInsert[] = $rec['row'];
                }
            }
            if (! $dryRun && ! empty($toInsert)) {
                DB::table('user_fingerprints')->insert($toInsert);
            }
            $pending = [];
        };

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $totals['lines']++;

            $rec = $this->parseLine($line);
            if ($rec === null || ($rec['tmp'] ?? '') === '') {
                $totals['empty_tmp']++;

                continue;
            }

            $pin = strtolower(trim((string) ($rec['pin'] ?? '')));
            if ($pin === '') {
                $totals['empty_tmp']++;

                continue;
            }

            $user = $usersByPin[$pin] ?? null;
            if (! $user) {
                $totals['no_user']++;
                $noUserPins[$pin] = ($noUserPins[$pin] ?? 0) + 1;

                continue;
            }

            $type = (int) ($rec['type'] ?? 0);
            $hash = hash('sha256', $rec['tmp']);
            $userKey = $user->id.':'.$hash;
            if (isset($batchHashes[$userKey])) {
                $totals['duplicate']++;

                continue;
            }
            $batchHashes[$userKey] = true;

            if ($type === 2) {
                $totals['face']++;
                $index = max(0, (int) ($rec['index'] ?? 0));
                $version = (int) ($rec['major_ver'] ?? 0).((int) ($rec['minor_ver'] ?? 0));
                $pending[] = [
                    'user_id' => $user->id,
                    'hash' => $hash,
                    'row' => [
                        'user_id' => $user->id,
                        'device_id' => $deviceId,
                        'finger_id' => 50 + $index,
                        'template_data' => $rec['tmp'],
                        'template_format' => 'zkteco-face-push',
                        'template_type' => 'face',
                        'template_index' => $index,
                        'device_serial' => $serial,
                        'template_hash' => $hash,
                        'template_metadata' => json_encode([
                            'Pin' => $rec['pin'],
                            'No' => (int) ($rec['no'] ?? 0),
                            'Index' => $index,
                            'Valid' => (int) ($rec['valid'] ?? 1),
                            'Duress' => (int) ($rec['duress'] ?? 0),
                            'Type' => $type,
                            'MajorVer' => (int) ($rec['major_ver'] ?? 12),
                            'MinorVer' => (int) ($rec['minor_ver'] ?? 0),
                            'Format' => (int) ($rec['format'] ?? 0),
                            'source' => 'usb-import:biotemplate.dat',
                        ], JSON_UNESCAPED_UNICODE),
                        'template_version' => (int) $version,
                        'quality' => 0,
                        'is_master' => $index === 0,
                        'captured_at' => now(),
                        'synced_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ];
            } elseif ($type === 1) {
                $totals['fp']++;
                $slot = max(0, min(9, (int) ($rec['no'] ?? $rec['index'] ?? 0)));
                $version = (int) ($rec['major_ver'] ?? 0).((int) ($rec['minor_ver'] ?? 0));
                $pending[] = [
                    'user_id' => $user->id,
                    'hash' => $hash,
                    'row' => [
                        'user_id' => $user->id,
                        'device_id' => $deviceId,
                        'finger_id' => $slot,
                        'template_data' => $rec['tmp'],
                        'template_format' => 'zkteco-fp-push',
                        'template_type' => 'fingerprint',
                        'template_index' => $slot,
                        'device_serial' => $serial,
                        'template_hash' => $hash,
                        'template_metadata' => json_encode([
                            'Pin' => $rec['pin'],
                            'No' => (int) ($rec['no'] ?? $slot),
                            'Index' => (int) ($rec['index'] ?? 0),
                            'Valid' => (int) ($rec['valid'] ?? 1),
                            'Duress' => (int) ($rec['duress'] ?? 0),
                            'Type' => $type,
                            'MajorVer' => (int) ($rec['major_ver'] ?? 10),
                            'MinorVer' => (int) ($rec['minor_ver'] ?? 0),
                            'source' => 'usb-import:biotemplate.dat',
                        ], JSON_UNESCAPED_UNICODE),
                        'template_version' => (int) $version,
                        'quality' => 0,
                        'is_master' => $slot === 0,
                        'captured_at' => now(),
                        'synced_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ];
            } else {
                $totals['other']++;
            }

            if (count($pending) >= 500) {
                $flush();
                $this->output->write('.');
            }
        }
        fclose($handle);
        $flush();
        $this->newLine();

        $this->info('=== Result '.($dryRun ? '(DRY-RUN, nothing written)' : '').' ===');
        foreach ($totals as $k => $v) {
            $this->line("  {$k}: {$v}");
        }
        if (! empty($noUserPins)) {
            arsort($noUserPins);
            $this->warn('Top 20 PINs with no matching users.employee_code:');
            foreach (array_slice($noUserPins, 0, 20, true) as $pin => $cnt) {
                $this->line("  {$pin}: {$cnt} record(s)");
            }
            $this->line('  ... total missing PINs: '.count($noUserPins));
        }

        return self::SUCCESS;
    }

    /** Parse one "K=V tab-separated" biotemplate line. */
    private function parseLine(string $line): ?array
    {
        $out = [];
        foreach (explode("\t", $line) as $part) {
            $pos = strpos($part, '=');
            if ($pos === false) {
                continue;
            }
            $key = strtolower(trim(substr($part, 0, $pos)));
            $val = trim(substr($part, $pos + 1));
            $out[$key] = $val;
        }
        if (! isset($out['pin']) && isset($out['pin '])) {
            $out['pin'] = $out['pin '];
        }

        return $out !== [] ? $out : null;
    }
}
