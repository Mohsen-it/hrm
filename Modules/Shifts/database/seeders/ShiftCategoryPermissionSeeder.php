<?php

namespace Modules\Shifts\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class ShiftCategoryPermissionSeeder extends Seeder
{
    /**
     * Permissions to seed.
     *
     * @var array<int, string>
     */
    protected array $permissions = [
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
        'view-rotations',
        'create-rotations',
        'edit-rotations',
        'delete-rotations',
        'assign-employees-to-rotation',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
            );
        }
    }
}
