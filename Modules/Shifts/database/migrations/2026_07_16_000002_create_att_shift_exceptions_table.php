<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 1 — `Shift_Exceptions` table (leaves / missions / swaps).
 *
 * This is the ISOLATED interceptor source consumed by the ScheduleResolver
 * in strict fail-fast order. A row here short-circuits the absence/penalty
 * calculation so an employee on an approved leave/mission (or a shift swap)
 * is never flagged absent.
 *
 * The Vacations module remains the system of record for balances; a
 * Shifts listener mirrors approved vacation requests into this table so the
 * resolver has a single, denormalised lookup (no coupling to Vacations at
 * query time).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_shift_exceptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('employee_id');
            $table->enum('exception_type', ['leave', 'mission', 'swap', 'training', 'other'])
                ->default('leave');
            $table->enum('source', ['vacation', 'manual', 'swap'])->default('manual');
            $table->unsignedBigInteger('source_id')->nullable()
                ->comment('Original row id (e.g. user_vacation_requests.id) for traceability.');
            $table->date('from_date');
            $table->date('to_date');
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->text('reason')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            // Core interceptor lookup: employee + overlapping date range.
            $table->index(['employee_id', 'from_date', 'to_date'], 'att_shift_exceptions_emp_range_idx');
            // Fast active-filter scans during sync.
            $table->index(['status', 'from_date', 'to_date'], 'att_shift_exceptions_status_range_idx');
            // Traceability back to the originating system.
            $table->index(['source', 'source_id'], 'att_shift_exceptions_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_shift_exceptions');
    }
};
