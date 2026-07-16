<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The table that records the Gregorian start and end of every
     * Ramadan the system has operated through. Attendance calculations
     * use the bounds to switch to the reduced-hours policy in effect
     * for the holy month.
     */
    public function up(): void
    {
        Schema::create('ramadan_dates', function (Blueprint $table) {
            $table->id();
            $table->year('year')->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('daily_working_hours')->default(6);
            $table->time('default_start_time')->default('09:00:00');
            $table->time('default_end_time')->default('15:00:00');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['start_date', 'end_date'], 'ramadan_dates_range_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ramadan_dates');
    }
};
