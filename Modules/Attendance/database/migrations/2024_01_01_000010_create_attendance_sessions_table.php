<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `attendance_sessions` stores a single check-in / check-out pair for an
     * employee on a given date. A user may have multiple sessions on the same
     * day (e.g. split shift, make-up time). Each session references the shift
     * that scheduled the work, the punch source, and (optionally) the device
     * and raw log that produced it.
     */
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->unsignedBigInteger('device_id')->nullable();
            $table->unsignedBigInteger('raw_log_id')->nullable();
            $table->unsignedBigInteger('zone_id')->nullable();

            $table->date('attendance_date');
            $table->dateTime('check_in_at');
            $table->dateTime('check_out_at')->nullable();

            $table->time('expected_check_in')->nullable();
            $table->time('expected_check_out')->nullable();

            $table->string('status', 30)->default('present');
            $table->string('session_type', 20)->default('normal');
            $table->string('source', 20)->default('device');

            $table->unsignedInteger('work_minutes')->default(0);
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_leave_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);

            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'attendance_date'], 'att_sessions_user_date_idx');
            $table->index(['attendance_date', 'status'], 'att_sessions_date_status_idx');
            $table->index(['user_id', 'status', 'attendance_date'], 'att_sessions_user_status_date_idx');
            $table->index(['shift_id', 'attendance_date'], 'att_sessions_shift_date_idx');
            $table->index('device_id', 'att_sessions_device_id_idx');
            $table->index('raw_log_id', 'att_sessions_raw_log_id_idx');
            $table->index('zone_id', 'att_sessions_zone_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
