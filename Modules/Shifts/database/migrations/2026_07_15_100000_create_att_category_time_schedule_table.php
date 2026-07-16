<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_category_time_schedule', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shift_category_id');
            $table->unsignedBigInteger('time_schedule_id');
            $table->timestamps();

            $table->foreign('shift_category_id')->references('id')->on('att_shift_categories');
            $table->foreign('time_schedule_id')->references('id')->on('att_time_schedules');

            $table->unique('shift_category_id');
            $table->index('time_schedule_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_category_time_schedule');
    }
};
