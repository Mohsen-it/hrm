# نظام جدولة المناوبات والدوام الدوري - خطة التنفيذ التقنية
# Employee Shift Scheduling Engine - Technical Implementation Plan

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-16
**الحالة:** مسودة

---

## ملخص التغييرات

### Technical Context

| العنصر | التفاصيل |
|--------|----------|
| الوحدة المستهدفة | Shifts, ShiftRotation, Attendance, Vacations |
| القاعدة المستهدفة | SQLite (تطوير) / MySQL 8.0+ (إنتاج) |
| إطار العمل | Laravel 13 + Vue 3 + Inertia.js |
| المعمارية | Controller → Service → Repository → Model |
| الصلاحيات | Spatie Permission |

### Constitution Check

| المادة | الحالة | ملاحظات |
|--------|--------|---------|
| II: بنية الوحدات | ✅ متوافق | سيتبع نمط Controller → Service → Repository → Model |
| III: التسمية | ✅ متوافق | Models مفرد، Controllers جمع، Migrations snake_case |
| IV: قاعدة البيانات | ✅ متوافق | Foreign Keys، Indexes، SQLite للتطوير |
| V: الأمان | ✅ متوافق | Spatie Permission، Auth middleware |
| VI: الأداء | ✅ متوافق | Eager Loading، Bulk Insert، Chunking |
| VII: المكونات | ✅ متوافق | استخدام DataTable، FormInput، FormModal، إلخ |
| XIV: التوسع | ✅ متوافق | Service Layer، Repository، Dependency Injection |

---

## ملخص التغييرات

### قاعدة البيانات

#### 1. جدول أنماط الدوام (shift_patterns)
```sql
CREATE TABLE shift_patterns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    work_days INT UNSIGNED NOT NULL,
    rest_days INT UNSIGNED NOT NULL,
    cycle_length INT UNSIGNED NOT NULL,
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### 2. جدول فئات الدوام (duty_categories)
```sql
CREATE TABLE duty_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    shift_pattern_id BIGINT UNSIGNED NOT NULL,
    cycle_start_date DATE NOT NULL,
    display_order INT UNSIGNED DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (shift_pattern_id) REFERENCES shift_patterns(id) ON DELETE RESTRICT
);
```

#### 3. جدول إسناد الموظفين (employee_shift_assignments)
```sql
CREATE TABLE employee_shift_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    duty_category_id BIGINT UNSIGNED NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    assigned_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES personnel_employee(id) ON DELETE CASCADE,
    FOREIGN KEY (duty_category_id) REFERENCES duty_categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE RESTRICT
);
```

#### 4. جدول فترة الجدول (schedule_periods)
```sql
CREATE TABLE schedule_periods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    year INT UNSIGNED NOT NULL,
    month INT UNSIGNED NOT NULL,
    schedule_period_start DATE NOT NULL,
    schedule_period_end DATE NOT NULL,
    status ENUM('draft', 'published') DEFAULT 'draft',
    generated_by BIGINT UNSIGNED NOT NULL,
    generated_at TIMESTAMP NOT NULL,
    published_by BIGINT UNSIGNED NULL,
    published_at TIMESTAMP NULL,
    schedule_version INT UNSIGNED DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (published_by) REFERENCES users(id) ON DELETE RESTRICT
);
```

#### 5. جدول سجل الجدول (schedule_entries)
```sql
CREATE TABLE schedule_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_period_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    duty_category_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    day_status ENUM('WORK', 'REST') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_period_id) REFERENCES schedule_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES personnel_employee(id) ON DELETE CASCADE,
    FOREIGN KEY (duty_category_id) REFERENCES duty_categories(id) ON DELETE RESTRICT
);
```

#### 6. جدول سجل التدقيق (audit_logs)
```sql
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE RESTRICT
);
```

### الفهارس (Indexes)

```php
// duty_categories
$table->unique(['shift_pattern_id', 'cycle_start_date']);

// employee_shift_assignments
$table->index(['employee_id', 'effective_from', 'effective_to']);
$table->index(['duty_category_id', 'effective_from']);

// schedule_periods
$table->unique(['year', 'month', 'schedule_version']);

// schedule_entries
$table->index(['schedule_period_id', 'employee_id']);
$table->index(['employee_id', 'date']);
$table->index(['duty_category_id', 'date']);

