<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `raw_attendance_logs` captures every punchonomic event coming from a
     * fingerprint device (or other ingestion path) before it has been
     * correlated into an `attendance_session`. The `processed` flag and
     * `processed_at` timestamp allow incremental reconciliation jobs to skip
     * rows that have already been turned into sessions.
     */
    public function up(): void
    {
        Schema::create('raw_attendance_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('device_id')->nullable();

            // Raw employee / device-side identifier (may differ from our user_id
            // before reconciliation — kept for traceability).
            $table->string('device_user_id', 100)->nullable();

            $table->dateTime('punch_time');
            $table->string('punch_type', 20)->default('unknown'); // check_in / check_out / unknown
            $table->string('verify_type', 20)->default('fingerprint'); // fingerprint / card / password / face
            $table->unsignedSmallInteger('work_code')->default(0);
            $table->string('source', 20)->default('device'); // device / adms / manual / api

            $table->boolean('processed')->default(false);
            $table->dateTime('processed_at')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->json('raw_data')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'punch_time']);
            $table->index(['device_id', 'punch_time']);
            $table->index(['processed', 'punch_time']);
            $table->index('punch_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_attendance_logs');
    }
};
