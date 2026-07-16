<?php

namespace App\Console\Commands;

use App\Services\ZKTecoPythonBridgeService;
use Illuminate\Console\Command;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\FingerprintDevices\Services\FingerprintDeviceService;

/**
 * PullFingerprintsFromDevices — push every stored template to its origin device.
 *
 * Walks the `user_fingerprints` table and, for each row, calls the Python
 * bridge to upload the template back to the device it came from. Templates
 * without a `device_id` are skipped.
 */
class PullFingerprintsFromDevices extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'fingerprints:pull-from-devices
                            {--device= : Restrict the pull to a single device id}
                            {--dry-run : List what would be pulled without contacting the device}';

    /**
     * The console command description.
     */
    protected $description = 'Pull stored fingerprint templates and upload them to their physical devices';

    /**
     * Execute the console command.
     */
    public function handle(
        ZKTecoPythonBridgeService $bridge,
        FingerprintDeviceService $deviceService,
    ): int {
        $deviceId = $this->option('device');
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $bridge->ensureServiceRunning()) {
            $this->components->error('ZKTeco Python bridge is not reachable. Use --dry-run to skip.');

            return self::FAILURE;
        }

        $query = UserFingerprint::query()
            ->whereNotNull('device_id')
            ->with(['device', 'user']);

        if ($deviceId !== null) {
            $query->where('device_id', (int) $deviceId);
        }

        $count = (clone $query)->count();
        $this->info("Found {$count} fingerprint row(s) to push.");

        if ($dryRun) {
            $this->table(
                ['id', 'user', 'device', 'finger_id'],
                $query->limit(20)->get()->map(fn ($f) => [
                    $f->id,
                    optional($f->user)->name,
                    optional($f->device)->name,
                    $f->finger_id,
                ])->all(),
            );

            return self::SUCCESS;
        }

        $uploaded = 0;
        $query->chunkById(200, function ($rows) use ($bridge, &$uploaded): void {
            foreach ($rows as $fingerprint) {
                $device = $fingerprint->device;
                if (! $device instanceof FingerprintDevice) {
                    continue;
                }

                $template = is_resource($fingerprint->template_data)
                    ? stream_get_contents($fingerprint->template_data)
                    : (string) $fingerprint->template_data;

                $result = $bridge->exportTemplate(
                    $device->ip_address,
                    (int) $device->port,
                    (int) $device->comm_key,
                    (int) $fingerprint->user_id,
                    (int) $fingerprint->finger_id,
                    base64_encode($template),
                );

                if (($result['success'] ?? false) === true) {
                    $uploaded++;
                }
            }
        });

        $this->info("Done. Pushed {$uploaded} template(s) to the device(s).");

        return self::SUCCESS;
    }
}