// audit_logs
$table->index(['entity_type', 'entity_id']);
$table->index(['actor_id', 'created_at']);
```

### النماذج (Models)

#### ShiftPattern
```php
namespace Modules\Shifts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftPattern extends Model
{
    protected $fillable = [
        'name_ar', 'name_en', 'code',
        'work_days', 'rest_days', 'cycle_length',
        'description', 'is_active'
    ];

    protected $casts = [
        'work_days' => 'integer',
        'rest_days' => 'integer',
        'cycle_length' => 'integer',
        'is_active' => 'boolean'
    ];

    // Relationships
    public function dutyCategories(): HasMany
    {
        return $this->hasMany(DutyCategory::class);
    }

    // Accessors
    public function getCycleLengthAttribute(): int
    {
        return $this->work_days + $this->rest_days;
    }
}
```

#### DutyCategory
```php
namespace Modules\Shifts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DutyCategory extends Model
{
    protected $fillable = [
        'name', 'code', 'shift_pattern_id',
        'cycle_start_date', 'display_order', 'is_active'
    ];

    protected $casts = [
        'cycle_start_date' => 'date',
        'display_order' => 'integer',
        'is_active' => 'boolean'
    ];

    // Relationships
    public function shiftPattern(): BelongsTo
    {
        return $this->belongsTo(ShiftPattern::class);
    }

    public function employeeAssignments(): HasMany
    {
        return $this->hasMany(EmployeeShiftAssignment::class);
    }

    public function scheduleEntries(): HasMany
    {
        return $this->hasMany(ScheduleEntry::class);
    }
}
```

#### EmployeeShiftAssignment
```php
namespace Modules\Shifts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeShiftAssignment extends Model
{
    protected $fillable = [
        'employee_id', 'duty_category_id',
        'effective_from', 'effective_to', 'assigned_by'
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date'
    ];

    // Relationships
    public function employee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Employee::class);
    }

    public function dutyCategory(): BelongsTo
    {
        return $this->belongsTo(DutyCategory::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereNull('effective_to')
            ->orWhere('effective_to', '>=', now());
    }
}
```

#### SchedulePeriod
```php
namespace Modules\Shifts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchedulePeriod extends Model
{
    protected $fillable = [
        'year', 'month', 'schedule_period_start', 'schedule_period_end',
        'status', 'generated_by', 'generated_at',
        'published_by', 'published_at', 'schedule_version'
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'schedule_period_start' => 'date',
        'schedule_period_end' => 'date',
        'generated_at' => 'timestamp',
        'published_at' => 'timestamp',
        'schedule_version' => 'integer'
    ];

    // Relationships
    public function entries(): HasMany
    {
        return $this->hasMany(ScheduleEntry::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'generated_by');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'published_by');
    }
}
```

#### ScheduleEntry
```php
namespace Modules\Shifts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleEntry extends Model
{
    protected $fillable = [
        'schedule_period_id', 'employee_id', 'duty_category_id',
        'date', 'day_status'
    ];

    protected $casts = [
        'date' => 'date'
    ];

    // Relationships
    public function schedulePeriod(): BelongsTo
    {
        return $this->belongsTo(SchedulePeriod::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Employee::class);
    }

    public function dutyCategory(): BelongsTo
    {
        return $this->belongsTo(DutyCategory::class);
    }
}
```

### الخدمات (Services)

#### ShiftPatternService
```php
namespace Modules\Shifts\Services;

use Modules\Shifts\Models\ShiftPattern;
use Modules\Shifts\Repositories\ShiftPatternRepository;

class ShiftPatternService
{
    public function __construct(
        private ShiftPatternRepository $repository
    ) {}

    public function create(array $data): ShiftPattern
    {
        $validated = $this->validateCreate($data);
        $validated['cycle_length'] = $validated['work_days'] + $validated['rest_days'];
        return $this->repository->create($validated);
    }

    public function update(ShiftPattern $pattern, array $data): ShiftPattern
    {
        $validated = $this->validateUpdate($data, $pattern);
        if (isset($validated['work_days']) || isset($validated['rest_days'])) {
            $workDays = $validated['work_days'] ?? $pattern->work_days;
            $restDays = $validated['rest_days'] ?? $pattern->rest_days;
            $validated['cycle_length'] = $workDays + $restDays;
        }
        return $this->repository->update($pattern, $validated);
    }

