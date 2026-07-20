<?php

namespace Modules\Users\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Attendance\Models\AttendanceEmployee;
use Modules\Shifts\Services\RotationService;
use Modules\Shifts\Services\ShiftCategoryAssignmentService;
use Modules\Users\Models\User;
use Modules\Users\Repositories\UserRepository;

class UserService
{
    public function __construct(
        private UserRepository $repository,
        private RotationService $rotationService,
        private ShiftCategoryAssignmentService $shiftCategoryAssignmentService,
    ) {}

    // ------------------------------------------------------------------
    // Read
    // ------------------------------------------------------------------

    /**
     * Get all users with filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAllUsers(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->getAll($filters, $perPage);
    }

    /**
     * Get all active employees.
     *
     * @return Collection<int, User>
     */
    public function getActiveUsers(): Collection
    {
        return $this->repository->getActive();
    }

    /**
     * Get all users belonging to a company.
     *
     * @return Collection<int, User>
     */
    public function getUsersByCompany(int $companyId): Collection
    {
        return $this->repository->getByCompany($companyId);
    }

    /**
     * Get all users belonging to a branch.
     *
     * @return Collection<int, User>
     */
    public function getUsersByBranch(int $branchId): Collection
    {
        return $this->repository->getByBranch($branchId);
    }

    /**
     * Get all users belonging to a department.
     *
     * @return Collection<int, User>
     */
    public function getUsersByDepartment(int $departmentId): Collection
    {
        return $this->repository->getByDepartment($departmentId);
    }

    /**
     * Get all users assigned to a shift.
     *
     * @return Collection<int, User>
     */
    public function getUsersByShift(int $shiftId): Collection
    {
        return $this->repository->getByShift($shiftId);
    }

    /**
     * Get all users with a specific grade.
     *
     * @return Collection<int, User>
     */
    public function getUsersByGrade(int $gradeId): Collection
    {
        return $this->repository->getByGrade($gradeId);
    }

    /**
     * Find a user by its primary key.
     */
    public function getUserById(int $id): ?User
    {
        return $this->repository->findById($id);
    }

    /**
     * Find a user by email.
     */
    public function getUserByEmail(string $email): ?User
    {
        return $this->repository->findByEmail($email);
    }

    /**
     * Find a user by employee code.
     */
    public function getUserByEmployeeCode(string $code): ?User
    {
        return $this->repository->findByEmployeeCode($code);
    }

    // ------------------------------------------------------------------
    // CRUD
    // ------------------------------------------------------------------

