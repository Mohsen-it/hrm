<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('position_code', 50);
            $table->string('position_name');
            $table->text('description')->nullable();
            $table->decimal('min_salary', 12, 2)->nullable();
            $table->decimal('max_salary', 12, 2)->nullable();
            $table->text('requirements')->nullable();
            $table->smallInteger('status')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['department_id', 'position_code'], 'positions_dept_code_unique');
            $table->index(
                ['company_id', 'branch_id', 'department_id', 'status', 'deleted_at'],
                'positions_company_branch_dept_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
