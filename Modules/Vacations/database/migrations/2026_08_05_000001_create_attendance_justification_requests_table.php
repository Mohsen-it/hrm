<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create auditable employee attendance-justification requests. */
    public function up(): void
    {
        Schema::create('attendance_justification_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->time('arrival_time')->nullable();
            $table->boolean('missing_check_in')->default(false);
            $table->boolean('missing_check_out')->default(false);
            $table->boolean('late_arrival')->default(false);
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->time('expected_check_in')->nullable();
            $table->time('check_in_deadline')->nullable();
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->foreignId('rotation_id')->nullable()->constrained('att_rotations')->nullOnDelete();
            $table->foreignId('rotation_group_id')->nullable()->constrained('att_rotation_groups')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('schedule_snapshot')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'attendance_date'], 'att_justification_user_date');
            $table->index(['attendance_date', 'late_arrival'], 'att_justification_date_late');
        });
    }

    /** Drop the isolated justification table. */
    public function down(): void
    {
        Schema::dropIfExists('attendance_justification_requests');
    }
};
