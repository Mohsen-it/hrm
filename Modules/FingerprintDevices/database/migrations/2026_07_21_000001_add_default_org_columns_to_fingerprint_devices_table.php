<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fingerprint_devices', function (Blueprint $table) {
            $table->foreignId('default_company_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('companies')
                ->nullOnDelete();

            $table->foreignId('default_branch_id')
                ->nullable()
                ->after('default_company_id')
                ->constrained('branches')
                ->nullOnDelete();

            $table->foreignId('default_subordination_id')
                ->nullable()
                ->after('default_branch_id')
                ->constrained('subordinations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fingerprint_devices', function (Blueprint $table) {
            $table->dropForeign(['default_company_id']);
            $table->dropForeign(['default_branch_id']);
            $table->dropForeign(['default_subordination_id']);

            $table->dropColumn([
                'default_company_id',
                'default_branch_id',
                'default_subordination_id',
            ]);
        });
    }
};
