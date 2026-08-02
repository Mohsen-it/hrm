<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add covering indexes for the read-mostly company and branch lookups.
 *
 * The lookup services filter active records, exclude soft-deleted rows, and
 * sort by the display name. These composite indexes keep those select-box
 * queries indexed as the organizational structure grows.
 */
return new class extends Migration
{
    private const INDEXES = [
        'companies' => [
            'idx_companies_active_name' => ['status', 'deleted_at', 'company_name'],
        ],
        'branches' => [
            'idx_branches_active_name' => ['status', 'deleted_at', 'branch_name'],
            'idx_branches_company_name' => ['company_id', 'deleted_at', 'branch_name'],
        ],
    ];

    /**
     * Create the indexes without changing existing data.
     */
    public function up(): void
    {
        foreach (self::INDEXES as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                try {
                    Schema::table($tableName, function (Blueprint $table) use ($columns, $name): void {
                        $table->index($columns, $name);
                    });
                } catch (QueryException|PDOException $exception) {
                    if (! $this->isDuplicateIndexException($exception)) {
                        throw $exception;
                    }
                }
            }
        }
    }

    /**
     * Remove only the indexes added by this migration.
     */
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
                } catch (QueryException|PDOException $exception) {
                    if (! $this->isMissingIndexException($exception)) {
                        throw $exception;
                    }
                }
            }
        }
    }

    /**
     * Determine whether an index already exists on the target database.
     */
    private function isDuplicateIndexException(Throwable $exception): bool
    {
        return str_contains($exception->getMessage(), 'Duplicate key name')
            || str_contains($exception->getMessage(), 'already exists')
            || str_contains($exception->getMessage(), 'index already exists');
    }

    /**
     * Determine whether an index was already removed.
     */
    private function isMissingIndexException(Throwable $exception): bool
    {
        return str_contains($exception->getMessage(), "doesn't exist")
            || str_contains($exception->getMessage(), 'does not exist')
            || str_contains($exception->getMessage(), "Can't DROP");
    }
};
