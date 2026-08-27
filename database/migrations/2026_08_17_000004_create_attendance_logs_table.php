<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->string('employee_pin', 20)->index();
            $table->dateTime('punch_time')->index();
            $table->tinyInteger('punch_type')->comment('0=Check-in, 1=Check-out, 2=Break-out, 3=Break-in, etc.');
            $table->string('device_sn', 64)->index();
            $table->tinyInteger('verify_mode')->default(0)->comment('1=FP, 2=Face, 3=Card, 4=Password');
            $table->string('work_code', 20)->nullable();
            $table->string('source', 20)->default('ADMS')->comment('ADMS, SDK, Manual');
            $table->timestamps();

            // Deduplication: same person, same time, same device = duplicate
            $table->unique(['employee_pin', 'punch_time', 'device_sn'], 'ux_attendance_dedup');
            $table->index(['device_sn', 'punch_time']);
            $table->index(['employee_pin', 'punch_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