    /**
     * Create a new user.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createUser(array $data): User
    {
        $validated = $this->validateUserData($data);

        if (isset($validated['avatar']) && $validated['avatar'] instanceof UploadedFile) {
            $validated['avatar'] = $this->uploadAvatar($validated['avatar']);
        }

        $roles = $validated['roles'] ?? null;
        $permissions = $validated['permissions'] ?? null;
        $shifts = $validated['shifts'] ?? null;
        $zones = $validated['zones'] ?? null;
        $password = $validated['password'] ?? null;
        $attendanceGroupId = $validated['attendance_group_id'] ?? null;

        unset(
            $validated['roles'],
            $validated['permissions'],
            $validated['shifts'],
            $validated['zones'],
            $validated['attendance_group_id'],
        );

        return DB::transaction(function () use ($validated, $roles, $permissions, $shifts, $zones, $password, $attendanceGroupId) {
            if (! empty($validated['name']) && (empty($validated['first_name']) || empty($validated['last_name']))) {
                $parts = explode(' ', trim((string) $validated['name']), 2);
                $validated['first_name'] ??= $parts[0] ?? null;
                $validated['last_name'] ??= $parts[1] ?? null;
            }

            $user = $this->repository->create($validated);

            if ($password) {
                $user->forceFill(['must_change_password' => true])->save();
            }

            if (is_array($roles)) {
                $user->syncRoles($roles);
            }

            if (is_array($permissions)) {
                $user->syncPermissions($permissions);
            }

            if (is_array($shifts)) {
                $this->syncShifts($user, $shifts);
            }

            if (is_array($zones)) {
                $user->zones()->sync($zones);
            }

            if ($attendanceGroupId) {
                AttendanceEmployee::create([
                    'emp_id' => $user->id,
                    'group_id' => $attendanceGroupId,
                    'enable_attendance' => true,
                    'enable_schedule' => true,
                    'enable_overtime' => false,
                    'enable_holiday' => true,
                    'enable_compensatory' => false,
                ]);
            }

            return $user->fresh([
                ...['company', 'branch', 'department', 'position', 'grade', 'shift', 'manager'],
                'shifts', 'roles', 'permissions',
            ]);
        });
    }

    /**
     * Update the given user.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateUser(User $user, array $data): User
    {
        $validated = $this->validateUserData($data, $user->id);

        if (isset($validated['avatar']) && $validated['avatar'] instanceof UploadedFile) {
            $validated['avatar'] = $this->uploadAvatar($validated['avatar'], $user->avatar);
        }

        $roles = $validated['roles'] ?? null;
        $permissions = $validated['permissions'] ?? null;
        $shifts = $validated['shifts'] ?? null;
        $zones = $validated['zones'] ?? null;
        $attendanceGroupId = $validated['attendance_group_id'] ?? null;
        $rotationAssignment = $validated['rotation_assignment'] ?? null;
        $shiftCategoryAssignment = $validated['shift_category_assignment'] ?? null;

        unset(
            $validated['roles'],
            $validated['permissions'],
            $validated['shifts'],
            $validated['zones'],
            $validated['attendance_group_id'],
            $validated['rotation_assignment'],
            $validated['shift_category_assignment'],
        );

        return DB::transaction(function () use ($user, $validated, $roles, $permissions, $shifts, $zones, $attendanceGroupId, $rotationAssignment, $shiftCategoryAssignment) {
            $user = $this->repository->update($user, $validated);

            if (is_array($roles)) {
                $user->syncRoles($roles);
            }

            if (is_array($permissions)) {
                $user->syncPermissions($permissions);
            }

            if (is_array($shifts)) {
                $this->syncShifts($user, $shifts);
            }

            if (is_array($zones)) {
                $user->zones()->sync($zones);
            }

            if ($attendanceGroupId) {
                AttendanceEmployee::updateOrCreate(
                    ['emp_id' => $user->id],
                    ['group_id' => $attendanceGroupId]
                );
            }

            if (is_array($rotationAssignment)) {
                $this->handleRotationAssignment($user->id, $rotationAssignment);
            }

            if (is_array($shiftCategoryAssignment)) {
                $this->handleShiftCategoryAssignment($user->id, $shiftCategoryAssignment);
            }

            return $user->fresh([
                ...['company', 'branch', 'department', 'position', 'grade', 'shift', 'manager'],
                'shifts', 'roles', 'permissions',
            ]);
        });
    }

    /**
     * Soft delete the given user.
     */
    public function deleteUser(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'user' => __('users.cannot_delete_super_admin'),
            ]);
        }

        return $this->repository->delete($user);
    }

    /**
     * Bulk delete users by their IDs.
     *
     * @param  array<int, int>  $ids
     */
    public function bulkDeleteUsers(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn ($v) => $v > 0));
        $ids = array_values(array_diff($ids, [User::SUPER_ADMIN_ID]));

        if (empty($ids)) {
            return 0;
        }

        return $this->repository->bulkDelete($ids);
    }

    // ------------------------------------------------------------------
    // Roles & permissions
    // ------------------------------------------------------------------

    /**
     * Assign a role to the user.
     *
     * @param  string|array<int, string>  $role
     */
    public function assignRole(User $user, string|array $role): User
    {
        $user->assignRole($role);

        return $user->fresh('roles');
    }

    /**
     * Remove a role from the user.
     *
     * @param  string|array<int, string>  $role
     */
    public function removeRole(User $user, string|array $role): User
    {
        $user->removeRole($role);

        return $user->fresh('roles');
    }

    /**
     * Sync roles for the user.
     *
     * @param  array<int, string>  $roles
     */
    public function syncRoles(User $user, array $roles): User
    {
        $user->syncRoles($roles);

        return $user->fresh('roles');
    }

    /**
     * Grant a direct permission to the user.
     *
     * @param  string|array<int, string>  $permission
     */
    public function givePermissionTo(User $user, string|array $permission): User
    {
        $user->givePermissionTo($permission);

        return $user->fresh('permissions');
    }

    /**
     * Revoke a direct permission from the user.
     *
     * @param  string|array<int, string>  $permission
     */
    public function revokePermissionTo(User $user, string|array $permission): User
    {
        $user->revokePermissionTo($permission);

        return $user->fresh('permissions');
    }

    // ------------------------------------------------------------------
    // Shifts
    // ------------------------------------------------------------------

    /**
     * Sync the many-to-many shifts for the user.
     *
     * Each entry must contain: shift_id, effective_from?, effective_to?, is_primary?
     *
     * @param  array<int, array<string, mixed>>  $shifts
     */
    public function syncShifts(User $user, array $shifts): User
    {
        $sync = [];
        $hasPrimary = false;

        foreach ($shifts as $entry) {
            $shiftId = (int) ($entry['shift_id'] ?? 0);
            if ($shiftId <= 0) {
                continue;
            }

            $isPrimary = (bool) ($entry['is_primary'] ?? false);
            if ($isPrimary) {
                $hasPrimary = true;
            }

            $sync[$shiftId] = [
                'effective_from' => $entry['effective_from'] ?? null,
                'effective_to' => $entry['effective_to'] ?? null,
                'is_primary' => $isPrimary,
            ];
        }

        // Ensure at most one primary
        if ($hasPrimary) {
            foreach ($sync as $id => $attrs) {
                if (! $attrs['is_primary']) {
                    $sync[$id]['is_primary'] = false;
                }
            }
        }

        $user->shifts()->sync($sync);

        return $user->fresh('shifts');
    }

    /**
     * Attach a single shift to the user.
     *
     * @param  array<string, mixed>  $pivot
     */
    public function attachShift(User $user, int $shiftId, array $pivot = []): User
    {
        $user->shifts()->syncWithoutDetaching([
            $shiftId => array_merge([
                'effective_from' => null,
                'effective_to' => null,
                'is_primary' => false,
            ], $pivot),
        ]);

        return $user->fresh('shifts');
    }

    /**
     * Detach a shift from the user.
     */
    public function detachShift(User $user, int $shiftId): User
    {
        $user->shifts()->detach($shiftId);

        return $user->fresh('shifts');
    }

    // ------------------------------------------------------------------
    // Zones
    // ------------------------------------------------------------------

    /**
     * Sync the many-to-many zones for the user.
     *
     * @param  array<int, int>  $zoneIds
     */
    public function syncZones(User $user, array $zoneIds): User
    {
        $user->zones()->sync($zoneIds);

        return $user->fresh('zones');
    }

    // ------------------------------------------------------------------
    // Rotation & Shift Category Assignments
    // ------------------------------------------------------------------

    /**
     * Handle rotation assignment update from the user edit form.
     */
    private function handleRotationAssignment(int $employeeId, array $data): void
    {
        $action = $data['action'] ?? null;

        if ($action === 'assign' || $action === 'transfer') {
            $rotationId = (int) ($data['rotation_id'] ?? 0);
            $groupId = (int) ($data['rotation_group_id'] ?? 0);
            $startDate = $data['start_date'] ?? null;

            if ($rotationId > 0 && $groupId > 0 && $startDate) {
                if ($action === 'transfer') {
                    $this->rotationService->transferEmployee($employeeId, $rotationId, $groupId, $startDate);
                } else {
                    $this->rotationService->assignEmployee($employeeId, $rotationId, $groupId, $startDate);
                }
            }
        } elseif ($action === 'unassign') {
            $endDate = $data['end_date'] ?? now()->toDateString();
            $this->rotationService->unassignEmployee($employeeId, $endDate);
        }
    }

    /**
     * Handle shift category assignment update from the user edit form.
     */
    private function handleShiftCategoryAssignment(int $employeeId, array $data): void
    {
        $action = $data['action'] ?? null;

        if ($action === 'assign' || $action === 'transfer') {
            $categoryId = (int) ($data['shift_category_id'] ?? 0);
            $startDate = $data['start_date'] ?? null;

            if ($categoryId > 0 && $startDate) {
                if ($action === 'transfer') {
                    $this->shiftCategoryAssignmentService->transferEmployee($employeeId, $categoryId, $startDate);
                } else {
                    $this->shiftCategoryAssignmentService->assignEmployee($employeeId, $categoryId, $startDate, $data['end_date'] ?? null);
                }
            }
        } elseif ($action === 'unassign') {
            $endDate = $data['end_date'] ?? now()->toDateString();
            $this->shiftCategoryAssignmentService->unassignEmployee($employeeId, $endDate);
        }
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    /**
     * Validate user data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validateUserData(array $data, ?int $ignoreId = null): array
    {
        $rules = [
            'employee_code' => array_merge(
                ['nullable', 'string', 'max:50'],
                $this->uniqueEmployeeCodeRule($ignoreId),
            ),
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'full_name_ar' => ['nullable', 'string', 'max:255'],
            'full_name_en' => ['nullable', 'string', 'max:255'],
            'email' => array_merge(
                ['required', 'email', 'max:255'],
                $this->uniqueEmailRule($ignoreId),
            ),
            'password' => [$ignoreId ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:20'],
            'phone2' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female'],
            'marital_status' => ['nullable', 'in:single,married,divorced,widowed'],
            'nationality' => ['nullable', 'string', 'max:50'],

            'hire_date' => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'employment_type' => ['nullable', 'in:full_time,part_time,contract,temporary,intern'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'work_location' => ['nullable', 'string', 'max:255'],

            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:50'],
            'state' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],

            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],

            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'iban' => ['nullable', 'string', 'max:50'],

            'avatar' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],

            'status' => ['required', 'integer', 'in:0,1'],
            'is_active_employee' => ['boolean'],
            'must_change_password' => ['boolean'],

            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'subordination_id' => ['nullable', 'integer', 'exists:subordinations,id'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'attendance_group_id' => ['nullable', 'integer', 'exists:att_attgroup,id'],

            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'shifts' => ['nullable', 'array'],
            'shifts.*.shift_id' => ['required_with:shifts', 'integer', 'exists:shifts,id'],
            'shifts.*.effective_from' => ['nullable', 'date'],
            'shifts.*.effective_to' => ['nullable', 'date', 'after_or_equal:shifts.*.effective_from'],
            'shifts.*.is_primary' => ['nullable', 'boolean'],
            'zones' => ['nullable', 'array'],
            'rotation_assignment' => ['nullable', 'array'],
            'rotation_assignment.action' => ['nullable', 'in:assign,transfer,unassign'],
            'rotation_assignment.rotation_id' => ['required_with:rotation_assignment', 'integer', 'exists:att_rotations,id'],
            'rotation_assignment.rotation_group_id' => ['required_if:rotation_assignment.action,assign,transfer', 'integer', 'exists:att_rotation_groups,id'],
            'rotation_assignment.start_date' => ['required_if:rotation_assignment.action,assign,transfer', 'date'],
            'rotation_assignment.end_date' => ['nullable', 'date'],
            'shift_category_assignment' => ['nullable', 'array'],
            'shift_category_assignment.action' => ['nullable', 'in:assign,transfer,unassign'],
            'shift_category_assignment.shift_category_id' => ['required_if:shift_category_assignment.action,assign,transfer', 'integer', 'exists:att_shift_categories,id'],
            'shift_category_assignment.start_date' => ['required_if:shift_category_assignment.action,assign,transfer', 'date'],
            'shift_category_assignment.end_date' => ['nullable', 'date'],
        ];

        return Validator::make($data, $rules)->validate();
    }

    /**
     * Build the unique rule for email.
     *
     * @return array<int, string>
     */
    protected function uniqueEmailRule(?int $ignoreId): array
    {
        if ($ignoreId) {
            return ["unique:users,email,{$ignoreId},id"];
        }

        return ['unique:users,email'];
    }

    /**
     * Build the unique rule for employee_code.
     *
     * @return array<int, string>
     */
    protected function uniqueEmployeeCodeRule(?int $ignoreId): array
    {
        if ($ignoreId) {
            return ["unique:users,employee_code,{$ignoreId},id"];
        }

        return ['unique:users,employee_code'];
    }

    /**
     * Upload a user avatar and optionally remove the old one.
     */
    protected function uploadAvatar(UploadedFile $avatar, ?string $oldAvatar = null): string
    {
        $path = $avatar->store('users/avatars', 'public');

        if ($oldAvatar) {
            Storage::disk('public')->delete($oldAvatar);
        }

        return $path;
    }
}
