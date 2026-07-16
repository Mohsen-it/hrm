<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds all module permissions and grants them to the super-admin role.
 *
 * Permission format: {action}-{module}
 * Actions: view, create, edit, delete
 */
class PermissionSeeder extends Seeder
{
    /**
     * Modules and their available actions.
     *
     * @var array<string, array<int, string>>
     */
    protected array $modules = [
        'companies' => ['view', 'create', 'edit', 'delete'],
        'branches' => ['view', 'create', 'edit', 'delete'],
        'departments' => ['view', 'create', 'edit', 'delete'],
        'positions' => ['view', 'create', 'edit', 'delete'],
        'grades' => ['view', 'create', 'edit', 'delete'],
        'shifts' => ['view', 'create', 'edit', 'delete'],
        'users' => ['view', 'create', 'edit', 'delete'],
        'attendance' => ['view', 'create', 'edit', 'delete'],
        'fingerprint-devices' => ['view', 'create', 'edit', 'delete'],
        'fingerprint-device-types' => ['view', 'create', 'edit', 'delete'],
        'vacation-types' => ['view', 'create', 'edit', 'delete'],
        'vacation-requests' => ['view', 'create', 'edit', 'delete'],
        'vacations' => ['view', 'create', 'edit', 'delete'],
        'holidays' => ['view', 'create', 'edit', 'delete'],
        'zones' => ['view', 'create', 'edit', 'delete'],
        'settings' => ['view', 'create', 'edit', 'delete'],
        'roles' => ['view', 'create', 'edit', 'delete'],
        'permissions' => ['view', 'edit'],
    ];

    /**
     * Extra permissions that are not tied to a standard CRUD module.
     *
     * @var array<int, string>
     */
    protected array $extraPermissions = [
        'view-dashboard',
        'approve-vacation-requests',
        'view-shift-categories',
        'create-shift-categories',
        'edit-shift-categories',
        'delete-shift-categories',
        'view-time-schedules',
        'create-time-schedules',
        'edit-time-schedules',
        'delete-time-schedules',
        'assign-employees-to-category',
        'view-attendance-by-schedule',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = $this->createPermissions();

        $this->assignPermissionsToSuperAdmin($permissions);
    }

    /**
     * Create every permission in the catalog and return their names.
     *
     * @return array<int, string>
     */
    protected function createPermissions(): array
    {
        $names = [];

        foreach ($this->modules as $module => $actions) {
            foreach ($actions as $action) {
                $names[] = $this->ensurePermission("{$action}-{$module}");
            }
        }

        foreach ($this->extraPermissions as $extra) {
            $names[] = $this->ensurePermission($extra);
        }

        return $names;
    }

    /**
     * Create the permission if it does not exist and return its name.
     */
    protected function ensurePermission(string $name): string
    {
        Permission::firstOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
        );

        return $name;
    }

    /**
     * Grant every supplied permission to the super-admin role.
     *
     * @param  array<int, string>  $permissions
     */
    protected function assignPermissionsToSuperAdmin(array $permissions): void
    {
        $role = Role::firstOrCreate(
            ['name' => 'super-admin', 'guard_name' => 'web'],
        );

        $role->syncPermissions($permissions);
    }
}
