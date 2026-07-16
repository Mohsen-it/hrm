<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_temporaryschedule', function (Blueprint $table) {
            $table->id();
            $table->timestamp('create_time')->nullable();
            $table->string('create_user', 150)->nullable();
            $table->timestamp('change_time')->nullable();
            $table->string('change_user', 150)->nullable();
            $table->smallInteger('status')->default(1);
            $table->date('att_date');
            $table->foreignId('employee_id')->constrained('users');
            $table->unsignedBigInteger('time_interval_id')->nullable();
            $table->timestamps();

            $table->foreign('time_interval_id')->references('id')->on('att_timeinterval')->nullOnDelete();
            $table->index(['employee_id', 'att_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_temporaryschedule');
    }
};
