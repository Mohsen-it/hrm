<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Key-value store for system-level configuration. The `type` column
     * controls casting; the `group` column drives the UI tabs; the
     * `is_public` flag hides secrets from non-admin views.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 150)->unique();
            $table->text('value')->nullable();
            $table->string('type', 30)->default('string');
            $table->string('group', 50)->default('general');
            $table->string('name_ar', 200)->nullable();
            $table->string('name_en', 200)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_encrypted')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['group', 'sort_order'], 'settings_group_order_index');
            $table->index('is_public', 'settings_public_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