    public function delete(ShiftPattern $pattern): bool
    {
        if ($pattern->dutyCategories()->active()->exists()) {
            throw new \Exception('Cannot delete pattern with active categories');
        }
        return $this->repository->delete($pattern);
    }

    private function validateCreate(array $data): array
    {
        // Validation logic
    }

    private function validateUpdate(array $data, ShiftPattern $pattern): array
    {
        // Validation logic
    }
}
```

#### DutyCategoryService
```php
namespace Modules\Shifts\Services;

use Modules\Shifts\Models\DutyCategory;
use Modules\Shifts\Repositories\DutyCategoryRepository;

class DutyCategoryService
{
    public function __construct(
        private DutyCategoryRepository $repository
    ) {}

    public function create(array $data): DutyCategory
    {
        $validated = $this->validateCreate($data);
        return $this->repository->create($validated);
    }

    public function update(DutyCategory $category, array $data): DutyCategory
    {
        $validated = $this->validateUpdate($data, $category);
        return $this->repository->update($category, $validated);
    }

    public function updateCycleStartDate(
        DutyCategory $category,
        string $newStartDate,
        string $effectiveFrom
    ): DutyCategory {
        // Warn about future schedule changes
        // Update the category
        // Log the change
    }

    private function validateCreate(array $data): array
    {
        // Validation logic
    }

    private function validateUpdate(array $data, DutyCategory $category): array
    {
        // Validation logic
    }
}
```

#### EmployeeShiftAssignmentService
```php
namespace Modules\Shifts\Services;

use Modules\Shifts\Models\EmployeeShiftAssignment;
use Modules\Shifts\Repositories\EmployeeShiftAssignmentRepository;

class EmployeeShiftAssignmentService
{
    public function __construct(
        private EmployeeShiftAssignmentRepository $repository
    ) {}

    public function assign(array $data): EmployeeShiftAssignment
    {
        $validated = $this->validateAssign($data);
        return $this->repository->create($validated);
    }

    public function bulkAssign(array $employeeIds, int $categoryId, string $effectiveFrom): array
    {
        // Validate all employees
        // Create assignments in transaction
        // Log bulk operation
    }

    public function transfer(
        EmployeeShiftAssignment $current,
        int $newCategoryId,
        string $effectiveFrom
    ): EmployeeShiftAssignment {
        // Close current assignment
        // Create new assignment
        // Return new assignment
    }

    private function validateAssign(array $data): array
    {
        // Validation logic
    }
}
```

#### ScheduleGenerationService
```php
namespace Modules\Shifts\Services;

use Modules\Shifts\Models\{
    SchedulePeriod,
    ScheduleEntry,
    DutyCategory,
    EmployeeShiftAssignment
};
use Modules\Shifts\Repositories\SchedulePeriodRepository;

class ScheduleGenerationService
{
    public function __construct(
        private SchedulePeriodRepository $repository
    ) {}

    public function generate(int $year, int $month, ?int $departmentId = null): SchedulePeriod
    {
        // 1. Create SchedulePeriod (draft)
        // 2. Get all active assignments for the period
        // 3. Calculate schedule for each employee
        // 4. Bulk insert ScheduleEntries
        // 5. Return period with entries
    }

    public function calculateDayStatus(
        \Carbon\Carbon $cycleStartDate,
        int $workDays,
        int $restDays,
        \Carbon\Carbon $targetDate
    ): string {
        $cycleLength = $workDays + $restDays;
        $daysDifference = $targetDate->diffInDays($cycleStartDate);
        $cyclePosition = $daysDifference % $cycleLength;
        return $cyclePosition < $workDays ? 'WORK' : 'REST';
    }

    public function publish(SchedulePeriod $period): SchedulePeriod
    {
        // Mark as published
        // Record published_by and published_at
    }

    public function regenerate(SchedulePeriod $existing): SchedulePeriod
    {
        // Create new version
        // Generate new schedule
        // Return new period
    }
}
```

### المستودعات (Repositories)

#### ShiftPatternRepository
```php
namespace Modules\Shifts\Repositories;

