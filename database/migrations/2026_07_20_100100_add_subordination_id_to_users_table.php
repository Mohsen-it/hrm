<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('subordination_id')
                ->nullable()
                ->after('grade_id')
                ->constrained('subordinations')
                ->nullOnDelete();

            $table->index('subordination_id', 'users_subordination_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['subordination_id']);
            $table->dropIndex('users_subordination_id_index');
            $table->dropColumn('subordination_id');
        });
    }
};
