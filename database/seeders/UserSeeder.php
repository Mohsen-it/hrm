<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Users\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds the HRM system super-admin user.
 *
 * The super-admin record lives at id = 10000 and is excluded from all
 * domain queries through the `withoutSuperAdmin` scope on the User model.
 */
class UserSeeder extends Seeder
{
    /**
     * The fixed identifier reserved for the system super-admin.
     */
    public const SUPER_ADMIN_ID = 10000;

    /**
     * The email reserved for the system super-admin.
     */
    public const SUPER_ADMIN_EMAIL = 'admin@hrm.local';

    /**
     * The default password for the system super-admin.
     */
    public const SUPER_ADMIN_PASSWORD = 'password';

    /**
     * The bcrypt rounds used when seeding the super-admin password.
     */
    public const BCRYPT_ROUNDS = 12;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->ensureBcryptRounds();
        $this->truncateGuardModels();

        $role = $this->ensureSuperAdminRole();
        $this->syncAllPermissionsTo($role);

        $this->ensureSuperAdminUser($role);

        $this->restoreBcryptRounds();
    }

    /**
     * Configure the hasher to use 12 bcrypt rounds while seeding.
     */
    protected function ensureBcryptRounds(): void
    {
        config()->set('hashing.bcrypt.rounds', self::BCRYPT_ROUNDS);
    }

    /**
     * Restore the previously configured bcrypt rounds when finished.
     */
    protected function restoreBcryptRounds(): void
    {
        $previous = env('BCRYPT_ROUNDS', 12);
        config()->set('hashing.bcrypt.rounds', (int) $previous);
    }

    /**
     * Reset Spatie pivot mappings for a clean seed.
     *
     * Idempotent seeding is preferred over destructive resets for production
     * reruns; therefore we only truncate when no super-admin user exists yet.
     *
     * Note: in Spatie's permission package the `model_has_roles` and
     * `model_has_permissions` pivot tables do NOT carry a `guard_name`
     * column (the guard is stored on `roles` / `permissions`). The
     * `where('guard_name', 'web')` clause would crash on MySQL.
     */
    protected function truncateGuardModels(): void
    {
        $exists = User::where('id', self::SUPER_ADMIN_ID)
            ->orWhere('email', self::SUPER_ADMIN_EMAIL)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('model_has_roles')->delete();
        DB::table('model_has_permissions')->delete();
    }

    /**
     * Ensure the super-admin role exists and return it.
     */
    protected function ensureSuperAdminRole(): Role
    {
        return Role::firstOrCreate(
            ['name' => 'super-admin', 'guard_name' => 'web'],
        );
    }

    /**
     * Grant every existing permission to the supplied role.
     */
    protected function syncAllPermissionsTo(Role $role): void
    {
        $permissionNames = Permission::where('guard_name', 'web')->pluck('name')->all();

        if (! empty($permissionNames)) {
            $role->syncPermissions($permissionNames);
        }
    }

    /**
     * Create or refresh the system super-admin user and assign the role.
     *
     * Inserts through `DB::table` so the fixed primary key (10000) is honored
     * regardless of the auto-increment configuration of the underlying driver.
     */
    protected function ensureSuperAdminUser(Role $role): void
    {
        $now = now();

        $payload = [
            'id' => self::SUPER_ADMIN_ID,
            'name' => 'System Super Admin',
            'first_name' => 'System',
            'last_name' => 'Super Admin',
            'full_name_ar' => 'مدير النظام',
            'full_name_en' => 'System Super Admin',
            'email' => self::SUPER_ADMIN_EMAIL,
            'password' => Hash::make(self::SUPER_ADMIN_PASSWORD),
            'status' => 1,
            'is_active_employee' => true,
            'email_verified_at' => $now,
            'must_change_password' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        DB::table('users')->updateOrInsert(
            ['id' => self::SUPER_ADMIN_ID],
            $payload,
        );

        $user = User::find(self::SUPER_ADMIN_ID);

        if ($user) {
            $user->syncRoles([$role->name]);
        }
    }
}