use Modules\Shifts\Models\ShiftPattern;
use Illuminate\Database\Eloquent\Builder;

class ShiftPatternRepository
{
    public function query(): Builder
    {
        return ShiftPattern::query();
    }

    public function create(array $data): ShiftPattern
    {
        return ShiftPattern::create($data);
    }

    public function update(ShiftPattern $pattern, array $data): ShiftPattern
    {
        $pattern->update($data);
        return $pattern->fresh();
    }

    public function delete(ShiftPattern $pattern): bool
    {
        return $pattern->delete();
    }
}
```

#### DutyCategoryRepository
```php
namespace Modules\Shifts\Repositories;

use Modules\Shifts\Models\DutyCategory;
use Illuminate\Database\Eloquent\Builder;

class DutyCategoryRepository
{
    public function query(): Builder
    {
        return DutyCategory::query();
    }

    public function create(array $data): DutyCategory
    {
        return DutyCategory::create($data);
    }

    public function update(DutyCategory $category, array $data): DutyCategory
    {
        $category->update($data);
        return $category->fresh();
    }

    public function delete(DutyCategory $category): bool
    {
        return $category->delete();
    }
}
```

#### EmployeeShiftAssignmentRepository
```php
namespace Modules\Shifts\Repositories;

use Modules\Shifts\Models\EmployeeShiftAssignment;
use Illuminate\Database\Eloquent\Builder;

class EmployeeShiftAssignmentRepository
{
    public function query(): Builder
    {
        return EmployeeShiftAssignment::query();
    }

    public function create(array $data): EmployeeShiftAssignment
    {
        return EmployeeShiftAssignment::create($data);
    }

    public function getActiveForEmployee(int $employeeId, \Carbon\Carbon $date): ?EmployeeShiftAssignment
    {
        return $this->query()
            ->where('employee_id', $employeeId)
            ->where('effective_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            })
            ->first();
    }

    public function getActiveForPeriod(\Carbon\Carbon $start, \Carbon\Carbon $end): Builder
    {
        return $this->query()
            ->where('effective_from', '<=', $end)
            ->where(function ($query) use ($start) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $start);
            });
    }
}
```

#### SchedulePeriodRepository
```php
namespace Modules\Shifts\Repositories;

use Modules\Shifts\Models\SchedulePeriod;
use Illuminate\Database\Eloquent\Builder;

class SchedulePeriodRepository
{
    public function query(): Builder
    {
        return SchedulePeriod::query();
    }

    public function create(array $data): SchedulePeriod
    {
        return SchedulePeriod::create($data);
    }

    public function update(SchedulePeriod $period, array $data): SchedulePeriod
    {
        $period->update($data);
        return $period->fresh();
    }

    public function delete(SchedulePeriod $period): bool
    {
        return $period->delete();
    }
}
```

### التحكمات (Controllers)

#### ShiftPatternsController
```php
namespace Modules\Shifts\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Shifts\Services\ShiftPatternService;
use Modules\Shifts\Http\Requests\{
    StoreShiftPatternRequest,
    UpdateShiftPatternRequest
};
use Inertia\Inertia;

class ShiftPatternsController extends Controller
{
    public function __construct(
        private ShiftPatternService $service
    ) {}

    public function index()
    {
        $this->authorize('view-shift-patterns');
        return Inertia::render('Shifts/Patterns/Index', [
            'patterns' => fn() => $this->service->getAll(request())
        ]);
    }

    public function create()
    {
        $this->authorize('create-shift-patterns');
        return Inertia::render('Shifts/Patterns/Create');
    }

    public function store(StoreShiftPatternRequest $request)
    {
        $this->authorize('create-shift-patterns');
        $pattern = $this->service->create($request->validated());
        return redirect()->route('shift-patterns.index')
            ->with('success', __('shifts.pattern_created'));
    }

    public function edit($id)
    {
        $this->authorize('edit-shift-patterns');
        $pattern = $this->service->findById($id);
        return Inertia::render('Shifts/Patterns/Edit', [
            'pattern' => $pattern
        ]);
    }

