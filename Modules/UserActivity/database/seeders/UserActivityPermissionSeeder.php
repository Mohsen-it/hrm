<?php

namespace Modules\UserActivity\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the `view-activity-logs` permission and grants it to the
 * super-admin role.
 */
class UserActivityPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(
            ['name' => 'view-activity-logs', 'guard_name' => 'web']
        );

        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        if (! $role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }
    }
}
