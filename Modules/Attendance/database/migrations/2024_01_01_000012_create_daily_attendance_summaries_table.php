<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `daily_attendance_summaries` stores exactly one roll-up row per
     * employee per calendar date, aggregating the totals from every
     * `attendance_session` of that day. The (user_id, summary_date) pair is
     * enforced unique so the auto-calculation service can use `updateOrInsert`
     * without worrying about duplicates.
     */
    public function up(): void
    {
        Schema::create('daily_attendance_summaries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->date('summary_date');

            $table->string('status', 30)->default('present');
            $table->string('session_type', 20)->default('normal');

            $table->dateTime('first_check_in_at')->nullable();
            $table->dateTime('last_check_out_at')->nullable();
            $table->time('expected_check_in')->nullable();
            $table->time('expected_check_out')->nullable();

            $table->unsignedSmallInteger('sessions_count')->default(0);
            $table->boolean('is_first_punch')->default(false);
            $table->boolean('is_complete')->default(false);

            $table->unsignedInteger('total_work_minutes')->default(0);
            $table->unsignedInteger('total_break_minutes')->default(0);
            $table->unsignedInteger('total_overtime_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_leave_minutes')->default(0);

            $table->text('notes')->nullable();
            $table->dateTime('calculated_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'summary_date'], 'daily_summary_user_date_unique');
            $table->index(['summary_date', 'status'], 'daily_summaries_date_status_idx');
            $table->index(['user_id', 'status', 'summary_date'], 'daily_summaries_user_status_date_idx');
            $table->index(['shift_id', 'summary_date'], 'daily_summaries_shift_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_attendance_summaries');
    }
};
