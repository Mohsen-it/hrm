<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\RoleController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for {@see RoleController}.
 */
class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Index returns 200 for the super-admin.
     */
    public function test_index_renders_for_super_admin(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('roles.index'))->assertOk();
    }

    /**
     * `store` creates a new role and grants its permissions.
     */
    public function test_store_creates_role_with_permissions(): void
    {
        $this->actAsSuperAdmin();

        $this->post(route('roles.store'), [
            'name' => 'manager',
            'guard_name' => 'web',
            'permissions' => ['view-companies', 'view-branches'],
        ])->assertRedirect(route('roles.index'));

        $role = Role::findByName('manager', 'web');
        $this->assertTrue($role->hasPermissionTo('view-companies'));
        $this->assertTrue($role->hasPermissionTo('view-branches'));
        $this->assertFalse($role->hasPermissionTo('edit-companies'));
    }

    /**
     * `store` rejects a duplicate role name.
     */
    public function test_store_rejects_duplicate_name(): void
    {
        $this->actAsSuperAdmin();
        Role::create(['name' => 'duplicate', 'guard_name' => 'web']);

        $this->post(route('roles.store'), [
            'name' => 'duplicate',
            'guard_name' => 'web',
        ])->assertSessionHasErrors('name');
    }

    /**
     * `update` modifies the role's name and permission set.
     */
    public function test_update_persists_changes(): void
    {
        $this->actAsSuperAdmin();
        $role = Role::create(['name' => 'editable', 'guard_name' => 'web']);
        $role->givePermissionTo('view-companies');

        $this->put(route('roles.update', $role->id), [
            'name' => 'renamed',
            'permissions' => ['view-companies', 'view-branches'],
        ])->assertRedirect(route('roles.index'));

        $role->refresh();
        $this->assertSame('renamed', $role->name);
        $this->assertTrue($role->hasPermissionTo('view-branches'));
    }

    /**
     * `destroy` removes the role from the catalogue.
     */
    public function test_destroy_deletes_role(): void
    {
        $this->actAsSuperAdmin();
        $role = Role::create(['name' => 'deletable', 'guard_name' => 'web']);

        $this->delete(route('roles.destroy', $role->id))
            ->assertRedirect(route('roles.index'));

        $this->assertNull(Role::find($role->id));
    }

    /**
     * `destroy` refuses to remove the super-admin role.
     */
    public function test_destroy_refuses_to_delete_super_admin(): void
    {
        $this->actAsSuperAdmin();
        $adminRole = Role::findByName('super-admin', 'web');

        $this->delete(route('roles.destroy', $adminRole->id))
            ->assertRedirect(route('roles.index'))
            ->assertSessionHasErrors('role');

        $this->assertNotNull(Role::find($adminRole->id));
    }
}
