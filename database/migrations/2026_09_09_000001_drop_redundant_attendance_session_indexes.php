<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 011/P1-A — Drop two strictly-redundant indexes on attendance_sessions.
 *
 * Redundancy proof (left-prefix rule — the longer index serves every query
 * the shorter one serves):
 *  - att_sessions_user_date_idx(user_id, attendance_date)
 *    is a left prefix of idx_att_sessions_user_date_status(user_id, attendance_date, status)
 *  - att_sessions_date_status_idx(attendance_date, status)
 *    is a left prefix of idx_att_sessions_date_status_type(attendance_date, status, session_type)
 *
 * Safety:
 *  - Each drop is guarded: the covering index must exist first, otherwise the
 *    drop is skipped so the query plan can never degrade.
 *  - Missing-index errors are ignored (idempotent re-runs).
 *  - down() restores both indexes (duplicate errors ignored).
 *  - No column or data change — index metadata only.
 */
return new class extends Migration
{
    /**
     * @var array<string, array{drop: string, covering: string, columns: array<int, string>}>
     */
    protected array $redundant = [
        'att_sessions_user_date_idx' => [
            'drop' => 'att_sessions_user_date_idx',
            'covering' => 'idx_att_sessions_user_date_status',
            'columns' => ['user_id', 'attendance_date'],
        ],
        'att_sessions_date_status_idx' => [
            'drop' => 'att_sessions_date_status_idx',
            'covering' => 'idx_att_sessions_date_status_type',
            'columns' => ['attendance_date', 'status'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->redundant as $spec) {
            if (! $this->indexExists('attendance_sessions', $spec['covering'])) {
                continue;
            }

            try {
                Schema::table('attendance_sessions', function (Blueprint $table) use ($spec): void {
                    $table->dropIndex($spec['drop']);
                });
            } catch (QueryException|PDOException $e) {
                $this->handleMissingIndex($e);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->redundant as $spec) {
            try {
                Schema::table('attendance_sessions', function (Blueprint $table) use ($spec): void {
                    $table->index($spec['columns'], $spec['drop']);
                });
            } catch (QueryException|PDOException $e) {
                $this->handleDuplicateKey($e);
            }
        }
    }

    protected function indexExists(string $table, string $name): bool
    {
        try {
            return collect(Schema::getIndexes($table))
                ->pluck('name')
                ->contains($name);
        } catch (Throwable) {
            return false;
        }
    }

    protected function handleMissingIndex(Throwable $e): void
    {
        $msg = $e->getMessage();

        if (str_contains($msg, "doesn't exist")
            || str_contains($msg, '1091')
            || str_contains($msg, 'does not exist')
            || str_contains($msg, 'Can\'t drop')
            || str_contains($msg, 'Cannot drop index')) {
            return;
        }

        throw $e;
    }

    protected function handleDuplicateKey(Throwable $e): void
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'Duplicate key name')
            || str_contains($msg, '1061')
            || str_contains($msg, 'already exists')
            || str_contains($msg, 'index already exists')) {
            return;
        }

        throw $e;
    }
};
