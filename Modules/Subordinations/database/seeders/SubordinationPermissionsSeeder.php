<?php

namespace Modules\Subordinations\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SubordinationPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Resolve names against a fresh collection so `givePermissionTo`
        // never throws for permissions created inside this seeder.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'view-subordinations',
            'create-subordinations',
            'edit-subordinations',
            'delete-subordinations',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web']
            );
        }

        $superAdmin = Role::where('name', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permissions);
        }

        // Clear cached permissions
        Artisan::call('permission:cache-reset');
    }
}