    public function update(UpdateShiftPatternRequest $request, $id)
    {
        $this->authorize('edit-shift-patterns');
        $pattern = $this->service->findById($id);
        $this->service->update($pattern, $request->validated());
        return redirect()->route('shift-patterns.index')
            ->with('success', __('shifts.pattern_updated'));
    }

    public function destroy($id)
    {
        $this->authorize('delete-shift-patterns');
        $pattern = $this->service->findById($id);
        $this->service->delete($pattern);
        return redirect()->route('shift-patterns.index')
            ->with('success', __('shifts.pattern_deleted'));
    }
}
```

#### DutyCategoriesController
```php
namespace Modules\Shifts\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Shifts\Services\DutyCategoryService;
use Modules\Shifts\Http\Requests\{
    StoreDutyCategoryRequest,
    UpdateDutyCategoryRequest,
    UpdateCycleStartDateRequest
};
use Inertia\Inertia;

class DutyCategoriesController extends Controller
{
    public function __construct(
        private DutyCategoryService $service
    ) {}

    public function index()
    {
        $this->authorize('view-duty-categories');
        return Inertia::render('Shifts/Categories/Index', [
            'categories' => fn() => $this->service->getAll(request()),
            'patterns' => fn() => $this->service->getShiftPatterns()
        ]);
    }

    public function create()
    {
        $this->authorize('create-duty-categories');
        return Inertia::render('Shifts/Categories/Create', [
            'patterns' => fn() => $this->service->getShiftPatterns()
        ]);
    }

    public function store(StoreDutyCategoryRequest $request)
    {
        $this->authorize('create-duty-categories');
        $category = $this->service->create($request->validated());
        return redirect()->route('duty-categories.index')
            ->with('success', __('shifts.category_created'));
    }

    public function edit($id)
    {
        $this->authorize('edit-duty-categories');
        $category = $this->service->findById($id);
        return Inertia::render('Shifts/Categories/Edit', [
            'category' => $category,
            'patterns' => fn() => $this->service->getShiftPatterns()
        ]);
    }

    public function update(UpdateDutyCategoryRequest $request, $id)
    {
        $this->authorize('edit-duty-categories');
        $category = $this->service->findById($id);
        $this->service->update($category, $request->validated());
        return redirect()->route('duty-categories.index')
            ->with('success', __('shifts.category_updated'));
    }

    public function updateCycleStartDate(UpdateCycleStartDateRequest $request, $id)
    {
        $this->authorize('change-category-start-date');
        $category = $this->service->findById($id);
        $this->service->updateCycleStartDate(
            $category,
            $request->cycle_start_date,
            $request->effective_from
        );
        return redirect()->route('duty-categories.index')
            ->with('success', __('shifts.cycle_start_date_updated'));
    }
}
```

#### EmployeeShiftAssignmentsController
```php
namespace Modules\Shifts\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Shifts\Services\EmployeeShiftAssignmentService;
use Modules\Shifts\Http\Requests\{
    StoreAssignmentRequest,
    BulkAssignRequest,
    TransferAssignmentRequest
};
use Inertia\Inertia;

class EmployeeShiftAssignmentsController extends Controller
{
    public function __construct(
        private EmployeeShiftAssignmentService $service
    ) {}

    public function index()
    {
        $this->authorize('view-employees');
        return Inertia::render('Shifts/Assignments/Index', [
            'assignments' => fn() => $this->service->getAll(request())
        ]);
    }

    public function store(StoreAssignmentRequest $request)
    {
        $this->authorize('assign-employees-to-category');
        $assignment = $this->service->assign($request->validated());
        return redirect()->route('assignments.index')
            ->with('success', __('shifts.assignment_created'));
    }

    public function bulkAssign(BulkAssignRequest $request)
    {
        $this->authorize('bulk-assign-employees');
        $this->service->bulkAssign(
            $request->employee_ids,
            $request->duty_category_id,
            $request->effective_from
        );
        return redirect()->route('assignments.index')
            ->with('success', __('shifts.bulk_assignment_completed'));
    }

    public function transfer(TransferAssignmentRequest $request, $id)
    {
        $this->authorize('assign-employees-to-category');
        $current = $this->service->findById($id);
        $this->service->transfer(
            $current,
            $request->duty_category_id,
            $request->effective_from
        );
        return redirect()->route('assignments.index')
            ->with('success', __('shifts.assignment_transferred'));
    }
}
```

#### SchedulesController
```php
namespace Modules\Shifts\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Shifts\Services\ScheduleGenerationService;
use Modules\Shifts\Http\Requests\{
    GenerateScheduleRequest,
    PublishScheduleRequest
};
use Inertia\Inertia;

