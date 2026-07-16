<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('grade_code', 50);
            $table->string('grade_name');
            $table->unsignedTinyInteger('level')->default(1);
            $table->decimal('min_salary', 12, 2)->nullable();
            $table->decimal('max_salary', 12, 2)->nullable();
            $table->text('description')->nullable();
            $table->smallInteger('status')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'grade_code']);
            $table->index(['company_id', 'level', 'status', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
