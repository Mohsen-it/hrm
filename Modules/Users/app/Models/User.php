<?php

namespace Modules\Users\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Branches\Models\Branch;
use Modules\Companies\Models\Company;
use Modules\Departments\Models\Department;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\Grades\Models\Grade;
use Modules\Positions\Models\Position;
use Modules\Shifts\Models\Shift;
use Modules\Vacations\Models\UserVacationBalance;
use Modules\Vacations\Models\UserVacationRequest;
use Modules\Vacations\Models\Vacation;
use Modules\Zones\Models\Zone;
use Spatie\Permission\Traits\HasRoles;

/**
 * User — central HRM model.
 *
 * Acts as both the authentication user (extends Laravel's Authenticatable)
 * and the employee record. The system super-admin (id = 10000) is excluded
 * from all domain queries via the `withoutSuperAdmin` scope.
 */
class User extends Authenticatable
{
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    /**
     * The ID of the system super-admin that must be excluded from queries.
     */
    public const SUPER_ADMIN_ID = 10000;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_code', 'name', 'first_name', 'last_name',
        'full_name_ar', 'full_name_en', 'email', 'email_verified_at',
        'password', 'national_id', 'phone', 'phone2', 'date_of_birth',
        'gender', 'marital_status', 'nationality',
        'hire_date', 'termination_date', 'employment_type',
        'job_title', 'work_location',
        'address', 'city', 'state', 'country', 'postal_code',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
        'bank_name', 'bank_account_number', 'iban',
        'avatar', 'face_photo_path',
        'status', 'is_active_employee',
        'last_login_at', 'last_login_ip',
        'must_change_password', 'failed_login_attempts', 'locked_until',
        'company_id', 'branch_id', 'department_id', 'position_id', 'grade_id',
        'shift_id', 'manager_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'date_of_birth' => 'date',
            'hire_date' => 'date',
            'termination_date' => 'date',
            'password' => 'hashed',
            'is_active_employee' => 'boolean',
            'must_change_password' => 'boolean',
            'status' => 'integer',
            'failed_login_attempts' => 'integer',
        ];
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    /**
     * Get the company the user belongs to.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Get the branch the user belongs to.
     *
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Get the department the user belongs to.
     *
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Get the position assigned to the user.
     *
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    /**
     * Get the grade assigned to the user.
     *
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    /**
     * Get the primary shift assigned to the user.
     *
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    /**
     * Get the manager of the user.
     *
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Get all shifts the user is assigned to (many-to-many).
     *
     * @return BelongsToMany<Shift, $this>
     */
    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(Shift::class, 'user_shifts', 'user_id', 'shift_id')
            ->withPivot(['effective_from', 'effective_to', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * Get the direct subordinates of the user.
     *
     * @return HasMany<User, $this>
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    // ------------------------------------------------------------------
    // Placeholder relations for future modules
    // ------------------------------------------------------------------

    /**
     * Vacations taken by the user (defined by Vacations module).
     */
    public function vacations()
    {
        return $this->hasMany(Vacation::class, 'user_id');
    }

    /**
     * Vacation requests submitted by the user.
     */
    public function vacationRequests()
    {
        return $this->hasMany(UserVacationRequest::class, 'user_id');
    }

    /**
     * Vacation balance for the user.
     */
    public function vacationBalances()
    {
        return $this->hasMany(UserVacationBalance::class, 'user_id');
    }

    /**
     * Attendance sessions belonging to the user.
     */
    public function attendanceSessions()
    {
        return $this->hasMany(AttendanceSession::class, 'user_id');
    }

    /**
     * Fingerprint templates registered for the user.
     */
    public function fingerprintTemplates()
    {
        return $this->hasMany(UserFingerprint::class, 'user_id');
    }

    /**
     * Zones assigned to the user.
     *
     * @return BelongsToMany<Zone, $this>
     */
    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(
            Zone::class,
            'user_zone',
            'user_id',
            'zone_id'
        )->withPivot('is_primary')->withTimestamps();
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    /**
     * Exclude the system super-admin from the query.
     */
    public function scopeWithoutSuperAdmin(Builder $query): Builder
    {
        return $query->where('id', '!=', self::SUPER_ADMIN_ID);
    }

    /**
     * Scope a query to only include active users.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1)->where('is_active_employee', true);
    }

    /**
     * Scope a query to only include inactive users.
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('status', '!=', 1)->orWhere('is_active_employee', false);
        });
    }

    /**
     * Scope a query to only employees (excluding the super admin).
     */
    public function scopeEmployees(Builder $query): Builder
    {
        return $query->withoutSuperAdmin();
    }

    // ------------------------------------------------------------------
    // Accessors / helpers
    // ------------------------------------------------------------------

    /**
     * Get the URL of the user's avatar.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar);
    }

    /**
     * Get the user's full name (prefers Arabic if available).
     */
    public function getFullNameAttribute(): string
    {
        if ($this->full_name_ar) {
            return $this->full_name_ar;
        }

        if ($this->first_name || $this->last_name) {
            return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
        }

        return $this->name ?? '';
    }

    /**
     * Determine if the user is the system super-admin.
     */
    public function isSuperAdmin(): bool
    {
        return (int) $this->id === self::SUPER_ADMIN_ID;
    }

    /**
     * Determine if the user is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === 1 && (bool) $this->is_active_employee;
    }

    /**
     * Determine if the account is currently locked.
     */
    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
