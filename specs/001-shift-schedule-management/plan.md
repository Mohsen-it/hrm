# إدارة فئات النوبات وجداول الوقت - خطة التنفيذ التقنية

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-15
**الوحدة:** `Modules/Shifts`
**الاعتماد على:** [spec.md](spec.md) | [research.md](research.md) | [data-model.md](data-model.md)

---

## Technical Context

| البند | القيمة |
|-------|--------|
| **Framework** | Laravel 13 |
| **Architecture** | nwidart/laravel-modules |
| **Database** | MySQL 8.0+ (prod) / SQLite (dev) |
| **Frontend** | Vue 3 + Inertia.js + Tailwind CSS 4.3 |
| **Language** | PHP 8.3+ |
| **Module** | `Modules/Shifts` (extend existing) |
| **New Tables** | 6 tables (`att_` prefix) |
| **Permissions** | 10 new permissions |

---

## Constitution Check

### Architecture Compliance

| Rule | Status | Notes |
|------|--------|-------|
| Controller → Service → Repository → Model | ✅ | All new code follows this pattern |
| No direct Model in Controller | ✅ | Controllers delegate to Services |
| Validation in Service layer | ✅ | Validated in ShiftCategoryService, TimeScheduleService |
| Relationships in Model | ✅ | Defined in Eloquent models |
| No `app()`/`resolve()` in Services | ✅ | Constructor DI only |
| Eager loading | ✅ | `with(['timeSchedule', 'employees'])` for all list queries |
| Pagination | ✅ | All index endpoints paginated |
| Caching | ✅ | Tags: `['shift_categories', 'time_schedules', 'employee_assignments']` |
| Queue for heavy ops | ✅ | Hours tracking calculation via queued job |

### UI Component Compliance

| Rule | Status | Notes |
|------|--------|-------|
| `<DataTable />` for all tables | ✅ | Used in ShiftCategories, TimeSchedules, Assignments index pages |
| `<FormModal />` for modals | ✅ | Create/Edit forms use FormModal |
| `<FormInput />` for inputs | ✅ | All form fields use shared components |
| `<FormSelect />` for dropdowns | ✅ | Type selector, period selector |
| `<ConfirmDialog />` for deletes | ✅ | Category/schedule deletion |
| `<PageHeader />` for page headers | ✅ | All pages |
| RTL support | ✅ | `dir="rtl"` default |
| Bilingual | ✅ | AR + EN translation files |

### Performance Gates

| Metric | Target | Verification |
|--------|--------|-------------|
| Calendar load (30-day view) | < 500ms | AC6 |
| Absence report (200 employees) | < 3s | AC6 |
| DB queries per page | < 10 | Eager loading + caching |
| N+1 queries | 0 | `with()` on all list queries |

### Gate Evaluation

- [x] All mandatory architecture rules followed
- [x] All UI component rules followed
- [x] Performance targets defined and measurable
- [x] No constitution violations

---

## Database Migrations

### Migration Order

| # | Migration | Table | Module |
|---|-----------|-------|--------|
| 1 | `create_att_shift_categories_table` | `att_shift_categories` | Shifts |
| 2 | `create_att_time_schedules_table` | `att_time_schedules` | Shifts |
| 3 | `create_att_time_schedule_breaks_table` | `att_time_schedule_breaks` | Shifts |
| 4 | `create_att_category_time_schedule_table` | `att_category_time_schedule` | Shifts |
| 5 | `create_att_employee_shift_categories_table` | `att_employee_shift_categories` | Shifts |
| 6 | `create_att_hours_tracking_table` | `att_hours_tracking` | Shifts |

Full schema details: [data-model.md](data-model.md)

---

## Models

### New Models (`Modules/Shifts/app/Models/`)

| Model | Table | Key Relationships |
|-------|-------|-------------------|
| `ShiftCategory` | `att_shift_categories` | BelongsTo Company, HasOne CategoryTimeSchedule, HasMany EmployeeShiftCategory |
| `TimeSchedule` | `att_time_schedules` | BelongsTo Company, HasMany TimeScheduleBreak, HasOne CategoryTimeSchedule |
| `TimeScheduleBreak` | `att_time_schedule_breaks` | BelongsTo TimeSchedule |
| `CategoryTimeSchedule` | `att_category_time_schedule` | BelongsTo ShiftCategory, BelongsTo TimeSchedule |
| `EmployeeShiftCategory` | `att_employee_shift_categories` | BelongsTo Employee (personnel_employee), BelongsTo ShiftCategory |
| `HoursTracking` | `att_hours_tracking` | BelongsTo Employee, BelongsTo ShiftCategory |

### Model Requirements

