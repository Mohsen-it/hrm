<?php

namespace Modules\FingerprintDevices\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\FingerprintDevices\Models\DeviceCommand;

class RetryFailedFaceCommands extends Command
{
    protected $signature = 'fingerprints:retry-failed-faces
                            {--limit=50 : Maximum number of failed face commands to re-queue per run}
                            {--device= : Only re-queue commands for this device id or serial}
                            {--hours=72 : Only re-queue commands failed within this many hours (default 3 days)}';

    protected $description = 'Re-queue failed face-template commands so the next device poll retries them (bounded: preserves retry budget, never deletes)';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $hours = (int) $this->option('hours');

        $query = DB::table('device_commands')
            ->where('command_type', DeviceCommand::TYPE_FACE_TEMPLATE)
            ->where('status', DeviceCommand::STATUS_FAILED)
            ->where(function ($q): void {
                $q->where('command_body', 'like', 'DATA UPDATE biodata%')
                    ->orWhere('command_body', 'like', 'DATA UPDATE FACE%');
            })
            ->where('updated_at', '>=', now()->subHours($hours));

        if ($device = $this->option('device')) {
            $query->where(function ($q) use ($device): void {
                $q->where('device_id', $device)->orWhereIn('device_id', function ($sub) use ($device): void {
                    $sub->select('id')
                        ->from('fingerprint_devices')
                        ->where('serial_number', $device);
                });
            });
        }

        $rows = (clone $query)->orderBy('updated_at')->limit($limit)->get(['id', 'retry_count', 'max_retries']);

        if ($rows->isEmpty()) {
            $this->info('No failed face-template commands to retry.');

            return self::SUCCESS;
        }

        $updated = 0;
        foreach ($rows as $row) {
            if ((int) $row->retry_count >= (int) $row->max_retries) {
                continue;
            }
            $backoffSeconds = (int) min(300, 30 * 2 ** max(0, (int) $row->retry_count));
            $updated += DB::table('device_commands')
                ->where('id', $row->id)
                ->where('status', DeviceCommand::STATUS_FAILED)
                ->update([
                    'status' => DeviceCommand::STATUS_PENDING,
                    'sent_at' => null,
                    'available_at' => now()->addSeconds($backoffSeconds),
                ]);
        }

        $this->info(sprintf('Re-queued %d failed face-template command(s) for retry.', $updated));

        return self::SUCCESS;
    }
}