class SchedulesController extends Controller
{
    public function __construct(
        private ScheduleGenerationService $service
    ) {}

    public function index()
    {
        $this->authorize('view-shift-patterns');
        return Inertia::render('Shifts/Schedules/Index', [
            'periods' => fn() => $this->service->getAllPeriods(request())
        ]);
    }

    public function generate(GenerateScheduleRequest $request)
    {
        $this->authorize('generate-schedule');
        $period = $this->service->generate(
            $request->year,
            $request->month,
            $request->department_id
        );
        return redirect()->route('schedules.show', $period->id)
            ->with('success', __('shifts.schedule_generated'));
    }

    public function show($id)
    {
        $this->authorize('view-shift-patterns');
        $period = $this->service->findPeriodById($id);
        return Inertia::render('Shifts/Schedules/Show', [
            'period' => $period,
            'entries' => fn() => $period->entries()->with('employee', 'dutyCategory')->get()
        ]);
    }

    public function publish(PublishScheduleRequest $request, $id)
    {
        $this->authorize('publish-schedule');
        $period = $this->service->findPeriodById($id);
        $this->service->publish($period);
        return redirect()->route('schedules.show', $period->id)
            ->with('success', __('shifts.schedule_published'));
    }

    public function regenerate($id)
    {
        $this->authorize('regenerate-schedule');
        $existing = $this->service->findPeriodById($id);
        $newPeriod = $this->service->regenerate($existing);
        return redirect()->route('schedules.show', $newPeriod->id)
            ->with('success', __('shifts.schedule_regenerated'));
    }
}
```

### المسارات (Routes)

```php
// Modules/Shifts/routes/web.php

use Modules\Shifts\Http\Controllers\{
    ShiftPatternsController,
    DutyCategoriesController,
    EmployeeShiftAssignmentsController,
    SchedulesController
};

Route::middleware(['auth', 'verified'])->prefix('shifts')->name('shifts.')->group(function () {
    // Shift Patterns
    Route::resource('patterns', ShiftPatternsController::class)
        ->middleware(['permission:view-shift-patterns|create-shift-patterns|edit-shift-patterns|delete-shift-patterns']);

    // Duty Categories
    Route::resource('categories', DutyCategoriesController::class)
        ->middleware(['permission:view-duty-categories|create-duty-categories|edit-duty-categories']);
    Route::put('categories/{id}/cycle-start-date', [DutyCategoriesController::class, 'updateCycleStartDate'])
        ->name('categories.update-cycle-start-date')
        ->middleware('permission:change-category-start-date');

    // Employee Assignments
    Route::resource('assignments', EmployeeShiftAssignmentsController::class)
        ->only(['index', 'store'])
        ->middleware(['permission:view-employees|assign-employees-to-category']);
    Route::post('assignments/bulk', [EmployeeShiftAssignmentsController::class, 'bulkAssign'])
        ->name('assignments.bulk')
        ->middleware('permission:bulk-assign-employees');
    Route::put('assignments/{id}/transfer', [EmployeeShiftAssignmentsController::class, 'transfer'])
        ->name('assignments.transfer')
        ->middleware('permission:assign-employees-to-category');

    // Schedules
    Route::resource('schedules', SchedulesController::class)
        ->only(['index', 'show'])
        ->middleware(['permission:view-shift-patterns']);
    Route::post('schedules/generate', [SchedulesController::class, 'generate'])
        ->name('schedules.generate')
        ->middleware('permission:generate-schedule');
    Route::put('schedules/{id}/publish', [SchedulesController::class, 'publish'])
        ->name('schedules.publish')
        ->middleware('permission:publish-schedule');
    Route::put('schedules/{id}/regenerate', [SchedulesController::class, 'regenerate'])
        ->name('schedules.regenerate')
        ->middleware('permission:regenerate-schedule');
});
```

### القوالب (Views)

#### Vue Pages Structure
```
resources/js/Pages/Shifts/
├── Patterns/
│   ├── Index.vue
│   ├── Create.vue
│   └── Edit.vue
├── Categories/
│   ├── Index.vue
│   ├── Create.vue
│   └── Edit.vue
├── Assignments/
│   ├── Index.vue
│   └── BulkAssign.vue
└── Schedules/
    ├── Index.vue
    └── Show.vue
