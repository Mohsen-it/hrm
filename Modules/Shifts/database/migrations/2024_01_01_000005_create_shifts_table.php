<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('shift_code', 50);
            $table->string('shift_name');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->decimal('working_hours', 5, 2)->default(0);
            $table->json('work_days')->nullable();
            $table->text('description')->nullable();
            $table->smallInteger('status')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'shift_code']);
            $table->index(['company_id', 'branch_id', 'status', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
