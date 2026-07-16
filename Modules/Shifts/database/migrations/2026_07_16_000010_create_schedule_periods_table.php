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
        Schema::create('schedule_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('year');
            $table->unsignedInteger('month');
            $table->date('schedule_period_start');
            $table->date('schedule_period_end');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->foreignId('generated_by')->constrained('users');
            $table->timestamp('generated_at');
            $table->foreignId('published_by')->nullable()->constrained('users');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('schedule_version')->default(1);
            $table->timestamps();

            $table->unique(['year', 'month', 'schedule_version']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_periods');
    }
};