Each model must include:
- `$fillable` or `$guarded` property
- `$casts` for JSON, date, boolean fields
- Relationship methods with proper return types
- Relevant scopes (e.g., `scopeActive()`, `scopeByCompany()`)

**Example — ShiftCategory casts**:
```php
protected $casts = [
    'type' => ShiftCategoryType::class, // Enum
    'work_days_json' => 'array',
    'weekend_days_json' => 'array',
    'overtime_enabled' => 'boolean',
    'fingerprint_enabled' => 'boolean',
    'work_on_holidays' => 'boolean',
    'work_on_weekends' => 'boolean',
];
```

---

## Repositories

### New Repositories (`Modules/Shifts/app/Repositories/`)

| Repository | Responsibility |
|------------|---------------|
| `ShiftCategoryRepository` | CRUD + filtering by type/company, active employee check before delete |
| `TimeScheduleRepository` | CRUD + duplicate check, linked category check before delete |
| `EmployeeShiftCategoryRepository` | Assignment CRUD, overlap detection, active assignment lookup, bulk assign |
| `HoursTrackingRepository` | Period calculation, upsert tracking records |

---

## Services

### New Services (`Modules/Shifts/app/Services/`)

| Service | Key Methods | Dependencies |
|---------|-------------|-------------|
| `ShiftCategoryService` | `create()`, `update()`, `delete()`, `getAll()`, `getById()` | ShiftCategoryRepository, Validation |
| `TimeScheduleService` | `create()`, `update()`, `delete()`, `getAll()`, `copy()` | TimeScheduleRepository, Validation |
| `ShiftCategoryAssignmentService` | `assignEmployee()`, `bulkAssign()`, `transferEmployee()`, `getActiveAssignment()`, `closeAssignment()` | EmployeeShiftCategoryRepository, ShiftCategoryRepository |
| `CyclicScheduleCalculator` | `isWorkDay(date, startDate, workDays, restDays)`, `getWorkDays(month, year, startDate, workDays, restDays)`, `getNextWorkDay(date)` | — (pure computation) |
| `AbsenceCalculationService` | `getExpectedEmployees(date)`, `getAbsentEmployees(date)`, `getMonthlyAbsence(employee, month, year)` | EmployeeShiftCategoryRepository, CyclicScheduleCalculator, Attendance data |
| `HoursTrackingService` | `calculatePeriodHours(employee, periodStart, periodEnd)`, `getDeficitReport()` | HoursTrackingRepository, iclock_transaction data |

### Validation Services

| Service | Responsibility |
|---------|---------------|
| `ShiftCategoryValidationService` | Validate category create/update rules (type-specific rules, uniqueness) |
| `TimeScheduleValidationService` | Validate schedule create/update rules (margin >= 0) |
| `AssignmentValidationService` | Validate assignment rules (no overlap, employee active, category exists) |

---

## Controllers

### New Controllers (`Modules/Shifts/app/Http/Controllers/`)

| Controller | Methods | Route Pattern |
|------------|---------|---------------|
| `ShiftCategoriesController` | `index`, `create`, `store`, `edit`, `update`, `destroy` | Resource |
| `TimeSchedulesController` | `index`, `create`, `store`, `edit`, `update`, `destroy`, `copy` | Resource + copy |
| `ShiftCategoryAssignmentController` | `index`, `assign`, `bulkAssign`, `transfer`, `unassign` | Custom |
| `ScheduleCalendarController` | `employee(mployeeId, ?month, ?year)`, `department(departmentId, ?date)` | Custom |
| `SmartAbsenceController` | `daily(date, ?department)`, `monthly(employee, month, year)` | Custom |

### Controller Method Signatures

```php
// ShiftCategoriesController
class ShiftCategoriesController extends Controller
{
    public function __construct(
        private ShiftCategoryService $shiftCategoryService
    ) {}

    public function index()
    {
        $this->authorize('view-shift-categories');
        return Inertia::render('Shifts/ShiftCategories/Index', [
            'categories' => fn() => ShiftCategoryResource::collection(
                $this->shiftCategoryService->getAll(request())
            ),
        ]);
    }

    public function store(StoreShiftCategoryRequest $request)
    {
        $this->authorize('create-shift-categories');
        $category = $this->shiftCategoryService->create($request->validated());
        return redirect()->route('shift-categories.index')
            ->with('success', __('shifts.category_created'));
    }
    // ...
}
```

---

## Routes

### `Modules/Shifts/routes/web.php`

