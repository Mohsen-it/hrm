<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T006 — Add performance indexes to attendance, raw logs, and iclock tables.
 *
 * These serve the busiest tables in the system:
 *  - attendance_sessions: user+date lookups, status filters, check-out scans
 *  - daily_attendance_summaries: date+calculated_at batch updates
 *  - raw_attendance_logs: dedup, device-user scans, processed filters
 *  - iclock_transaction: biometric punch scans
 *
 * All additions are additive only — no column or data change.
 */
return new class extends Migration
{
    public function up(): void
    {
        // attendance_sessions — 4 indexes
        $attIndexes = [
            'idx_att_sessions_user_date_status' => ['user_id', 'attendance_date', 'status'],
            'idx_att_sessions_date_status_type' => ['attendance_date', 'status', 'session_type'],
            'idx_att_sessions_created_by' => ['created_by', 'attendance_date'],
            'idx_att_sessions_checkout' => ['check_out_at', 'status'],
        ];

        foreach ($attIndexes as $name => $cols) {
            try {
                Schema::table('attendance_sessions', function (Blueprint $table) use ($cols, $name): void {
                    $table->index($cols, $name);
                });
            } catch (QueryException|PDOException $e) {
                $this->handleDuplicateKey($e);
            }
        }

        // daily_attendance_summaries — 2 indexes
        $summIndexes = [
            'idx_daily_summaries_date_calculated' => ['summary_date', 'calculated_at'],
            'idx_daily_summaries_status_date' => ['status', 'summary_date'],
        ];

        foreach ($summIndexes as $name => $cols) {
            try {
                Schema::table('daily_attendance_summaries', function (Blueprint $table) use ($cols, $name): void {
                    $table->index($cols, $name);
                });
            } catch (QueryException|PDOException $e) {
                $this->handleDuplicateKey($e);
            }
        }

        // raw_attendance_logs — 3 indexes
        $rawIndexes = [
            'idx_raw_logs_dedup' => ['device_id', 'device_user_id', 'punch_time'],
            'idx_raw_logs_user_time' => ['device_user_id', 'punch_time'],
            'idx_raw_logs_processed_punch' => ['processed', 'punch_time'],
        ];

        foreach ($rawIndexes as $name => $cols) {
            try {
                Schema::table('raw_attendance_logs', function (Blueprint $table) use ($cols, $name): void {
                    $table->index($cols, $name);
                });
            } catch (QueryException|PDOException $e) {
                $this->handleDuplicateKey($e);
            }
        }

        // iclock_transaction — 1 index (guarded)
        if (Schema::hasTable('iclock_transaction')) {
            try {
                Schema::table('iclock_transaction', function (Blueprint $table): void {
                    $table->index(['punch_time'], 'idx_iclock_punch_time');
                });
            } catch (QueryException|PDOException $e) {
                $this->handleDuplicateKey($e);
            }
        }
    }

    public function down(): void
    {
        $attIndexes = [
            'idx_att_sessions_user_date_status',
            'idx_att_sessions_date_status_type',
            'idx_att_sessions_created_by',
            'idx_att_sessions_checkout',
        ];

        foreach ($attIndexes as $name) {
            try {
                Schema::table('attendance_sessions', function (Blueprint $table) use ($name): void {
                    $table->dropIndex($name);
                });
            } catch (QueryException|PDOException $e) {
                $this->handleMissingIndex($e);
            }
        }

        $summIndexes = [
            'idx_daily_summaries_date_calculated',
            'idx_daily_summaries_status_date',
        ];

        foreach ($summIndexes as $name) {
            try {
                Schema::table('daily_attendance_summaries', function (Blueprint $table) use ($name): void {
                    $table->dropIndex($name);
                });
            } catch (QueryException|PDOException $e) {
                $this->handleMissingIndex($e);
            }
        }

        $rawIndexes = [
            'idx_raw_logs_dedup',
            'idx_raw_logs_user_time',
            'idx_raw_logs_processed_punch',
        ];

        foreach ($rawIndexes as $name) {
            try {
                Schema::table('raw_attendance_logs', function (Blueprint $table) use ($name): void {
                    $table->dropIndex($name);
                });
            } catch (QueryException|PDOException $e) {
                $this->handleMissingIndex($e);
            }
        }

        if (Schema::hasTable('iclock_transaction')) {
            try {
                Schema::table('iclock_transaction', function (Blueprint $table): void {
                    $table->dropIndex('idx_iclock_punch_time');
                });
            } catch (QueryException|PDOException $e) {
                $this->handleMissingIndex($e);
            }
        }
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

    protected function handleMissingIndex(Throwable $e): void
    {
        $msg = $e->getMessage();

        if (str_contains($msg, "doesn't exist")
            || str_contains($msg, '1091')
            || str_contains($msg, 'does not exist')
            || str_contains($msg, 'Can\'t drop')
            || str_contains($msg, 'Cannot drop index')
            || str_contains($msg, '1553')
            || str_contains($msg, 'needed in a foreign key constraint')) {
            return;
        }

        throw $e;
    }
};
