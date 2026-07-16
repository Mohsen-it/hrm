<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('department_code', 50);
            $table->string('department_name');
            $table->text('description')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('location')->nullable();
            $table->smallInteger('status')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'department_code'], 'departments_branch_code_unique');
            $table->index(
                ['company_id', 'branch_id', 'status', 'deleted_at'],
                'departments_company_branch_status_idx'
            );
            $table->index('parent_id', 'departments_parent_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
