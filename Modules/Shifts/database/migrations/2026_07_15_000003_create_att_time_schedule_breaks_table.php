<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_time_schedule_breaks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_id');
            $table->time('break_start');
            $table->unsignedSmallInteger('duration');
            $table->time('break_end');
            $table->timestamps();

            $table->foreign('schedule_id')->references('id')->on('att_time_schedules')->onDelete('cascade');
            $table->index('schedule_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_time_schedule_breaks');
    }
};
