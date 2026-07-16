<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * RoleController — manage Spatie roles from the admin UI.
 *
 * All routes are gated by the `manage-roles` permission. The controller
 * intentionally uses thin payloads and delegates the actual creation /
 * update / deletion work to Spatie's Role model.
 */
class RoleController extends Controller
{
    /**
     * Display the roles catalogue.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-roles');

        $filters = $request->only(['search', 'guard']);

        $roles = Role::query()
            ->with('permissions')
            ->withCount('users')
            ->when(
                ! empty($filters['search']),
                fn ($q) => $q->where('name', 'like', '%'.$filters['search'].'%'),
            )
            ->when(
                ! empty($filters['guard']),
                fn ($q) => $q->where('guard_name', $filters['guard']),
            )
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Roles/Index', [
            'roles' => fn () => $roles->through(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions_count' => $role->permissions->count(),
                'users_count' => $role->users_count,
                'created_at' => optional($role->created_at)->toDateTimeString(),
            ]),
            'filters' => fn () => $filters,
            'permissions' => fn () => Permission::orderBy('name')
                ->get(['id', 'name'])
                ->groupBy(fn (Permission $p) => str($p->name)->after('-')->beforeLast('-')->toString()),
        ]);
    }

    /**
     * Persist a new role and sync its permission set.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create-roles');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'unique:roles,name'],
            'guard_name' => ['nullable', 'string', 'in:web,api'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => $data['guard_name'] ?? 'web',
        ]);

        if (! empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return redirect()->route('roles.index')
            ->with('success', __('roles.created_successfully'));
    }

    /**
     * Update a role's name and permission set.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->authorize('edit-roles');

        $role = Role::findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'unique:roles,name,'.$role->id],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->update(['name' => $data['name']]);

        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('roles.index')
            ->with('success', __('roles.updated_successfully'));
    }

    /**
     * Remove a role from the catalogue.
     */
    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('delete-roles');

        $role = Role::findOrFail($id);

        if ($role->name === 'super-admin') {
            return redirect()->route('roles.index')
                ->withErrors(['role' => __('roles.cannot_delete_super_admin')]);
        }

        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', __('roles.deleted_successfully'));
    }
}
