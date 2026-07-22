<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T009 — Add performance indexes to vacation tables.
 *
 *  - user_vacation_requests: pending-requests lookup, user date range, decided_at filter
 *  - user_vacation_balance_transactions: user+type date scan
 */
return new class extends Migration
{
    public function up(): void
    {
        $vacIndexes = [
            'idx_vacation_req_status_start' => ['status', 'start_date', 'end_date'],
            'idx_vacation_req_user_dates' => ['user_id', 'start_date'],
            'idx_vacation_req_decided' => ['decided_at', 'status'],
        ];

        foreach ($vacIndexes as $name => $cols) {
            try {
                Schema::table('user_vacation_requests', function (Blueprint $table) use ($cols, $name): void {
                    $table->index($cols, $name);
                });
            } catch (QueryException|PDOException $e) {
                $this->handleDuplicateKey($e);
            }
        }

        try {
            Schema::table('user_vacation_balance_transactions', function (Blueprint $table): void {
                $table->index(['user_id', 'vacation_type_id', 'created_at'], 'idx_vacation_bal_tx_date');
            });
        } catch (QueryException|PDOException $e) {
            $this->handleDuplicateKey($e);
        }
    }

    public function down(): void
    {
        $vacIndexes = [
            'idx_vacation_req_status_start',
            'idx_vacation_req_user_dates',
            'idx_vacation_req_decided',
        ];

        foreach ($vacIndexes as $name) {
            try {
                Schema::table('user_vacation_requests', function (Blueprint $table) use ($name): void {
                    $table->dropIndex($name);
                });
            } catch (QueryException|PDOException $e) {
                $this->handleMissingIndex($e);
            }
        }

        try {
            Schema::table('user_vacation_balance_transactions', function (Blueprint $table): void {
                $table->dropIndex('idx_vacation_bal_tx_date');
            });
        } catch (QueryException|PDOException $e) {
            $this->handleMissingIndex($e);
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
