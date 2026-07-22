<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T013 — Add performance indexes to fingerprint devices and sync logs.
 *
 *  - fingerprint_devices: company+branch+status lookup, last_pushed_at scan
 *  - device_sync_logs: device+date, status+date
 */
return new class extends Migration
{
    public function up(): void
    {
        $deviceIndexes = [
            'idx_devices_company_branch' => ['default_company_id', 'default_branch_id', 'status'],
        ];

        foreach ($deviceIndexes as $name => $cols) {
            try {
                Schema::table('fingerprint_devices', function (Blueprint $table) use ($cols, $name): void {
                    $table->index($cols, $name);
                });
            } catch (QueryException|PDOException $e) {
                $this->handleDuplicateKey($e);
            }
        }

        if (Schema::hasTable('device_sync_logs')) {
            $syncIndexes = [
                'idx_sync_logs_device_date' => ['device_id', 'started_at'],
                'idx_sync_logs_status_date' => ['status', 'started_at'],
            ];

            foreach ($syncIndexes as $name => $cols) {
                try {
                    Schema::table('device_sync_logs', function (Blueprint $table) use ($cols, $name): void {
                        $table->index($cols, $name);
                    });
                } catch (QueryException|PDOException $e) {
                    $this->handleDuplicateKey($e);
                }
            }
        }
    }

    public function down(): void
    {
        $deviceIndexes = [
            'idx_devices_company_branch',
        ];

        foreach ($deviceIndexes as $name) {
            try {
                Schema::table('fingerprint_devices', function (Blueprint $table) use ($name): void {
                    $table->dropIndex($name);
                });
            } catch (QueryException|PDOException $e) {
                $this->handleMissingIndex($e);
            }
        }

        if (Schema::hasTable('device_sync_logs')) {
            $syncIndexes = [
                'idx_sync_logs_device_date',
                'idx_sync_logs_status_date',
            ];

            foreach ($syncIndexes as $name) {
                try {
                    Schema::table('device_sync_logs', function (Blueprint $table) use ($name): void {
                        $table->dropIndex($name);
                    });
                } catch (QueryException|PDOException $e) {
                    $this->handleMissingIndex($e);
                }
            }
        }
    }

    protected function handleDuplicateKey(Throwable $e): void
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'Duplicate key name') || str_contains($msg, '1061')
            || str_contains($msg, 'already exists') || str_contains($msg, 'index already exists')) {
            return;
        }
        throw $e;
    }

    protected function handleMissingIndex(Throwable $e): void
    {
        $msg = $e->getMessage();
        if (str_contains($msg, "doesn't exist") || str_contains($msg, '1091')
            || str_contains($msg, 'does not exist') || str_contains($msg, 'Can\'t drop')
            || str_contains($msg, 'Cannot drop index') || str_contains($msg, '1553')
            || str_contains($msg, 'needed in a foreign key constraint')) {
            return;
        }
        throw $e;
    }
};