```

#### Component Examples

**Patterns/Index.vue**
```vue
<script setup>
import { useTranslations } from '@/composables/useTranslations'
import DataTable from '@/Components/ui/DataTable.vue'
import PageHeader from '@/Components/ui/PageHeader.vue'

const { t } = useTranslations()

defineProps({
    patterns: { type: Array, required: true }
})

const columns = [
    { key: 'name_ar', label: t('shifts.name_ar') },
    { key: 'name_en', label: t('shifts.name_en') },
    { key: 'code', label: t('shifts.code') },
    { key: 'work_days', label: t('shifts.work_days') },
    { key: 'rest_days', label: t('shifts.rest_days') },
    { key: 'cycle_length', label: t('shifts.cycle_length') },
    { key: 'is_active', label: t('shifts.status') }
]
</script>

<template>
    <div dir="rtl">
        <PageHeader :title="t('shifts.patterns')">
            <template #actions>
                <Link :href="route('shift-patterns.create')">
                    {{ t('shifts.create_pattern') }}
                </Link>
            </template>
        </PageHeader>
        <DataTable :columns="columns" :data="patterns" />
    </div>
</template>
```

**Schedules/Show.vue**
```vue
<script setup>
import { useTranslations } from '@/composables/useTranslations'
import PageHeader from '@/Components/ui/PageHeader.vue'

const { t } = useTranslations()

defineProps({
    period: { type: Object, required: true },
    entries: { type: Array, required: true }
})
</script>

<template>
    <div dir="rtl">
        <PageHeader :title="t('shifts.schedule_for_period', { month: period.month, year: period.year })">
            <template #actions>
                <button v-if="period.status === 'draft'" @click="publishSchedule">
                    {{ t('shifts.publish_schedule') }}
                </button>
                <button @click="regenerateSchedule">
                    {{ t('shifts.regenerate') }}
                </button>
            </template>
        </PageHeader>
        <!-- Calendar view here -->
    </div>
</template>
```

### الشيفرة المكررة (Seeders)

```php
// Modules/Shifts/database/seeders/ShiftPatternSeeder.php

use Modules\Shifts\Models\ShiftPattern;