```php
use Modules\Shifts\Http\Controllers\{
    ShiftCategoriesController,
    TimeSchedulesController,
    ShiftCategoryAssignmentController,
    ScheduleCalendarController,
    SmartAbsenceController,
};

Route::middleware(['auth', 'web'])->group(function () {
    // Shift Categories
    Route::resource('shift-categories', ShiftCategoriesController::class)
        ->middleware('permission:view-shift-categories');

    // Time Schedules
    Route::resource('time-schedules', TimeSchedulesController::class)
        ->middleware('permission:view-time-schedules');
    Route::post('time-schedules/{id}/copy', [TimeSchedulesController::class, 'copy'])
        ->middleware('permission:create-time-schedules')
        ->name('time-schedules.copy');

    // Assignments
    Route::get('shift-assignments', [ShiftCategoryAssignmentController::class, 'index'])
        ->middleware('permission:view-shift-categories')
        ->name('shift-assignments.index');
    Route::post('shift-assignments/assign', [ShiftCategoryAssignmentController::class, 'assign'])
        ->middleware('permission:assign-employees-to-category')
        ->name('shift-assignments.assign');
    Route::post('shift-assignments/bulk-assign', [ShiftCategoryAssignmentController::class, 'bulkAssign'])
        ->middleware('permission:assign-employees-to-category')
        ->name('shift-assignments.bulk-assign');
    Route::post('shift-assignments/transfer', [ShiftCategoryAssignmentController::class, 'transfer'])
        ->middleware('permission:assign-employees-to-category')
        ->name('shift-assignments.transfer');

    // Calendar
    Route::get('schedule-calendar/{employee}', [ScheduleCalendarController::class, 'employee'])
        ->middleware('permission:view-shift-categories')
        ->name('schedule-calendar.employee');
    Route::get('schedule-calendar/department/{department}', [ScheduleCalendarController::class, 'department'])
        ->middleware('permission:view-shift-categories')
        ->name('schedule-calendar.department');

    // Smart Absence
    Route::get('smart-absence/daily', [SmartAbsenceController::class, 'daily'])
        ->middleware('permission:view-attendance-by-schedule')
        ->name('smart-absence.daily');
    Route::get('smart-absence/monthly/{employee}', [SmartAbsenceController::class, 'monthly'])
        ->middleware('permission:view-attendance-by-schedule')
        ->name('smart-absence.monthly');
});
```

---

## Form Requests

### New Form Requests (`Modules/Shifts/app/Http/Requests/`)

| FormRequest | Controller Method | Key Rules |
|-------------|-------------------|-----------|
| `StoreShiftCategoryRequest` | `store` | name required, type required, type-specific rules |
| `UpdateShiftCategoryRequest` | `update` | Same as store + ignore current ID for unique |
| `StoreTimeScheduleRequest` | `store` | name required, in_time/out_time required, margins >= 0 |
| `UpdateTimeScheduleRequest` | `update` | Same as store + ignore current ID |
| `AssignEmployeeRequest` | `assign` | employee_id required, shift_category_id required, start_date required |
| `BulkAssignRequest` | `bulkAssign` | employee_ids required (array), shift_category_id required, start_date required |
| `TransferEmployeeRequest` | `transfer` | employee_id, new_category_id, effective_date required |

---

## API Resources

### New Resources (`Modules/Shifts/app/Http/Resources/`)

| Resource | Model | Key Fields |
|----------|-------|------------|
| `ShiftCategoryResource` | `ShiftCategory` | id, name, type, work_days, rest_days, work_days_json, timeSchedule, settings |
| `TimeScheduleResource` | `TimeSchedule` | id, name, in_time, out_time, is_multi_day, margins, breaks |
| `EmployeeShiftCategoryResource` | `EmployeeShiftCategory` | id, employee, category, start_date, end_date, is_active |
| `ScheduleCalendarResource` | (computed) | date, day_name, status (work/rest/holiday/absent), in_time, out_time |

---

## Frontend Pages

### Vue Pages (`Modules/Shifts/Resources/js/Pages/`)

```
Shifts/
├── Partials/
│   ├── ShiftCategoryForm.vue       ← Shared create/edit form
│   ├── TimeScheduleForm.vue        ← Shared create/edit form
│   ├── CyclicDaysDisplay.vue       ← Visual cycle pattern
│   └── CalendarLegend.vue          ← Color legend for calendar
├── ShiftCategories/
│   ├── Index.vue                   ← DataTable list
│   ├── Create.vue                  ← Form for new category
│   ├── Edit.vue                    ← Form for edit (reuses ShiftCategoryForm)
│   └── Show.vue                    ← Detail view with linked employees
├── TimeSchedules/
│   ├── Index.vue                   ← DataTable list
│   ├── Create.vue                  ← Form for new schedule
│   ├── Edit.vue                    ← Form for edit (reuses TimeScheduleForm)
│   └── Show.vue                    ← Detail view
├── Assignments/
│   ├── Index.vue                   ← DataTable of all assignments
│   ├── Assign.vue                  ← Single employee assignment form
│   └── BulkAssign.vue              ← Multi-employee assignment form
├── Calendar/
│   └── EmployeeCalendar.vue        ← 30-day calendar grid view
└── Absence/
    └── SmartAbsenceReport.vue       ← Daily/monthly absence filtered by schedule
```

