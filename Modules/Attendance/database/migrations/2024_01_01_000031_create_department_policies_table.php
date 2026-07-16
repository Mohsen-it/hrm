<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_departmentpolicy', function (Blueprint $table) {
            $table->id();
            $table->timestamp('create_time')->nullable();
            $table->string('create_user', 150)->nullable();
            $table->timestamp('change_time')->nullable();
            $table->string('change_user', 150)->nullable();
            $table->smallInteger('status')->default(1);
            $table->smallInteger('use_ot')->default(0);
            $table->smallInteger('weekend1')->default(6);
            $table->smallInteger('weekend2')->default(0);
            $table->smallInteger('start_of_week')->default(0);
            $table->decimal('max_hrs', 4, 1)->default(8);
            $table->time('day_change');
            $table->smallInteger('paring_rule')->default(0);
            $table->smallInteger('punch_period')->default(0);
            $table->boolean('daily_ot')->default(false);
            $table->uuid('daily_ot_rule')->nullable();
            $table->boolean('weekly_ot')->default(false);
            $table->uuid('weekly_ot_rule')->nullable();
            $table->boolean('weekend_ot')->default(false);
            $table->uuid('weekend_ot_rule')->nullable();
            $table->boolean('holiday_ot')->default(false);
            $table->uuid('holiday_ot_rule')->nullable();
            $table->integer('late_in2absence')->default(0);
            $table->integer('early_out2absence')->default(0);
            $table->smallInteger('miss_in')->default(0);
            $table->integer('late_in_hrs')->default(0);
            $table->smallInteger('miss_out')->default(0);
            $table->integer('early_out_hrs')->default(0);
            $table->boolean('require_capture')->default(false);
            $table->boolean('require_work_code')->default(false);
            $table->boolean('require_punch_state')->default(false);
            $table->foreignId('department_id')->constrained('departments');
            $table->time('email_send_time');
            $table->smallInteger('group_frequency')->default(0);
            $table->smallInteger('group_send_day')->default(0);
            $table->integer('max_absent')->default(0);
            $table->integer('max_early_out')->default(0);
            $table->integer('max_late_in')->default(0);
            $table->smallInteger('sending_day')->default(0);
            $table->string('weekend1_color_setting', 30)->nullable();
            $table->string('weekend2_color_setting', 30)->nullable();
            $table->unsignedBigInteger('ot_pay_code_id')->nullable();
            $table->smallInteger('overtime_policy')->default(0);
            $table->boolean('enable_compensatory')->default(false);
            $table->string('bot_uid', 100)->nullable();
            $table->boolean('enable_workcode_calculation')->default(false);
            $table->smallInteger('enable_workcode_punch_state')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('ot_pay_code_id')->references('id')->on('att_paycode')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_departmentpolicy');
    }
};