class ShiftPatternSeeder
{
    public function run(): void
    {
        // Pattern 1: 1 Day Work / 3 Days Off
        ShiftPattern::create([
            'name_ar' => 'يوم دوام واحد / 3 أيام راحة',
            'name_en' => '1 Day Work / 3 Days Off',
            'code' => '1W3R',
            'work_days' => 1,
            'rest_days' => 3,
            'cycle_length' => 4,
            'is_active' => true
        ]);

        // Pattern 2: 7 Days Work / 21 Days Off
        ShiftPattern::create([
            'name_ar' => '7 أيام دوام / 21 يوم راحة',
            'name_en' => '7 Days Work / 21 Days Off',
            'code' => '7W21R',
            'work_days' => 7,
            'rest_days' => 21,
            'cycle_length' => 28,
            'is_active' => true
        ]);

        // Pattern 3: 3 Days Work / 9 Days Off
        ShiftPattern::create([
            'name_ar' => '3 أيام دوام / 9 أيام راحة',
            'name_en' => '3 Days Work / 9 Days Off',
            'code' => '3W9R',
            'work_days' => 3,
            'rest_days' => 9,
            'cycle_length' => 12,
            'is_active' => true
        ]);

        // Pattern 4: 5 Days Work / 2 Days Off
        ShiftPattern::create([
            'name_ar' => '5 أيام دوام / يوما عطلة',
            'name_en' => '5 Days Work / 2 Days Off',
            'code' => '5W2R',
            'work_days' => 5,
            'rest_days' => 2,
            'cycle_length' => 7,
            'is_active' => true
        ]);
    }
}
```

---

## ترتيب التنفيذ

1. **Migration** - إنشاء الجداول الجديدة
2. **Model** - إنشاء النماذج مع العلاقات والأوسمة
3. **Repository** - إنشاء المستودعات
4. **Service** - إنشاء الخدمات مع منطق الأعمال
5. **Controller** - إنشاء التحكمات مع الصلاحيات
6. **Routes** - إضافة المسارات
7. **Views** - إنشاء صفحات Vue
8. **Translation** - إضافة الترجمات العربية والإنجليزية
9. **Seeder** - إضافة البيانات الأولية
10. **Tests** - كتابة الاختبارات

---

## الاعتبارات

### الأمان
- Spatie Permission لكل العمليات
- Auth middleware على جميع المسارات
- التحقق من الصلاحيات في Controller

### الأداء
- Eager Loading لجميع العلاقات
- Bulk Insert للسجلات الكبيرة
- Chunking للبيانات الكبيرة
- لا N+1 queries

### اللغة
- ترجمة عربية وإنجليزية
- RTL دعم كامل
- استخدام useTranslations() composable

### RTL
- dir="rtl" على جميع المكونات
- استخدام المكونات المشتركة المدعومة

---

## الملفات المعدلة

### Backend Files
| الملف | الوصف |
|-------|-------|
| `Modules/Shifts/Models/ShiftPattern.php` | نموذج نمط الدوام |
| `Modules/Shifts/Models/DutyCategory.php` | نموذج فئة الدوام |
| `Modules/Shifts/Models/EmployeeShiftAssignment.php` | نموذج إسناد الموظف |
| `Modules/Shifts/Models/SchedulePeriod.php` | نموذج فترة الجدول |
| `Modules/Shifts/Models/ScheduleEntry.php` | نموذج سجل الجدول |
| `Modules/Shifts/Services/ShiftPatternService.php` | خدمة أنماط الدوام |
| `Modules/Shifts/Services/DutyCategoryService.php` | خدمة فئات الدوام |
| `Modules/Shifts/Services/EmployeeShiftAssignmentService.php` | خدمة إسناد الموظفين |
| `Modules/Shifts/Services/ScheduleGenerationService.php` | خدمة توليد الجدول |
| `Modules/Shifts/Repositories/ShiftPatternRepository.php` | مستودع أنماط الدوام |
| `Modules/Shifts/Repositories/DutyCategoryRepository.php` | مستودع فئات الدوام |
| `Modules/Shifts/Repositories/EmployeeShiftAssignmentRepository.php` | مستودع إسناد الموظفين |
| `Modules/Shifts/Repositories/SchedulePeriodRepository.php` | مستودع فترة الجدول |
| `Modules/Shifts/Http/Controllers/ShiftPatternsController.php` | تحكم أنماط الدوام |
| `Modules/Shifts/Http/Controllers/DutyCategoriesController.php` | تحكم فئات الدوام |
| `Modules/Shifts/Http/Controllers/EmployeeShiftAssignmentsController.php` | تحكم إسناد الموظفين |
| `Modules/Shifts/Http/Controllers/SchedulesController.php` | تحكم الجداول |
| `Modules/Shifts/routes/web.php` | المسارات |
| `Modules/Shifts/database/migrations/...` | الترحيلات |

### Frontend Files
| الملف | الوصف |
|-------|-------|
| `resources/js/Pages/Shifts/Patterns/Index.vue` | قائمة أنماط الدوام |
| `resources/js/Pages/Shifts/Patterns/Create.vue` | إنشاء نمط دوام |
| `resources/js/Pages/Shifts/Patterns/Edit.vue` | تعديل نمط دوام |
| `resources/js/Pages/Shifts/Categories/Index.vue` | قائمة فئات الدوام |
| `resources/js/Pages/Shifts/Categories/Create.vue` | إنشاء فئة دوام |
| `resources/js/Pages/Shifts/Categories/Edit.vue` | تعديل فئة دوام |
| `resources/js/Pages/Shifts/Assignments/Index.vue` | قائمة إسناد الموظفين |
| `resources/js/Pages/Shifts/Assignments/BulkAssign.vue` | الإسناد الجماعي |
| `resources/js/Pages/Shifts/Schedules/Index.vue` | قائمة الجداول |
| `resources/js/Pages/Shifts/Schedules/Show.vue` | عرض الجدول |

---

*آخر تحديث: 2026-07-16*
