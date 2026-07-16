<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_employee_shift_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('shift_category_id');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->json('snapshot_data');
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('users');
            $table->foreign('shift_category_id')->references('id')->on('att_shift_categories');

            $table->index('employee_id');
            $table->index('shift_category_id');
            $table->index(['start_date', 'end_date']);
            $table->index(['employee_id', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_employee_shift_categories');
    }
};
