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
        Schema::table('att_departmentpolicy', function (Blueprint $table) {
            $table->index('department_id', 'att_dept_policy_dept_idx');
        });

        Schema::table('att_departmentschedule', function (Blueprint $table) {
            $table->index('department_id', 'att_dept_sched_dept_idx');
            $table->index(['department_id', 'start_date', 'end_date'], 'att_dept_sched_range_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('att_departmentpolicy', function (Blueprint $table) {
            $table->dropIndex('att_dept_policy_dept_idx');
        });

        Schema::table('att_departmentschedule', function (Blueprint $table) {
            $table->dropIndex('att_dept_sched_dept_idx');
            $table->dropIndex('att_dept_sched_range_idx');
        });
    }
};
