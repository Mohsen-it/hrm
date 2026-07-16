<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_hours_tracking', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('shift_category_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('period_type', ['daily', 'weekly', 'monthly']);
            $table->decimal('required_hours', 6, 2);
            $table->decimal('actual_hours', 6, 2)->default(0);
            $table->decimal('surplus_hours', 6, 2)->default(0);
            $table->decimal('deficit_hours', 6, 2)->default(0);
            $table->enum('status', ['on_track', 'deficit', 'surplus'])->default('on_track');
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('users');
            $table->foreign('shift_category_id')->references('id')->on('att_shift_categories');

            $table->unique(['employee_id', 'period_start', 'period_end', 'period_type'], 'att_hours_tracking_unique');
            $table->index('employee_id');
            $table->index(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_hours_tracking');
    }
};
