<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_timeinterval_break_time', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timeinterval_id')->constrained('att_timeinterval')->cascadeOnDelete();
            $table->foreignId('breaktime_id')->constrained('att_breaktime')->cascadeOnDelete();
            $table->unique(['timeinterval_id', 'breaktime_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_timeinterval_break_time');
    }
};
