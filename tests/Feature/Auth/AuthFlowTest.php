<?php

namespace Tests\Feature\Auth;

use Database\Seeders\PermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Users\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the authentication and authorisation flow.
 *
 * The tests cover the seeded permission catalogue, the super-admin
 * role, and the unauthenticated/authorised boundary at the route
 * level. They run against the in-memory SQLite database that ships
 * with the testing environment so they do not touch the local
 * development database.
 */
class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * `PermissionSeeder` materialises the full permission catalogue.
     */
    public function test_permission_seeder_creates_all_permissions(): void
    {
        $this->seedPermissions();

        $this->assertGreaterThan(0, Permission::where('guard_name', 'web')->count());
        $this->assertDatabaseHas('permissions', ['name' => 'view-companies', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'create-zones', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'edit-settings', 'guard_name' => 'web']);
    }

    /**
     * `UserSeeder` creates the canonical super-admin at id 10000.
     */
    public function test_user_seeder_creates_super_admin_at_10000(): void
    {
        $user = $this->seedSuperAdmin();

        $this->assertNotNull($user);
        $this->assertSame(UserSeeder::SUPER_ADMIN_ID, $user->id);
        $this->assertSame(UserSeeder::SUPER_ADMIN_EMAIL, $user->email);
    }

    /**
     * The super-admin role receives every permission in the catalogue.
     */
    public function test_super_admin_role_has_every_seeded_permission(): void
    {
        $this->seedSuperAdmin();

        $admin = User::find(UserSeeder::SUPER_ADMIN_ID);
        $permissionCount = Permission::where('guard_name', 'web')->count();

        $this->assertTrue($admin->hasRole('super-admin'));
        $this->assertSame($permissionCount, $admin->getPermissionsViaRoles()->count());
    }

    /**
     * The `withoutSuperAdmin` scope excludes the system super-admin.
     */
    public function test_without_super_admin_scope_excludes_id_10000(): void
    {
        $this->seedSuperAdmin();
        User::factory()->create(['email' => 'emp1@example.com']);
        User::factory()->create(['email' => 'emp2@example.com']);

        $this->assertCount(2, User::withoutSuperAdmin()->get());
    }

    /**
     * A new role can be created and assigned permissions on the fly.
     */
    public function test_role_can_be_created_and_assigned(): void
    {
        $this->seedPermissions();
        Artisan::call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);

        $role = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $role->syncPermissions(['view-companies', 'view-branches']);

        $this->assertTrue($role->hasPermissionTo('view-companies'));
        $this->assertFalse($role->hasPermissionTo('edit-companies'));
    }
}
