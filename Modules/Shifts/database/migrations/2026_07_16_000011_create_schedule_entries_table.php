<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedule_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_period_id')->constrained('schedule_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('duty_category_id')->nullable()->constrained('att_shift_categories')->nullOnDelete();
            $table->date('date');
            $table->enum('day_status', ['WORK', 'REST']);
            $table->timestamps();

            $table->index(['schedule_period_id', 'employee_id']);
            $table->index(['employee_id', 'date']);
            $table->index(['duty_category_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_entries');
    }
};
