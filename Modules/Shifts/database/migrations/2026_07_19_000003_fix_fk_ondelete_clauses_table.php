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
        if (Schema::hasTable('att_category_time_schedule') && $this->hasForeignKey('att_category_time_schedule', 'att_category_time_schedule_shift_category_id_foreign')) {
            Schema::table('att_category_time_schedule', function (Blueprint $table) {
                $table->dropForeign(['shift_category_id']);
                $table->dropForeign(['time_schedule_id']);
                $table->foreign('shift_category_id')->references('id')->on('att_shift_categories')->cascadeOnDelete();
                $table->foreign('time_schedule_id')->references('id')->on('att_time_schedules')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('att_employee_shift_categories') && $this->hasForeignKey('att_employee_shift_categories', 'att_employee_shift_categories_employee_id_foreign')) {
            Schema::table('att_employee_shift_categories', function (Blueprint $table) {
                $table->dropForeign(['employee_id']);
                $table->dropForeign(['shift_category_id']);
                $table->foreign('employee_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('shift_category_id')->references('id')->on('att_shift_categories')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('att_hours_tracking') && $this->hasForeignKey('att_hours_tracking', 'att_hours_tracking_employee_id_foreign')) {
            Schema::table('att_hours_tracking', function (Blueprint $table) {
                $table->dropForeign(['employee_id']);
                $table->dropForeign(['shift_category_id']);
                $table->foreign('employee_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('shift_category_id')->references('id')->on('att_shift_categories')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('att_category_time_schedule', function (Blueprint $table) {
            $table->dropForeign(['shift_category_id']);
            $table->dropForeign(['time_schedule_id']);
            $table->foreign('shift_category_id')->references('id')->on('att_shift_categories');
            $table->foreign('time_schedule_id')->references('id')->on('att_time_schedules');
        });

        Schema::table('att_employee_shift_categories', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['shift_category_id']);
            $table->foreign('employee_id')->references('id')->on('users');
            $table->foreign('shift_category_id')->references('id')->on('att_shift_categories');
        });

        Schema::table('att_hours_tracking', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['shift_category_id']);
            $table->foreign('employee_id')->references('id')->on('users');
            $table->foreign('shift_category_id')->references('id')->on('att_shift_categories');
        });
    }

    /**
     * Check if a foreign key exists on a table.
     */
    protected function hasForeignKey(string $table, string $constraint): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        $table = $connection->getTablePrefix().$table;

        $count = $connection->selectOne(
            "SELECT COUNT(*) as count FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$database, $table, $constraint]
        );

        return $count && $count->count > 0;
    }
};
