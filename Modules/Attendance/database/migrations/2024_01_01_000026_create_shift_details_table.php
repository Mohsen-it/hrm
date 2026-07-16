<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_shiftdetail', function (Blueprint $table) {
            $table->id();
            $table->time('in_time');
            $table->time('out_time');
            $table->integer('day_index');
            $table->foreignId('shift_id')->constrained('att_attshift')->cascadeOnDelete();
            $table->foreignId('time_interval_id')->constrained('att_timeinterval')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['shift_id', 'day_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_shiftdetail');
    }
};
