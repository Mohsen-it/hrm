<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerprint_device_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('manufacturer', 100)->default('ZKTeco');
            $table->string('protocol', 30)->default('ADMS');
            $table->string('sdk_version', 30)->nullable();
            $table->integer('default_port')->default(4370);
            $table->boolean('supports_fingerprint')->default(true);
            $table->boolean('supports_face')->default(false);
            $table->integer('max_fingerprints')->default(3000);
            $table->integer('max_users')->default(10000);
            $table->text('connection_params')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['manufacturer', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fingerprint_device_types');
    }
};
