<?php

namespace Tests\Unit\Modules\Users;

use Database\Seeders\PermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Users\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Unit coverage for {@see User}.
 *
 * Focuses on the `withoutSuperAdmin` scope (the rule that excludes the
 * system super-admin id = 10000 from every domain query) and the
 * `active`/`employees` companion scopes that depend on it.
 */
class UserScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed permissions once per test class invocation.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);
    }

    /**
     * `withoutSuperAdmin` excludes the canonical super-admin id.
     */
    public function test_without_super_admin_excludes_id_10000(): void
    {
        $this->seedSuperAdmin();
        User::factory()->create(['email' => 'emp1@example.com']);
        User::factory()->create(['email' => 'emp2@example.com']);

        $this->assertCount(2, User::withoutSuperAdmin()->get());
    }

    /**
     * `employees` scope also excludes the super-admin (it calls withoutSuperAdmin).
     */
    public function test_employees_scope_excludes_super_admin(): void
    {
        $this->seedSuperAdmin();
        User::factory()->create(['email' => 'staff@example.com', 'is_active_employee' => true]);

        $this->assertCount(1, User::employees()->get());
    }

    /**
     * `active` scope includes only users with status = 1 and `is_active_employee = true`.
     *
     * The super-admin (id = 10000) is itself an `active` row in the table,
     * so we filter it out via `withoutSuperAdmin` to make the assertion
     * deterministic.
     */
    public function test_active_scope_filters_inactive(): void
    {
        $this->seedSuperAdmin();
        User::factory()->create(['is_active_employee' => true, 'status' => 1]);
        User::factory()->create(['is_active_employee' => true, 'status' => 0]);
        User::factory()->create(['is_active_employee' => false, 'status' => 1]);

        $this->assertCount(1, User::active()->withoutSuperAdmin()->get());
    }

    /**
     * UserSeeder creates the canonical super-admin at id 10000.
     */
    public function test_user_seeder_creates_super_admin_at_10000(): void
    {
        $this->seedSuperAdmin();

        $this->assertDatabaseHas('users', [
            'id' => UserSeeder::SUPER_ADMIN_ID,
            'email' => UserSeeder::SUPER_ADMIN_EMAIL,
        ]);
    }

    /**
     * Super-admin role gets every seeded permission via PermissionSeeder.
     */
    public function test_super_admin_role_has_every_seeded_permission(): void
    {
        $this->seedSuperAdmin();

        $admin = User::find(UserSeeder::SUPER_ADMIN_ID);

        $this->assertTrue($admin->hasRole('super-admin'));

        $permissionCount = Permission::where('guard_name', 'web')->count();
        $this->assertGreaterThan(0, $permissionCount);
        $this->assertSame($permissionCount, $admin->getPermissionsViaRoles()->count());
    }
}
