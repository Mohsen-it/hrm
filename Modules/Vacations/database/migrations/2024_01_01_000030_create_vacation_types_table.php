<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `vacation_types` stores the catalog of vacation buckets the company
     * offers (annual, sick, unpaid, maternity, pilgrimage, marriage, ...).
     * Each type controls the defaults (days, approval, advance notice) that
     * are used when a request is opened.
     */
    public function up(): void
    {
        Schema::create('vacation_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name_ar', 100);
            $table->string('name_en', 100)->nullable();
            $table->string('color', 20)->nullable();
            $table->string('icon', 50)->nullable();

            // Default entitlements — used when no per-user override exists.
            $table->unsignedSmallInteger('default_days_per_year')->default(0);
            $table->unsignedSmallInteger('max_days_per_request')->default(0);
            $table->unsignedSmallInteger('max_carry_days')->default(0);
            $table->unsignedSmallInteger('advance_notice_days')->default(0);

            // Behavioural flags.
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('deducts_from_balance')->default(true);
            $table->boolean('counts_weekends')->default(false);
            $table->boolean('counts_holidays')->default(false);

            // Status + display ordering.
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'deleted_at']);
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vacation_types');
    }
};
