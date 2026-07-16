<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Pivot table that records which branches belong to a given zone.
     * `is_primary` flags the branch the user visits most often so the
     * attendance flow can prefer it when multiple branches are eligible.
     */
    public function up(): void
    {
        Schema::create('zone_branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->integer('priority')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['zone_id', 'branch_id'], 'zone_branches_unique');
            $table->index(['zone_id', 'is_primary'], 'zone_branches_zone_primary_index');
            $table->index(['branch_id', 'zone_id'], 'zone_branches_branch_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zone_branches');
    }
};
