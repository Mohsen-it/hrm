<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_integration_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 50);
            $table->string('correlation_id', 64)->nullable()->index();
            $table->unsignedBigInteger('device_id')->nullable()->index();
            $table->string('device_serial', 100)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('device_user_id', 100)->nullable();
            $table->string('status', 30);
            $table->json('context')->nullable();
            $table->json('payload_snapshot')->nullable();
            $table->decimal('duration_ms', 10, 2)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['action', 'occurred_at']);
            $table->index(['device_id', 'occurred_at']);
            $table->index(['status', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_integration_audit_logs');
    }
};
