<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_rotations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->date('anchor_start_date');
            $table->json('pattern')->comment('Array of 0/1 values representing the duty cycle, e.g. [1,1,1,0,0,0,0,0,0,0,0,0] for 3-on-9-off');
            $table->unsignedSmallInteger('cycle_length')->comment('Length of pattern array in days');
            $table->unsignedSmallInteger('work_days_count')->comment('Count of working days (1s) in pattern');
            $table->unsignedSmallInteger('rest_days_count')->comment('Count of rest days (0s) in pattern');
            $table->unsignedSmallInteger('number_of_groups')->default(1);
            $table->boolean('overtime_enabled')->default(false);
            $table->boolean('work_on_holidays')->default(false);
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->string('color', 7)->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index('company_id');
            $table->index('anchor_start_date');
            $table->unique(['name', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_rotations');
    }
};
