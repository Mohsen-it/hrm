<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T005 — Add composite and single-column indexes to `users`.
 *
 * These indexes serve the most-called queries in the system:
 *  - UserRepository::getAll (company_id, branch_id, department_id filters)
 *  - active() scope (status + is_active_employee)
 *  - Employment-type reports and hire-date sorting.
 *
 * All additions are additive only — no column or data change.
 */
return new class extends Migration
{
    public function up(): void
    {
        $indexes = [
            ['company_id', 'status', 'is_active_employee'],
            ['branch_id', 'status'],
            ['department_id', 'status'],
            ['position_id', 'status'],
            ['grade_id', 'status'],
            ['employment_type'],
            ['hire_date'],
        ];

        $names = [
            'idx_users_company_status_active',
            'idx_users_branch_status',
            'idx_users_department_status',
            'idx_users_position_status',
            'idx_users_grade_status',
            'idx_users_employment_type',
            'idx_users_hire_date',
        ];

        foreach ($names as $i => $name) {
            try {
                Schema::table('users', function (Blueprint $table) use ($indexes, $i, $name): void {
                    $table->index($indexes[$i], $name);
                });
            } catch (QueryException|PDOException $e) {
                $this->handleDuplicateKey($e);
            }
        }
    }

    public function down(): void
    {
        $names = [
            'idx_users_company_status_active',
            'idx_users_branch_status',
            'idx_users_department_status',
            'idx_users_position_status',
            'idx_users_grade_status',
            'idx_users_employment_type',
            'idx_users_hire_date',
        ];

        foreach ($names as $name) {
            try {
                Schema::table('users', function (Blueprint $table) use ($name): void {
                    $table->dropIndex($name);
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
