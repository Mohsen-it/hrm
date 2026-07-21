<?php

namespace Modules\Attendance\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Attendance\Repositories\RawAttendanceLogRepository;

/**
 * RawAttendanceLogService — ingestion pipeline for device punches.
 *
 * Responsibilities:
 *  - Validate and persist raw log payloads coming from fingerprint devices,
 *    ADMS push, or operator manual entry.
 *  - Resolve the `device_user_id` to an internal `user_id` when possible.
 *  - Convert incoming rows into the canonical wire-format used by
 *    `RawAttendanceLogRepository::bulkInsert`.
 *  - Hand over processed rows to `AttendanceSessionService` for correlation.
 *
 * The service stays free of HTTP concerns and is reused by jobs, controllers,
 * and artisan commands alike.
 */
class RawAttendanceLogService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private RawAttendanceLogRepository $repository,
        private AttendanceSessionService $sessionService,
    ) {}

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /**
     * Get a paginated list of raw logs filtered by the supplied filter bag.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAllLogs(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->getAll($filters, $perPage);
    }

    /**
     * Find a raw log by its primary key.
     */
    public function getLogById(int $id): ?RawAttendanceLog
    {
        return $this->repository->findById($id);
    }

    /**
     * Get all unprocessed raw logs (optionally bounded to a time window).
     *
     * @return Collection<int, RawAttendanceLog>
     */
    public function getUnprocessedLogs(?string $fromTime = null, ?string $toTime = null, int $limit = 0): Collection
    {
        return $this->repository->getUnprocessed($fromTime, $toTime, $limit);
    }

    /**
     * Get all raw logs for the given user.
     *
     * @return Collection<int, RawAttendanceLog>
     */
    public function getLogsByUser(int $userId, ?string $fromTime = null, ?string $toTime = null): Collection
    {
        return $this->repository->getByUser($userId, $fromTime, $toTime);
    }

    /**
     * Get all raw logs for the given device.
     *
     * @return Collection<int, RawAttendanceLog>
     */
    public function getLogsByDevice(int $deviceId, ?string $fromTime = null, ?string $toTime = null): Collection
    {
        return $this->repository->getByDevice($deviceId, $fromTime, $toTime);
    }

    /**
     * Count raw logs matching the supplied filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function countLogs(array $filters = []): int
    {
        return $this->repository->count($filters);
    }

    // ------------------------------------------------------------------
    // Writes
    // ------------------------------------------------------------------

    /**
     * Persist a single raw log row.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createLog(array $data): RawAttendanceLog
    {
        $validated = $this->validateLogData($data);

        return $this->repository->create($this->applyTimestamps($validated));
    }

    /**
     * Bulk import a batch of raw logs (e.g. pulled from a device at once).
     *
     * Each row is validated, defaulted with timestamps, and then handed over
     * to the repository for a single `insert` call. Rows that already exist
     * (by `device_user_id` + `punch_time`) are silently skipped.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{inserted: int, skipped: int, total: int}
     */
    public function bulkImport(array $rows): array
    {
        $total = count($rows);
        if ($total === 0) {
            return ['inserted' => 0, 'skipped' => 0, 'total' => 0];
        }

        $inserted = 0;
        $skipped = 0;
        $batch = [];
        $now = now();

        // Pre-load existing records for this batch to avoid N+1 queries
        $existingKeys = $this->getExistingKeys($rows);

        foreach ($rows as $row) {
            try {
                $validated = $this->validateLogData($row);
            } catch (ValidationException) {
                $skipped++;

                continue;
            }

            $punchTime = $validated['punch_time'] instanceof \DateTimeInterface
                ? $validated['punch_time']->format('Y-m-d H:i:s')
                : (string) $validated['punch_time'];

            $normalizedPunchTime = $this->normalizeTimestampForComparison($punchTime);
            $deviceUserId = $validated['device_user_id'] ?? null;
            $deviceId = $validated['device_id'] ?? null;
            $key = "{$deviceUserId}_{$deviceId}_{$normalizedPunchTime}";

            if (isset($existingKeys[$key])) {
                $skipped++;

                continue;
            }

            // Mark as seen to prevent duplicates within the same batch
            $existingKeys[$key] = true;

            $batch[] = array_merge($validated, [
                'processed' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! empty($batch)) {
            $inserted = $this->repository->bulkInsert($batch);
        }

        return [
            'inserted' => $inserted,
            'skipped' => $skipped,
            'total' => $total,
        ];
    }

    /**
     * Process a single unprocessed log: turn it into a session and mark it done.
     */
    public function processLog(RawAttendanceLog $log): ?AttendanceSession
    {
        return $this->sessionService->processRawLog($log);
    }

    /**
     * Process a single chunk of unprocessed logs.
     *
     * @return array{processed: int, sessions: int}
     */
    public function processAllUnprocessed(int $chunkSize = 200): array
    {
        $processed = 0;
        $sessions = 0;

        $logs = $this->repository
            ->query()
            ->unprocessed()
            ->orderBy('punch_time')
            ->limit($chunkSize)
            ->get();

        foreach ($logs as $log) {
            $session = $this->processLog($log);
            $processed++;
            if ($session !== null) {
                $sessions++;
            }
        }

        return [
            'processed' => $processed,
            'sessions' => $sessions,
        ];
    }

    /**
     * Process unprocessed raw logs belonging to a specific device.
     *
     * @return array{processed: int, sessions: int}
     */
    public function processUnprocessedForDevice(int $deviceId, int $limit = 1000): array
    {
        $processed = 0;
        $sessions = 0;

        $logs = $this->repository
            ->query()
            ->unprocessed()
            ->forDevice($deviceId)
            ->orderBy('punch_time')
            ->limit($limit)
            ->get();

        foreach ($logs as $log) {
            $session = $this->processLog($log);
            $processed++;
            if ($session !== null) {
                $sessions++;
            }
        }

        return [
            'processed' => $processed,
            'sessions' => $sessions,
        ];
    }

    /**
     * Mark the supplied raw log ids as processed.
     *
     * @param  array<int, int>  $ids
     */
    public function markProcessed(array $ids, ?\DateTimeInterface $at = null): int
    {
        return $this->repository->markProcessed($ids, $at);
    }

    /**
     * Soft delete a raw log.
     */
    public function deleteLog(RawAttendanceLog $log): bool
    {
        return $this->repository->delete($log);
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    /**
     * Validate a raw log payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validateLogData(array $data): array
    {
        $rules = [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'device_id' => ['nullable', 'integer'],
            'device_user_id' => ['nullable', 'string', 'max:100'],
            'punch_time' => ['required'],
            'punch_type' => ['nullable', 'in:check_in,check_out,unknown'],
            'verify_type' => ['nullable', 'in:fingerprint,card,password,face'],
            'work_code' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'source' => ['nullable', 'in:device,adms,manual,api'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'raw_data' => ['nullable', 'array'],
            'processed' => ['nullable', 'boolean'],
        ];

        $validated = Validator::make($data, $rules)->validate();

        $validated['punch_type'] = $validated['punch_type'] ?? 'unknown';
        $validated['verify_type'] = $validated['verify_type'] ?? 'fingerprint';
        $validated['source'] = $validated['source'] ?? 'device';
        $validated['work_code'] = (int) ($validated['work_code'] ?? 0);

        return $validated;
    }

    /**
     * Apply default timestamps on a freshly created raw log.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function applyTimestamps(array $validated): array
    {
        $validated['created_at'] = $validated['created_at'] ?? now();
        $validated['updated_at'] = $validated['updated_at'] ?? now();
        $validated['processed'] = $validated['processed'] ?? false;

        return $validated;
    }

    /**
     * Normalize a timestamp string to Y-m-d H:i:s format for consistent comparison.
     *
     * Handles various formats: ISO 8601, Y-m-d H:i:s, Y-m-d H:i:s.u, etc.
     */
    protected function normalizeTimestampForComparison(string $timestamp): string
    {
        $formats = [
            'Y-m-d\TH:i:s.u',
            'Y-m-d H:i:s',
            'Y-m-d H:i:s.u',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i',
            'Y-m-d H:i',
        ];

        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $timestamp);
            if ($dt !== false) {
                return $dt->format('Y-m-d H:i:s');
            }
        }

        $dt = new \DateTimeImmutable($timestamp);

        return $dt->format('Y-m-d H:i:s');
    }

    /**
     * Pre-load existing record keys to avoid N+1 queries during bulk import.
     *
     * Returns an associative array where keys are "device_user_id_device_id_punch_time".
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, true>
     */
    protected function getExistingKeys(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        // Extract all device_user_id + device_id combinations from the batch
        $combinations = [];
        foreach ($rows as $row) {
            $deviceUserId = $row['device_user_id'] ?? null;
            $deviceId = $row['device_id'] ?? null;
            if ($deviceUserId !== null && $deviceId !== null) {
                $combinations["{$deviceId}_{$deviceUserId}"] = true;
            }
        }

        if (empty($combinations)) {
            return [];
        }

        // Query all existing records for these device+user combinations
        $existingRecords = RawAttendanceLog::query()
            ->select('device_user_id', 'device_id', 'punch_time')
            ->where(function ($query) use ($combinations): void {
                foreach ($combinations as $key => $_) {
                    [$deviceId, $deviceUserId] = explode('_', $key, 2);
                    $query->orWhere(function ($q) use ($deviceId, $deviceUserId): void {
                        $q->where('device_id', $deviceId)
                            ->where('device_user_id', $deviceUserId);
                    });
                }
            })
            ->withTrashed()
            ->get();

        // Build lookup array
        $keys = [];
        foreach ($existingRecords as $record) {
            $punchTime = $record->punch_time instanceof \DateTimeInterface
                ? $record->punch_time->format('Y-m-d H:i:s')
                : (string) $record->punch_time;
            $normalized = $this->normalizeTimestampForComparison($punchTime);
            $key = "{$record->device_user_id}_{$record->device_id}_{$normalized}";
            $keys[$key] = true;
        }

        return $keys;
    }
}
