<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Complement indexed lookups with the last missing composite indexes.
 *
 * Covers real WHERE/ORDER BY clauses used by repositories:
 *  - FingerprintDeviceRepository: `status` + `last_pushed_at` (stale-device scan),
 *    `device_type_id` + `status` (filter combo). (`branch_id` + `status` is
 *    already indexed at table creation.)
 *  - UserVacationRequestRepository::getAll: `status` filter + `requested_at`
 *    ordering (`orderByDesc('requested_at')`).
 *
 * Additive only — no column, data, or signature change. Reversible via down().
 */
return new class extends Migration
{
    private const INDEXES = [
        'fingerprint_devices' => [
            'idx_devices_last_pushed_status' => ['last_pushed_at', 'status'],
            'idx_devices_type_status' => ['device_type_id', 'status'],
        ],
        'user_vacation_requests' => [
            'idx_vacation_req_status_requested' => ['status', 'requested_at'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                $missing = false;
                foreach ($columns as $col) {
                    if (! Schema::hasColumn($tableName, $col)) {
                        $missing = true;
                        break;
                    }
                }
                if ($missing) {
                    continue;
                }

                try {
                    Schema::table($tableName, function (Blueprint $table) use ($columns, $name): void {
                        $table->index($columns, $name);
                    });
                } catch (QueryException|PDOException $e) {
                    if (! $this->isDuplicateIndexException($e)) {
                        throw $e;
                    }
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach (array_keys($indexes) as $name) {
                try {
                    Schema::table($tableName, function (Blueprint $table) use ($name): void {
                        $table->dropIndex($name);
                    });
                } catch (QueryException|PDOException $e) {
                    if (! $this->isMissingIndexException($e)) {
                        throw $e;
                    }
                }
            }
        }
    }

    private function isDuplicateIndexException(Throwable $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, 'Duplicate key name')
            || str_contains($msg, '1061')
            || str_contains($msg, 'already exists')
            || str_contains($msg, 'index already exists');
    }

    private function isMissingIndexException(Throwable $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, "doesn't exist")
            || str_contains($msg, 'does not exist')
            || str_contains($msg, '1091')
            || str_contains($msg, "Can't DROP")
            || str_contains($msg, 'Cannot drop index')
            || str_contains($msg, '1553')
            || str_contains($msg, 'needed in a foreign key constraint');
    }
};
