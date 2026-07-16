<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the central HR columns to the existing users table.
     * The default users table is created by Laravel's first migration;
     * this migration only augments it for the HRM domain.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Employee personal info
            $table->string('employee_code', 50)->nullable()->after('id');
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('full_name_ar')->nullable()->after('last_name');
            $table->string('full_name_en')->nullable()->after('full_name_ar');
            $table->string('national_id', 30)->nullable()->after('full_name_en');
            $table->string('phone', 20)->nullable();
            $table->string('phone2', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable();
            $table->string('nationality', 50)->nullable();

            // Employment info
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'temporary', 'intern'])->default('full_time');
            $table->string('job_title', 100)->nullable();
            $table->string('work_location')->nullable();

            // Address
            $table->string('address')->nullable();
            $table->string('city', 50)->nullable();
            $table->string('state', 50)->nullable();
            $table->string('country', 50)->nullable();
            $table->string('postal_code', 20)->nullable();

            // Emergency contact
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_relation', 50)->nullable();

            // Banking
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('iban', 50)->nullable();

            // Avatar
            $table->string('avatar', 200)->nullable();

            // Status & security
            $table->smallInteger('status')->default(1);
            $table->boolean('is_active_employee')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();

            // Foreign keys (organizational structure)
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();

            // Soft deletes
            $table->softDeletes();

            // Indexes
            $table->index(['status', 'deleted_at']);
            $table->index(['company_id', 'branch_id', 'status', 'deleted_at']);
            $table->index(['department_id', 'status', 'deleted_at']);
            $table->index('employee_code');
            $table->index('national_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['department_id']);
            $table->dropForeign(['position_id']);
            $table->dropForeign(['grade_id']);
            $table->dropForeign(['shift_id']);
            $table->dropForeign(['manager_id']);

            $table->dropIndex(['status', 'deleted_at']);
            $table->dropIndex(['company_id', 'branch_id', 'status', 'deleted_at']);
            $table->dropIndex(['department_id', 'status', 'deleted_at']);
            $table->dropIndex(['employee_code']);
            $table->dropIndex(['national_id']);

            $table->dropColumn([
                'employee_code', 'first_name', 'last_name', 'full_name_ar', 'full_name_en',
                'national_id', 'phone', 'phone2', 'date_of_birth', 'gender', 'marital_status',
                'nationality', 'hire_date', 'termination_date', 'employment_type', 'job_title',
                'work_location', 'address', 'city', 'state', 'country', 'postal_code',
                'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
                'bank_name', 'bank_account_number', 'iban', 'avatar', 'status', 'is_active_employee',
                'last_login_at', 'last_login_ip', 'must_change_password', 'failed_login_attempts',
                'locked_until', 'company_id', 'branch_id', 'department_id', 'position_id',
                'grade_id', 'shift_id', 'manager_id', 'deleted_at',
            ]);
        });
    }
};
