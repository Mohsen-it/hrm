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
        Schema::create('zones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150)->nullable();
            $table->enum('zone_type', ['geographic', 'operational', 'security', 'sales', 'logistics'])->default('geographic');
            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->integer('radius_meters')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('branches_count')->default(0);
            $table->integer('employees_count')->default(0);
            $table->integer('devices_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'is_active', 'deleted_at'], 'zones_company_active_index');
            $table->index(['zone_type', 'is_active'], 'zones_type_active_index');
            $table->index(['city', 'region'], 'zones_city_region_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
