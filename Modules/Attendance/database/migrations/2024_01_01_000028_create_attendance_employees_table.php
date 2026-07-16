<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_attemployee', function (Blueprint $table) {
            $table->id();
            $table->timestamp('create_time')->nullable();
            $table->string('create_user', 150)->nullable();
            $table->timestamp('change_time')->nullable();
            $table->string('change_user', 150)->nullable();
            $table->smallInteger('status')->default(1);
            $table->boolean('enable_attendance')->default(true);
            $table->boolean('enable_schedule')->default(true);
            $table->boolean('enable_overtime')->default(false);
            $table->boolean('enable_holiday')->default(true);
            $table->boolean('enable_compensatory')->default(false);
            $table->foreignId('emp_id')->unique()->constrained('users');
            $table->unsignedBigInteger('group_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('group_id')->references('id')->on('att_attgroup')->nullOnDelete();
            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_attemployee');
    }
};
