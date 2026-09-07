<?php

namespace Modules\FingerprintDevices\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Services\DeviceCommandService;

/**
 * Replays user DELETEs held by the bulk-delete circuit breaker.
 *
 * Held deletes are employee deletions that arrived in a burst and were
 * deliberately NOT sent to terminals. An operator runs this explicitly
 * after review — each replayed PIN is removed from the held list only
 * after its commands are queued.
 */
class ProcessSkippedDeletes extends Command
{
    protected $signature = 'fingerprints:process-skipped-deletes
                            {--dry-run : Show held PINs without queuing}';

    protected $description = 'Replay user deletions held by the bulk-delete circuit breaker';

    public function handle(DeviceCommandService $commandService): int
    {
        $skipped = Cache::get('adms:skipped_user_deletes', []);
        if (empty($skipped)) {
            $this->info('No held user deletions.');

            return self::SUCCESS;
        }

        $devices = FingerprintDevice::query()
            ->with('deviceType')
            ->where('is_push_enabled', true)
            ->get()
            ->filter(fn (FingerprintDevice $device) => $device->getDriverName() === 'zkteco');

        if ($devices->isEmpty()) {
            $this->error('No ZKTeco push-enabled devices found.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $done = 0;

        foreach (array_keys($skipped) as $pin) {
            $pin = (string) $pin;
            if ($dryRun) {
                $this->line("  [DRY RUN] would queue DELETE for PIN {$pin} on {$devices->count()} device(s)");

                continue;
            }

            foreach ($devices as $device) {
                $commandService->queueUserDelete($device->id, $pin);
            }
            unset($skipped[$pin]);
            $done++;
            $this->line("  queued DELETE for PIN {$pin}");
        }

        if (! $dryRun) {
            Cache::put('adms:skipped_user_deletes', $skipped, now()->addDay());
            $this->info("Replayed {$done} held deletion(s).");
        } else {
            $this->info('Dry run complete — nothing was queued.');
        }

        return self::SUCCESS;
    }
}
