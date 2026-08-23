<?php

namespace Modules\FingerprintDevices\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\FingerprintDevices\Models\DeviceCommand;

class RetryFailedFaceCommands extends Command
{
    protected $signature = 'fingerprints:retry-failed-faces
                            {--limit=200 : Maximum number of failed face commands to re-queue per run}
                            {--device= : Only re-queue commands for this device id or serial}
                            {--hours=720 : Only re-queue commands failed within this many hours (default 30 days)}';

    protected $description = 'Re-queue failed face-template commands so the next device poll retries them';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $hours = (int) $this->option('hours');

        $query = DB::table('device_commands')
            ->where('command_type', DeviceCommand::TYPE_FACE_TEMPLATE)
            ->where('status', DeviceCommand::STATUS_FAILED)
->where('command_body', 'like', 'DATA UPDATE FACE%')
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

        $ids = (clone $query)->orderBy('updated_at')->limit($limit)->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('No failed face-template commands to retry.');

            return self::SUCCESS;
        }

        $updated = DB::table('device_commands')
            ->whereIn('id', $ids)
            ->update([
                'status' => DeviceCommand::STATUS_PENDING,
                'retry_count' => 0,
                'max_retries' => 30,
                'sent_at' => null,
                'error_message' => null,
                'expires_at' => null,
                'available_at' => null,
            ]);

        $this->info(sprintf('Re-queued %d failed face-template command(s) for retry.', $updated));

        return self::SUCCESS;
    }
}
