<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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

        $filters = $request->only(['search', 'guard', 'per_page']);

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
            ->orderBy('name');

        $perPage = $filters['per_page'] ?? 20;

        if ($perPage === 'all' || $perPage === -1) {
            $items = $roles->get();
            $total = $items->count();
            $roles = new LengthAwarePaginator(
                $items,
                $total,
                $total,
                1,
                ['path' => request()->url()]
            );
        } else {
            $roles = $roles->paginate((int) $perPage)->withQueryString();
        }

        return Inertia::render('Roles/Index', [
            'roles' => fn () => $roles->through(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions_count' => $role->permissions->count(),
                'permission_names' => $role->permissions->pluck('name')->values(),
                'users_count' => $role->users_count,
                'created_at' => optional($role->created_at)->toDateTimeString(),
            ]),
            'filters' => fn () => $filters,
            'permissions' => fn () => Permission::orderBy('name')
                ->get(['id', 'name', 'guard_name'])
                ->groupBy(fn (Permission $permission) => $this->permissionGroup($permission->name))
                ->map(fn ($permissions) => $permissions->map(fn (Permission $permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name,
                ])->values()),
        ]);
    }

    /**
     * Derive a readable group from a permission name.
     */
    private function permissionGroup(string $permission): string
    {
        return (string) str($permission)->after('-');
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
