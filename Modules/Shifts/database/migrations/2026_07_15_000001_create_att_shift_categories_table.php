<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_shift_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name', 100);
            $table->enum('type', ['cyclic', 'weekly', 'hours']);
            $table->unsignedSmallInteger('work_days')->nullable();
            $table->unsignedSmallInteger('rest_days')->nullable();
            $table->json('work_days_json')->nullable();
            $table->json('weekend_days_json')->nullable();
            $table->decimal('required_hours', 6, 2)->nullable();
            $table->enum('period_type', ['daily', 'weekly', 'monthly'])->nullable();
            $table->boolean('overtime_enabled')->default(false);
            $table->boolean('fingerprint_enabled')->default(true);
            $table->boolean('work_on_holidays')->default(false);
            $table->boolean('work_on_weekends')->default(false);
            $table->string('color', 7)->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->unique(['name', 'company_id']);
            $table->index('type');
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_shift_categories');
    }
};
