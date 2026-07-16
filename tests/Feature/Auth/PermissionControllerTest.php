<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\PermissionController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for {@see PermissionController}.
 */
class PermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Index renders the grouped permission catalogue.
     */
    public function test_index_renders_for_super_admin(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('permissions.index'))->assertOk();
    }

    /**
     * `attach` grants a permission to a role.
     */
    public function test_attach_grants_permission_to_role(): void
    {
        $this->actAsSuperAdmin();
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $permissionName = 'create-holidays';
        Permission::findOrCreate($permissionName, 'web');

        $this->post(route('permissions.attach'), [
            'role' => 'editor',
            'permission' => $permissionName,
        ])->assertRedirect(route('permissions.index'));

        $this->assertTrue($role->fresh()->hasPermissionTo($permissionName));
    }

    /**
     * `detach` revokes a permission from a role.
     */
    public function test_detach_revokes_permission_from_role(): void
    {
        $this->actAsSuperAdmin();
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $permissionName = 'create-holidays';
        Permission::findOrCreate($permissionName, 'web');
        $role->givePermissionTo($permissionName);

        $this->post(route('permissions.detach'), [
            'role' => 'editor',
            'permission' => $permissionName,
        ])->assertRedirect(route('permissions.index'));

        $this->assertFalse($role->fresh()->hasPermissionTo($permissionName));
    }

    /**
     * `attach` rejects unknown permission names.
     */
    public function test_attach_rejects_unknown_permission(): void
    {
        $this->actAsSuperAdmin();
        Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $this->post(route('permissions.attach'), [
            'role' => 'editor',
            'permission' => 'invented-permission',
        ])->assertSessionHasErrors('permission');
    }
}
