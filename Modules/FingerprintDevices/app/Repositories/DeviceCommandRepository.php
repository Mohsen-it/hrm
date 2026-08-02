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

    public function findByIdForDevice(int $id, int $deviceId): ?DeviceCommand
    {
        return $this->model
            ->whereKey($id)
            ->where('device_id', $deviceId)
            ->first();
    }

    public function findActiveByCorrelation(int $deviceId, string $correlationId): ?DeviceCommand
    {
        return $this->model
            ->where('device_id', $deviceId)
            ->where('correlation_id', $correlationId)
            ->whereIn('status', [
                DeviceCommand::STATUS_PENDING,
                DeviceCommand::STATUS_SENDING,
            ])
            ->first();
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
     * Atomically claim pending commands for a device using a single query.
     *
     * Uses SELECT ... FOR UPDATE SKIP LOCKED to avoid race conditions
     * between concurrent device polls.
     */
    public function claimPending(int $deviceId, int $limit = 1): Collection
    {
        $this->releaseStaleSending($deviceId);

        $now = now();
        $sendAt = $now->toDateTimeString();

        // Atomic claim: SELECT eligible rows, lock them, mark as sending
        $claimedIds = DB::select(
            'SELECT id FROM device_commands
             WHERE device_id = ?
               AND status = ?
               AND (expires_at IS NULL OR expires_at > ?)
             ORDER BY priority, created_at
             LIMIT ?
             FOR UPDATE SKIP LOCKED',
            [$deviceId, DeviceCommand::STATUS_PENDING, $now, $limit]
        );

        if (empty($claimedIds)) {
            return new Collection;
        }

        $ids = array_column($claimedIds, 'id');
        $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));

        DB::update(
            "UPDATE device_commands SET status = ?, sent_at = ? WHERE id IN ($idPlaceholders)",
            array_merge([DeviceCommand::STATUS_SENDING, $sendAt], $ids)
        );

        return $this->model->whereIn('id', $ids)->get();
    }

    /**
     * Return unacknowledged commands to the queue after a delivery timeout.
     */
    public function releaseStaleSending(int $deviceId, int $timeoutMinutes = 10): int
    {
        $stale = $this->model
            ->where('device_id', $deviceId)
            ->where('status', DeviceCommand::STATUS_SENDING)
            ->where('sent_at', '<', now()->subMinutes($timeoutMinutes));

        (clone $stale)
            ->whereColumn('retry_count', '>=', 'max_retries')
            ->update([
                'status' => DeviceCommand::STATUS_FAILED,
                'error_message' => 'Device did not acknowledge the command before the retry limit.',
            ]);

        return (clone $stale)
            ->whereColumn('retry_count', '<', 'max_retries')
            ->increment('retry_count', 1, [
                'status' => DeviceCommand::STATUS_PENDING,
                'sent_at' => null,
            ]);
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
            ->where(function ($query) use ($maxAgeMinutes): void {
                $query->where('expires_at', '<=', now())
                    ->orWhere(function ($fallback) use ($maxAgeMinutes): void {
                        $fallback->whereNull('expires_at')
                            ->where('created_at', '<', now()->subMinutes($maxAgeMinutes));
                    });
            })
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
