<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Many-to-many pivot between users and zones
     * (geofencing areas where the user is allowed to clock-in).
     *
     * The foreign key on zone_id is created without `constrained()` to
     * avoid ordering issues with the future Zones module migration.
     * The application-level relation is still defined in the User model.
     */
    public function up(): void
    {
        Schema::create('user_zone', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('zone_id');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'zone_id']);
            $table->index(['user_id', 'is_primary']);
            $table->index('zone_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_zone');
    }
};
