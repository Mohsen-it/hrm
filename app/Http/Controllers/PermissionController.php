<?php

namespace App\Http\Controllers;

use Database\Seeders\PermissionSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * PermissionController — readonly catalogue plus role-grant helpers.
 *
 * Permissions are the leaves of the authorisation graph; they are
 * created by {@see PermissionSeeder} and never
 * written by the application. This controller only exposes the
 * catalogue and a single `attach`/`detach` action to manage which
 * roles receive which permission.
 */
class PermissionController extends Controller
{
    /**
     * Render the permissions catalogue grouped by module.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-permissions');

        $permissions = Permission::query()
            ->orderBy('name')
            ->get(['id', 'name', 'guard_name']);

        $grouped = $permissions
            ->groupBy(fn (Permission $p) => $this->moduleKey($p->name))
            ->map(fn ($items, $key) => [
                'module' => $key,
                'permissions' => $items->map(fn (Permission $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'guard_name' => $p->guard_name,
                ])->values(),
            ])
            ->values();

        $roles = Role::orderBy('name')
            ->get(['id', 'name', 'guard_name'])
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
            ]);

        return Inertia::render('Permissions/Index', [
            'grouped' => fn () => $grouped,
            'roles' => fn () => $roles,
            'filters' => fn () => $request->only(['module', 'guard']),
        ]);
    }

    /**
     * Grant a permission to a role.
     */
    public function attach(Request $request): RedirectResponse
    {
        $this->authorize('edit-permissions');

        $data = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
            'permission' => ['required', 'string', 'exists:permissions,name'],
        ]);

        $role = Role::findByName($data['role'], 'web');
        $role->givePermissionTo($data['permission']);

        return redirect()->route('permissions.index')
            ->with('success', __('permissions.attached_successfully'));
    }

    /**
     * Revoke a permission from a role.
     */
    public function detach(Request $request): RedirectResponse
    {
        $this->authorize('edit-permissions');

        $data = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
            'permission' => ['required', 'string', 'exists:permissions,name'],
        ]);

        $role = Role::findByName($data['role'], 'web');
        $role->revokePermissionTo($data['permission']);

        return redirect()->route('permissions.index')
            ->with('success', __('permissions.detached_successfully'));
    }

    /**
     * Derive the module key from a permission name.
     *
     * `view-companies` → `companies`, `view-fingerprint-devices` →
     * `fingerprint-devices`, `approve-vacation-requests` →
     * `vacation-requests`.
     */
    private function moduleKey(string $name): string
    {
        $stripped = (string) str($name)->after('-');

        return match (true) {
            str_contains($stripped, 'vacation-requests') => 'vacation-requests',
            str_contains($stripped, 'vacation-types') => 'vacation-types',
            str_contains($stripped, 'fingerprint-device-types') => 'fingerprint-device-types',
            str_contains($stripped, 'fingerprint-devices') => 'fingerprint-devices',
            default => (string) str($stripped)->before('-'),
        };
    }
}