### Composable: `useCyclicCalendar.js`

```javascript
// Shared composable for cyclic pattern calculations on the frontend
export function useCyclicCalendar(startDate, workDays, restDays) {
    const isWorkDay = (date) => {
        const start = new Date(startDate);
        const diffDays = Math.floor((date - start) / (1000 * 60 * 60 * 24));
        const dayInCycle = diffDays % (workDays + restDays);
        return dayInCycle < workDays;
    };

    const getMonthDays = (year, month) => {
        // Returns array of { date, dayName, isWorkDay, status }
    };

    return { isWorkDay, getMonthDays };
}
```

---

## Translations

### New Translation Files

| File | Language | Path |
|------|----------|------|
| `shifts.php` | Arabic | `Modules/Shifts/resources/lang/ar/shifts.php` |
| `shifts.php` | English | `Modules/Shifts/resources/lang/en/shifts.php` |

**Key translation keys**:
```php
// ar/shifts.php
return [
    'shift_categories' => 'فئات النوبات',
    'category_name' => 'اسم الفئة',
    'category_type' => 'نوع الفئة',
    'cyclic' => 'دوام دوري',
    'weekly' => 'دوام أسبوعي ثابت',
    'hours' => 'دوام بعدد ساعات',
    'work_days' => 'أيام الدوام',
    'rest_days' => 'أيام الراحة',
    'category_created' => 'تم إنشاء فئة النوبة بنجاح',
    'category_updated' => 'تم تحديث فئة النوبة بنجاح',
    'category_deleted' => 'تم حذف فئة النوبة بنجاح',
    'cannot_delete_active' => 'لا يمكن حذف فئة مرتبطة بموظفين نشطين',
    // ... (full keys in implementation)
];
```

---

## Permissions & Seeder

### `Modules/Shifts/database/seeders/ShiftCategoryPermissionSeeder.php`

Seeds 10 permissions into Spatie:
```
view-shift-categories
create-shift-categories
edit-shift-categories
delete-shift-categories
view-time-schedules
create-time-schedules
edit-time-schedules
delete-time-schedules
assign-employees-to-category
view-attendance-by-schedule
```

---

## Implementation Order

| Step | Artifact | Priority |
|------|----------|----------|
| 1 | Migrations (6 tables) | HIGH |
| 2 | Models (6 + relationships + casts) | HIGH |
| 3 | Repositories (4) | HIGH |
| 4 | Validation Services (3) | HIGH |
| 5 | Business Services (6) | HIGH |
| 6 | CyclicScheduleCalculator | HIGH |
| 7 | Form Requests (7) | MEDIUM |
| 8 | API Resources (4) | MEDIUM |
| 9 | Controllers (5) | MEDIUM |
| 10 | Routes | MEDIUM |
| 11 | Translations (AR + EN) | MEDIUM |
| 12 | Permission Seeder | MEDIUM |
| 13 | Vue Pages (8) | MEDIUM |
| 14 | Vue Partials (4) | LOW |
| 15 | Tests (Feature + Unit) | FINAL |

---

## Tests

### Unit Tests

| Test | Target |
|------|--------|
| `CyclicScheduleCalculatorTest` | `isWorkDay()`, `getWorkDays()`, edge cases (leap year, start=end of month) |
| `ShiftCategoryServiceTest` | Create/update/delete with validation |
| `AssignmentValidationServiceTest` | Overlap detection, active employee check |

### Feature Tests

| Test | Endpoint |
|------|----------|
| `ShiftCategoriesControllerTest` | Index, store, update, destroy, permission gates |
| `TimeSchedulesControllerTest` | Index, store, update, destroy |
| `SmartAbsenceControllerTest` | Daily report filters by schedule, monthly aggregation |
| `ScheduleCalendarControllerTest` | Calendar returns correct work/rest days across months |

### Browser Tests (Optional)

- Calendar visual verification
- Assignment form interaction

---

## References

| Document | Path |
|----------|------|
| Feature Spec | [spec.md](spec.md) |
| Research & Decisions | [research.md](research.md) |
| Data Model | [data-model.md](data-model.md) |
| Quickstart Guide | [quickstart.md](quickstart.md) |

---

*آخر تحديث: 2026-07-15*
