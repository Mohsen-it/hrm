<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `holidays` stores both fixed (date-based) and recurring (month/day)
     * company / public holidays. The integration service reads from this
     * table to patch `daily_attendance_summaries.status = 'holiday'`.
     */
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 150);
            $table->string('name_en', 150)->nullable();
            $table->string('code', 50)->nullable();

            // A holiday is either a fixed date (date column) or a yearly
            // recurrence (month + day). The `is_recurring` flag makes the
            // semantics explicit.
            $table->boolean('is_recurring')->default(false);
            $table->date('date')->nullable();
            $table->unsignedTinyInteger('recurring_month')->nullable();
            $table->unsignedTinyInteger('recurring_day')->nullable();

            // Category — public, religious, national, company, weekend.
            $table->string('category', 30)->default('public');
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_active')->default(true);

            // The optional window around the holiday during which
            // attendance is automatically marked as `holiday`.
            $table->unsignedSmallInteger('duration_days')->default(1);
            $table->boolean('applies_to_all')->default(true);

            $table->json('applies_to_branches')->nullable();
            $table->json('applies_to_departments')->nullable();

            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'deleted_at'], 'holidays_active_deleted_idx');
            $table->index(['date'], 'holidays_date_idx');
            $table->index(['is_recurring', 'recurring_month', 'recurring_day'], 'holidays_recurring_idx');
            $table->index('category', 'holidays_category_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
