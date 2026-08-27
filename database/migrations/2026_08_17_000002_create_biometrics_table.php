<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometrics', function (Blueprint $table) {
            $table->id();
            $table->string('employee_pin', 20)->index();
            $table->tinyInteger('bio_type')->comment('1=Fingerprint, 9=Face'); // ZKTeco: 1=FP, 9=Face (or 2=Face in some firmwares)
            $table->smallInteger('major_ver')->default(0);
            $table->smallInteger('minor_ver')->default(0);
            $table->longText('template_data')->comment('Base64 encoded template, stored exactly as received');
            $table->char('template_hash', 64)->comment('SHA256 of template_data for deduplication');
            $table->string('device_sn', 64)->nullable()->index()->comment('Source device serial number');
            $table->smallInteger('finger_index')->nullable()->comment('Finger/face index (0-9 for face templates)');
            $table->tinyInteger('valid')->default(1);
            $table->tinyInteger('format')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Composite index for idempotency: prevent duplicate templates
            $table->unique(['employee_pin', 'bio_type', 'major_ver', 'minor_ver', 'template_hash'], 'ux_biometric_dedup');
            $table->index(['employee_pin', 'bio_type']);
            $table->index(['device_sn', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometrics');
    }
};
