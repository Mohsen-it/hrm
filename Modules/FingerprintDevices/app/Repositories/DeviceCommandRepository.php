<?php

namespace Modules\FingerprintDevices\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\FingerprintDevices\Models\DeviceCommand;

class DeviceCommandRepository
{
    public function __construct(
        protected DeviceCommand $model,
    ) {}

    public function create(array $data): DeviceCommand
    {
        return $this->model->create($data);
    }

    public function findById(int $id): ?DeviceCommand
    {
        return $this->model->find($id);
    }

    /**
     * Fetch the next batch of pending commands for a device (ordered by priority ASC, created ASC).
     *
     * @return Collection<int, DeviceCommand>
     */
    public function fetchPendingForDevice(int $deviceId, int $limit = 10): Collection
    {
        return $this->model
            ->where('device_id', $deviceId)
            ->where('status', DeviceCommand::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('priority')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get the next single pending command (for ADMS getrequest protocol).
     */
    public function nextPendingForDevice(int $deviceId): ?DeviceCommand
    {
        return $this->model
            ->where('device_id', $deviceId)
            ->where('status', DeviceCommand::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('priority')
            ->orderBy('created_at')
            ->first();
    }

    /**
     * Mark commands as sending (claim for processing).
     */
    public function claimPending(int $deviceId, int $limit = 1): Collection
    {
        $commands = $this->fetchPendingForDevice($deviceId, $limit);

        foreach ($commands as $cmd) {
            $cmd->update([
                'status' => DeviceCommand::STATUS_SENDING,
                'sent_at' => now(),
            ]);
        }

        return $commands;
    }

    /**
     * Get retryable failed commands for a device.
     */
    public function retryableForDevice(int $deviceId): Collection
    {
        return $this->model
            ->where('device_id', $deviceId)
            ->where('status', DeviceCommand::STATUS_FAILED)
            ->whereColumn('retry_count', '<', 'max_retries')
            ->orderBy('priority')
            ->orderBy('created_at')
            ->limit(20)
            ->get();
    }

    /**
     * Cancel all pending commands for a device.
     */
    public function cancelPendingForDevice(int $deviceId): int
    {
        return $this->model
            ->where('device_id', $deviceId)
            ->where('status', DeviceCommand::STATUS_PENDING)
            ->update(['status' => DeviceCommand::STATUS_CANCELLED]);
    }

    /**
     * Expire old pending commands.
     */
    public function expireStaleCommands(int $maxAgeMinutes = 60): int
    {
        return $this->model
            ->where('status', DeviceCommand::STATUS_PENDING)
            ->where('created_at', '<', now()->subMinutes($maxAgeMinutes))
            ->update(['status' => DeviceCommand::STATUS_EXPIRED]);
    }

    /**
     * Get queue stats for a device.
     */
    public function getQueueStats(int $deviceId): array
    {
        $counts = $this->model
            ->where('device_id', $deviceId)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'pending' => $counts[DeviceCommand::STATUS_PENDING] ?? 0,
            'sending' => $counts[DeviceCommand::STATUS_SENDING] ?? 0,
            'completed' => $counts[DeviceCommand::STATUS_COMPLETED] ?? 0,
            'failed' => $counts[DeviceCommand::STATUS_FAILED] ?? 0,
            'cancelled' => $counts[DeviceCommand::STATUS_CANCELLED] ?? 0,
            'expired' => $counts[DeviceCommand::STATUS_EXPIRED] ?? 0,
        ];
    }

    /**
     * Build ADMS command body from a DeviceCommand record.
     */
    public function buildAdmsCommandBody(DeviceCommand $command): string
    {
        return $command->command_body;
    }
}
