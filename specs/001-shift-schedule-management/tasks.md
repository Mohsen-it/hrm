# إدارة فئات النوبات وجداول الوقت - تقسيم المهام

**الميزة:** Shift Categories & Time Schedules Management
**التاريخ:** 2026-07-15
**الوحدة:** `Modules/Shifts`
**الاعتماد على:** [plan.md](plan.md) | [spec.md](spec.md) | [data-model.md](data-model.md)

---

## User Stories Summary

| ID | Story | Role | Priority |
|----|-------|------|----------|
| US1 | إدارة فئات النوبات وجداول الوقت (CRUD) | HR Admin | P1 |
| US2 | إسناد الموظفين للفئات | HR Admin | P1 |
| US3 | تقارير الغياب الذكي والتقويم | HR Admin | P1 |
| US4 | عرض جدول الفريق والغيابات | Manager | P2 |
| US5 | عرض الجدول الشخصي والغيابات | Employee | P3 |

---

## Phase 1: Setup (Migrations & Infrastructure)

**Goal:** Database tables ready, permissions seeded, directory structure created.

### 1.1 Database Migrations

- [ ] T001 [P] Create `att_shift_categories` migration in `Modules/Shifts/database/migrations/` — columns: id, company_id (FK), name, type (enum: cyclic/weekly/hours), work_days, rest_days, work_days_json (JSON), weekend_days_json (JSON), required_hours, period_type (enum: daily/weekly/monthly), overtime_enabled, fingerprint_enabled, work_on_holidays, work_on_weekends, color, timestamps
- [ ] T002 [P] Create `att_time_schedules` migration in `Modules/Shifts/database/migrations/` — columns: id, company_id (FK), name, in_time, out_time, is_multi_day, late_margin, early_margin, timestamps
- [ ] T003 [P] Create `att_time_schedule_breaks` migration in `Modules/Shifts/database/migrations/` — columns: id, schedule_id (FK), break_start, duration, break_end, timestamps
- [ ] T004 [P] Create `att_category_time_schedule` migration in `Modules/Shifts/database/migrations/` — columns: id, shift_category_id (FK, unique), time_schedule_id (FK), timestamps
- [ ] T005 [P] Create `att_employee_shift_categories` migration in `Modules/Shifts/database/migrations/` — columns: id, employee_id (FK), shift_category_id (FK), start_date, end_date (nullable), snapshot_data (JSON), timestamps
- [ ] T006 [P] Create `att_hours_tracking` migration in `Modules/Shifts/database/migrations/` — columns: id, employee_id (FK), shift_category_id (FK), period_start, period_end, period_type (enum), required_hours, actual_hours, surplus_hours, deficit_hours, status (enum: on_track/deficit/surplus), timestamps; unique key on (employee_id, period_start, period_end, period_type)
- [ ] T007 Run `php artisan migrate` to apply all 6 migrations

### 1.2 Permissions & Seeder

- [ ] T008 [P] Create `ShiftCategoryPermissionSeeder` in `Modules/Shifts/database/seeders/ShiftCategoryPermissionSeeder.php` — seeds 10 permissions: view-shift-categories, create-shift-categories, edit-shift-categories, delete-shift-categories, view-time-schedules, create-time-schedules, edit-time-schedules, delete-time-schedules, assign-employees-to-category, view-attendance-by-schedule
- [ ] T009 Run `php artisan db:seed --class=ShiftCategoryPermissionSeeder`

---

## Phase 2: Foundational (Models, Repositories, Core Services)

**Goal:** All backend building blocks in place. No user-facing functionality yet. Must complete before any user story.

### 2.1 Enums

- [ ] T010 [P] Create `ShiftCategoryType` enum in `Modules/Shifts/app/Enums/ShiftCategoryType.php` — values: Cyclic, Weekly, Hours
- [ ] T011 [P] Create `PeriodType` enum in `Modules/Shifts/app/Enums/PeriodType.php` — values: Daily, Weekly, Monthly
- [ ] T012 [P] Create `TrackingStatus` enum in `Modules/Shifts/app/Enums/TrackingStatus.php` — values: OnTrack, Deficit, Surplus

### 2.2 Models

- [ ] T013 [P] Create `ShiftCategory` model in `Modules/Shifts/app/Models/ShiftCategory.php` — table: att_shift_categories; casts: type→ShiftCategoryType, work_days_json→array, weekend_days_json→array, period_type→PeriodType, booleans; relationships: company(), timeSchedule() HasOneThrough via CategoryTimeSchedule, employees() HasMany EmployeeShiftCategory, categoryTimeSchedule() HasOne; scopes: scopeByCompany(), scopeByType(), scopeCyclic(), scopeWeekly(), scopeHours()
- [ ] T014 [P] Create `TimeSchedule` model in `Modules/Shifts/app/Models/TimeSchedule.php` — table: att_time_schedules; casts: is_multi_day→boolean; relationships: company(), breaks() HasMany, categoryTimeSchedule() HasOne, category() HasOneThrough via CategoryTimeSchedule; scopes: scopeByCompany()
- [ ] T015 [P] Create `TimeScheduleBreak` model in `Modules/Shifts/app/Models/TimeScheduleBreak.php` — table: att_time_schedule_breaks; relationships: schedule() BelongsTo
- [ ] T016 [P] Create `CategoryTimeSchedule` model in `Modules/Shifts/app/Models/CategoryTimeSchedule.php` — table: att_category_time_schedule; relationships: shiftCategory() BelongsTo, timeSchedule() BelongsTo
- [ ] T017 [P] Create `EmployeeShiftCategory` model in `Modules/Shifts/app/Models/EmployeeShiftCategory.php` — table: att_employee_shift_categories; casts: start_date→date, end_date→date, snapshot_data→array; relationships: employee() BelongsTo User (personnel_employee), shiftCategory() BelongsTo; scopes: scopeActive() where end_date IS NULL, scopeForEmployee(), scopeForDate()
- [ ] T018 [P] Create `HoursTracking` model in `Modules/Shifts/app/Models/HoursTracking.php` — table: att_hours_tracking; casts: period_type→PeriodType, status→TrackingStatus; relationships: employee() BelongsTo, shiftCategory() BelongsTo; scopes: scopeForPeriod()

### 2.3 Repositories

- [ ] T019 [P] Create `ShiftCategoryRepository` in `Modules/Shifts/app/Repositories/ShiftCategoryRepository.php` — methods: query() returns Builder, getAll(filters) returns paginated, findById(id), create(data), update(category, data), delete(category) with active-employee check, hasActiveEmployees(categoryId) returns bool, getByCompany(companyId)
- [ ] T020 [P] Create `TimeScheduleRepository` in `Modules/Shifts/app/Repositories/TimeScheduleRepository.php` — methods: query(), getAll(filters), findById(id), create(data), update(schedule, data), delete(schedule) with linked-category check, isLinkedToCategory(scheduleId) returns bool
- [ ] T021 [P] Create `EmployeeShiftCategoryRepository` in `Modules/Shifts/app/Repositories/EmployeeShiftCategoryRepository.php` — methods: query(), getActiveAssignment(employeeId), getAssignmentsForDate(date), create(data) with snapshot capture, closeAssignment(assignment, endDate), bulkAssign(employeeIds, categoryId, startDate), hasOverlappingAssignment(employeeId, startDate, endDate) returns bool
- [ ] T022 [P] Create `HoursTrackingRepository` in `Modules/Shifts/app/Repositories/HoursTrackingRepository.php` — methods: query(), upsertTracking(employeeId, period, data), getTrackingForPeriod(employeeId, periodStart, periodEnd), getDeficitEmployees(periodStart, periodEnd)

### 2.4 Core Services (no UI dependencies)

- [ ] T023 [P] Create `CyclicScheduleCalculator` in `Modules/Shifts/app/Services/CyclicScheduleCalculator.php` — methods: isWorkDay(Carbon date, Carbon cycleStart, int workDays, int restDays): bool, getWorkDays(int month, int year, Carbon cycleStart, int workDays, int restDays): array, getNextWorkDay(Carbon date, Carbon cycleStart, int workDays, int restDays): Carbon, getNextRestDay(Carbon date, Carbon cycleStart, int workDays, int restDays): Carbon. Pure computation, no DB access.
- [ ] T024 [P] Create `ShiftCategoryValidationService` in `Modules/Shifts/app/Services/ShiftCategoryValidationService.php` — methods: validateCreate(data): array (type-specific rules per BR1), validateUpdate(category, data): array. Dependencies injected: none (pure validation logic).
- [ ] T025 [P] Create `TimeScheduleValidationService` in `Modules/Shifts/app/Services/TimeScheduleValidationService.php` — methods: validateCreate(data): array, validateUpdate(schedule, data): array (margins >= 0 per BR4).
- [ ] T026 [P] Create `AssignmentValidationService` in `Modules/Shifts/app/Services/AssignmentValidationService.php` — methods: validateAssign(employeeId, categoryId, startDate, ?endDate): void (throws on overlap, inactive employee per BR2), validateBulkAssign(employeeIds, categoryId, startDate): void.

### 2.5 Form Requests

- [ ] T027 [P] Create `StoreShiftCategoryRequest` in `Modules/Shifts/app/Http/Requests/StoreShiftCategoryRequest.php` — authorize via create-shift-categories; rules: name→required\|string\|max:100\|unique per company, type→required\|enum, work_days→required_if:type,cyclic\|integer\|min:1, rest_days→required_if:type,cyclic\|integer\|min:0, work_days_json→required_if:type,weekly\|array\|min:1, weekend_days_json→required_if:type,weekly\|array, required_hours→required_if:type,hours\|numeric\|min:0.01, period_type→required_if:type,hours\|enum
- [ ] T028 [P] Create `UpdateShiftCategoryRequest` in `Modules/Shifts/app/Http/Requests/UpdateShiftCategoryRequest.php` — same as store, name unique ignoring current ID
- [ ] T029 [P] Create `StoreTimeScheduleRequest` in `Modules/Shifts/app/Http/Requests/StoreTimeScheduleRequest.php` — authorize via create-time-schedules; rules: name→required\|max:100\|unique per company, in_time→required\|date_format:H:i, out_time→required\|date_format:H:i, is_multi_day→boolean, late_margin→integer\|min:0, early_margin→integer\|min:0
- [ ] T030 [P] Create `UpdateTimeScheduleRequest` in `Modules/Shifts/app/Http/Requests/UpdateTimeScheduleRequest.php` — same as store, name unique ignoring current ID
- [ ] T031 [P] Create `AssignEmployeeRequest` in `Modules/Shifts/app/Http/Requests/AssignEmployeeRequest.php` — authorize via assign-employees-to-category; rules: employee_id→required\|exists, shift_category_id→required\|exists, start_date→required\|date, end_date→nullable\|date\|after:start_date
- [ ] T032 [P] Create `BulkAssignRequest` in `Modules/Shifts/app/Http/Requests/BulkAssignRequest.php` — authorize via assign-employees-to-category; rules: employee_ids→required\|array\|min:1, shift_category_id→required\|exists, start_date→required\|date
- [ ] T033 [P] Create `TransferEmployeeRequest` in `Modules/Shifts/app/Http/Requests/TransferEmployeeRequest.php` — authorize via assign-employees-to-category; rules: employee_id→required\|exists, new_category_id→required\|exists, effective_date→required\|date

---

## Phase 3: US1 - Shift Categories & Time Schedules CRUD (P1 - HR Admin)

**Story:** As HR Admin, I can create, view, edit, and delete shift categories (cyclic, weekly, hours-based) and time schedules, linking them 1:1.

**Independent Test:** Create a "دوام 3+9" cyclic category, link it to a "24h continuous" time schedule, view in index, edit the rest days, verify the calendar calculates correctly → delete after reassignment.

### 3.1 API Resources

- [ ] T034 [P] [US1] Create `ShiftCategoryResource` in `Modules/Shifts/app/Http/Resources/ShiftCategoryResource.php` — fields: id, name, type, work_days, rest_days, work_days_json, weekend_days_json, required_hours, period_type, overtime_enabled, fingerprint_enabled, work_on_holidays, work_on_weekends, color, time_schedule (when loaded via CategoryTimeSchedule), active_employees_count (optional), created_at
- [ ] T035 [P] [US1] Create `TimeScheduleResource` in `Modules/Shifts/app/Http/Resources/TimeScheduleResource.php` — fields: id, name, in_time, out_time, is_multi_day, late_margin, early_margin, breaks (collection when loaded), linked_category_name (optional), created_at

### 3.2 Services

- [ ] T036 [US1] Create `ShiftCategoryService` in `Modules/Shifts/app/Services/ShiftCategoryService.php` — inject ShiftCategoryRepository, ShiftCategoryValidationService, CategoryTimeSchedule link handling. Methods: getAll(request) paginated with eager load timeSchedule, getById(id), create(data) validates + creates + links timeSchedule if provided, update(id, data), delete(id) checks active employees first (BR1.6). Cache tags: shift_categories (1h TTL per constitution).
- [ ] T037 [US1] Create `TimeScheduleService` in `Modules/Shifts/app/Services/TimeScheduleService.php` — inject TimeScheduleRepository, TimeScheduleValidationService. Methods: getAll(request) paginated, getById(id), create(data) validates + creates + creates break records if provided, update(id, data), delete(id) checks linked category (BR4.2), copy(id, newName) duplicates schedule with its breaks. Cache tags: time_schedules (1h TTL).

### 3.3 Controllers

- [ ] T038 [US1] Create `ShiftCategoriesController` in `Modules/Shifts/app/Http/Controllers/ShiftCategoriesController.php` — inject ShiftCategoryService. Methods: index() authorize view-shift-categories, Inertia::render('Shifts/ShiftCategories/Index'); create() Inertia::render('Shifts/ShiftCategories/Create') with timeSchedules prop; store(StoreShiftCategoryRequest) authorize create-shift-categories, $service→create(), redirect with success flash; edit(id) Inertia::render('Shifts/ShiftCategories/Edit'); update(id, UpdateShiftCategoryRequest); destroy(id) authorize delete-shift-categories, delete, redirect.
- [ ] T039 [US1] Create `TimeSchedulesController` in `Modules/Shifts/app/Http/Controllers/TimeSchedulesController.php` — inject TimeScheduleService. Methods: index(), create(), store(StoreTimeScheduleRequest), edit(id), update(id, UpdateTimeScheduleRequest), destroy(id), copy(id) duplicate via service.

### 3.4 Routes

- [ ] T040 [US1] Register ShiftCategories resource routes in `Modules/Shifts/routes/web.php` — Route::resource('shift-categories', ShiftCategoriesController::class) with auth + permission middleware
- [ ] T041 [US1] Register TimeSchedules resource routes in `Modules/Shifts/routes/web.php` — Route::resource('time-schedules', TimeSchedulesController::class) + Route::post('time-schedules/{id}/copy', ...)

### 3.5 Translations

- [ ] T042 [P] [US1] Create Arabic translations in `Modules/Shifts/resources/lang/ar/shifts.php` — keys: shift_categories, time_schedules, category_name, category_type, cyclic, weekly, hours, work_days, rest_days, work_days_json, weekend_days_json, required_hours, period_type, daily, weekly_label, monthly, overtime_enabled, fingerprint_enabled, work_on_holidays, work_on_weekends, color, schedule_name, in_time, out_time, is_multi_day, late_margin, early_margin, break_start, duration, break_end, category_created, category_updated, category_deleted, cannot_delete_active, schedule_created, schedule_updated, schedule_deleted, cannot_delete_linked, copy, copy_success
- [ ] T043 [P] [US1] Create English translations in `Modules/Shifts/resources/lang/en/shifts.php` — same keys with English values

### 3.6 Vue Shared Partials

- [ ] T044 [P] [US1] Create `ShiftCategoryForm.vue` partial in `Modules/Shifts/Resources/js/Pages/Shifts/Partials/ShiftCategoryForm.vue` — shared form used in Create + Edit. Uses FormInput, FormSelect, FormDatepicker. Conditional fields: if type=cyclic show work_days + rest_days inputs; if type=weekly show multi-checkbox for weekdays; if type=hours show required_hours + period_type. Time schedule dropdown (1:1). Dynamic reactive form using watch on type.
- [ ] T045 [P] [US1] Create `TimeScheduleForm.vue` partial in `Modules/Shifts/Resources/js/Pages/Shifts/Partials/TimeScheduleForm.vue` — shared form for Create + Edit. Time inputs for in_time/out_time, number inputs for margins, toggle for is_multi_day, dynamic break rows (add/remove). Uses FormInput, FormSelect, FormDatepicker.

### 3.7 Vue Pages - Shift Categories

- [ ] T046 [US1] Create `ShiftCategories/Index.vue` in `Modules/Shifts/Resources/js/Pages/Shifts/ShiftCategories/Index.vue` — DataTable with columns: name, type badge (color-coded: cyclic=blue, weekly=green, hours=orange), work pattern (e.g. "3+9" or "أحد-خميس"), linked schedule name, employee count, actions (edit/delete). PageHeader with create button. ConfirmDialog for delete. Filters: type dropdown, search by name. Pagination.
- [ ] T047 [US1] Create `ShiftCategories/Create.vue` in `Modules/Shifts/Resources/js/Pages/Shifts/ShiftCategories/Create.vue` — FormModal or page with ShiftCategoryForm partial. Submit via Inertia POST to shift-categories.store.
- [ ] T048 [US1] Create `ShiftCategories/Edit.vue` in `Modules/Shifts/Resources/js/Pages/Shifts/ShiftCategories/Edit.vue` — Reuses ShiftCategoryForm partial. Pre-filled with category data + linked schedule. Submit via Inertia PUT.
- [ ] T049 [US1] Create `ShiftCategories/Show.vue` in `Modules/Shifts/Resources/js/Pages/Shifts/ShiftCategories/Show.vue` — Detail view: category info card, linked time schedule details (in/out times, breaks), list of assigned employees via nested DataTable.

### 3.8 Vue Pages - Time Schedules

- [ ] T050 [US1] Create `TimeSchedules/Index.vue` in `Modules/Shifts/Resources/js/Pages/Shifts/TimeSchedules/Index.vue` — DataTable: name, in_time, out_time, is_multi_day badge, late_margin, early_margin, linked_categories count, actions (edit/delete/copy). PageHeader with create. ConfirmDialog for delete.
- [ ] T051 [US1] Create `TimeSchedules/Create.vue` in `Modules/Shifts/Resources/js/Pages/Shifts/TimeSchedules/Create.vue` — Page with TimeScheduleForm partial. Submit to time-schedules.store.
- [ ] T052 [US1] Create `TimeSchedules/Edit.vue` in `Modules/Shifts/Resources/js/Pages/Shifts/TimeSchedules/Edit.vue` — Reuses TimeScheduleForm partial. Pre-filled. Submit via Inertia PUT.
- [ ] T053 [US1] Create `TimeSchedules/Show.vue` in `Modules/Shifts/Resources/js/Pages/Shifts/TimeSchedules/Show.vue` — Detail: schedule info, breaks list, linked category, all using Badge/StatCard components.

---

## Phase 4: US2 - Employee Assignment (P1 - HR Admin)

**Story:** As HR Admin, I can assign employees to shift categories (single or bulk), transfer between categories, and unassign. Historical snapshot preserved.

**Independent Test:** Assign employee #1 to "إداري" (admin) category → verify active assignment → transfer to "دوام 3+9" → verify old assignment closed with snapshot, new assignment active.

### 4.1 API Resources

- [ ] T054 [P] [US2] Create `EmployeeShiftCategoryResource` in `Modules/Shifts/app/Http/Resources/EmployeeShiftCategoryResource.php` — fields: id, employee (id, name, emp_code, department via nested), category (id, name, type), start_date, end_date, is_active (computed: end_date IS NULL), snapshot_data (category + schedule info)

### 4.2 Services

- [ ] T055 [US2] Create `ShiftCategoryAssignmentService` in `Modules/Shifts/app/Services/ShiftCategoryAssignmentService.php` — inject EmployeeShiftCategoryRepository, AssignmentValidationService, ShiftCategoryRepository. Methods: assignEmployee(employeeId, categoryId, startDate, ?endDate) validates + closes previous active assignment + captures snapshot (category + schedule data) + creates new, bulkAssign(employeeIds, categoryId, startDate) loops assignEmployee per employee, transferEmployee(employeeId, newCategoryId, effectiveDate) closes current + creates new from effectiveDate, unassignEmployee(employeeId, endDate) closes active assignment, getActiveAssignment(employeeId), getAllAssignments(filters) paginated. Cache tag: employee_assignments (12h TTL per constitution).

### 4.3 Controllers

- [ ] T056 [US2] Create `ShiftCategoryAssignmentController` in `Modules/Shifts/app/Http/Controllers/ShiftCategoryAssignmentController.php` — inject ShiftCategoryAssignmentService. Methods: index() authorize view-shift-categories, Inertia render with all assignments; assign(AssignEmployeeRequest) authorize assign-employees-to-category, $service→assignEmployee(), redirect; bulkAssign(BulkAssignRequest) authorize, $service→bulkAssign(), redirect with count; transfer(TransferEmployeeRequest) authorize, $service→transferEmployee(), redirect; unassign(request) authorize, $service→unassignEmployee(), redirect.

### 4.4 Routes

- [ ] T057 [US2] Register assignment routes in `Modules/Shifts/routes/web.php` — GET shift-assignments (index), POST shift-assignments/assign, POST shift-assignments/bulk-assign, POST shift-assignments/transfer, POST shift-assignments/unassign — all with auth + permission middleware

### 4.5 Vue Pages - Assignments

- [ ] T058 [US2] Create `Assignments/Index.vue` in `Modules/Shifts/Resources/js/Pages/Shifts/Assignments/Index.vue` — DataTable: employee (name, code, department), category (name, type badge), start_date, end_date (or "نشط" green badge), actions (transfer, unassign). Filters: category dropdown, department dropdown, status (active/all), search. PageHeader with "تعيين موظف" and "تعيين جماعي" buttons.
- [ ] T059 [US2] Create `Assignments/Assign.vue` in `Modules/Shifts/Resources/js/Pages/Shifts/Assignments/Assign.vue` — FormModal or page: employee search/select (autocomplete), category dropdown show type badge + linked schedule, start_date datepicker, end_date optional datepicker. Validation: no overlap, employee active.
- [ ] T060 [US2] Create `Assignments/BulkAssign.vue` in `Modules/Shifts/Resources/js/Pages/Shifts/Assignments/BulkAssign.vue` — FormModal or page: multi-select employees by department filter, category dropdown with schedule preview, common start_date. Submit via Inertia POST with employee_ids array.

---

## Phase 5: US3 - Smart Absence & Calendar (P1 - HR Admin)

**Story:** As HR Admin, I can view smart absence reports (only on actual work days) and employee schedule calendars with color-coded work/rest/absence/holiday days. Cyclic patterns auto-continue across months. Multi-day continuous shifts auto-attend intermediate days.

**Independent Test:** Employee with 1+3 cyclic pattern starting Saturday → verify calendar shows work: Sat, rest: Sun/Mon/Tue, work: Wed...; Friday absence report excludes this employee if it's their rest day; verify auto-continuation into next month.

### 5.1 Services

- [ ] T061 [US3] Create `AbsenceCalculationService` in `Modules/Shifts/app/Services/AbsenceCalculationService.php` — inject EmployeeShiftCategoryRepository, CyclicScheduleCalculator, plus attendance data access (iclock_transaction via Attendance module or raw query). Methods: getExpectedEmployees(Carbon date): Collection of employees expected to work that day (resolves each employee's category type → calls calculator or checks weekly days), getAbsentEmployees(Carbon date, ?departmentId): Collection of expected employees with no punch + no approved leave + not a holiday (BR3.1), getMonthlyAbsence(employeeId, int month, int year): array of absence days (only work days where no punch), isEmployeeExpectedToWork(employeeId, Carbon date): bool. Cache: expected_employees_{date} (5min TTL). For multi-day continuous: intermediate days auto-present.
- [ ] T062 [US3] Create `HoursTrackingService` in `Modules/Shifts/app/Services/HoursTrackingService.php` — inject HoursTrackingRepository, attendance data. Methods: calculatePeriodHours(employeeId, periodStart, periodEnd): void — sums actual hours from iclock_transaction, computes surplus/deficit, upserts tracking record, getDeficitReport(?departmentId, periodStart, periodEnd): Collection of employees with deficit status. Queue: calculatePeriodHours dispatched as queued job for heavy calculations.

### 5.2 API Resources

- [ ] T063 [P] [US3] Create `ScheduleCalendarResource` in `Modules/Shifts/app/Http/Resources/ScheduleCalendarResource.php` — fields: date, day_name (localized), day_of_week, status (enum: work, rest, holiday, absent), is_work_day, in_time, out_time, has_punch, punch_time, notes. Computed from category type + cyclic calculator or weekly days.

### 5.3 Controllers

- [ ] T064 [US3] Create `ScheduleCalendarController` in `Modules/Shifts/app/Http/Controllers/ScheduleCalendarController.php` — inject ShiftCategoryAssignmentService, CyclicScheduleCalculator. Methods: employee(id, ?month, ?year) authorize view-shift-categories, get active assignment → resolve category → calculate 30-day calendar array → return as Inertia page, department(departmentId, ?date) returns list of employees with their day status.
- [ ] T065 [US3] Create `SmartAbsenceController` in `Modules/Shifts/app/Http/Controllers/SmartAbsenceController.php` — inject AbsenceCalculationService. Methods: daily(?date, ?departmentId) authorize view-attendance-by-schedule, return expected + absent lists for that date → Inertia render, monthly(employeeId, ?month, ?year) return employee's absence days for the month.

### 5.4 Routes

- [ ] T066 [US3] Register calendar routes in `Modules/Shifts/routes/web.php` — GET schedule-calendar/{employee}, GET schedule-calendar/department/{department} — with auth + permission:view-shift-categories
- [ ] T067 [US3] Register smart absence routes in `Modules/Shifts/routes/web.php` — GET smart-absence/daily, GET smart-absence/monthly/{employee} — with auth + permission:view-attendance-by-schedule

### 5.5 Vue Composable

- [ ] T068 [P] [US3] Create `useCyclicCalendar.js` composable in `Modules/Shifts/Resources/js/Composables/useCyclicCalendar.js` — exports: isWorkDay(startDate, workDays, restDays, targetDate): bool, getMonthCalendar(year, month, startDate, workDays, restDays): Array of { date, dayName, isWorkDay, status }, getNextWorkDay(startDate, workDays, restDays, fromDate): Date. Pure client-side computation for calendar display.

### 5.6 Vue Pages - Calendar

- [ ] T069 [US3] Create `CalendarLegend.vue` partial in `Modules/Shifts/Resources/js/Pages/Shifts/Partials/CalendarLegend.vue` — color legend bar: green=دوام, gray=راحة, red=غياب, yellow=عطلة رسمية
- [ ] T070 [US3] Create `CyclicDaysDisplay.vue` partial in `Modules/Shifts/Resources/js/Pages/Shifts/Partials/CyclicDaysDisplay.vue` — visual row of circles showing work/rest pattern (green/gray dots) with day count labels. Used in category detail and calendar pages.
- [ ] T071 [US3] Create `EmployeeCalendar.vue` in `Modules/Shifts/Resources/js/Pages/Shifts/Calendar/EmployeeCalendar.vue` — grid calendar view (7 columns × 5-6 rows). Each cell: date number, color background per status, punch time if available. Navigation: prev/next month arrows. Employee selector (autocomplete). Uses useCyclicCalendar composable + server data. Legend below calendar. Click cell → shows punch details or absence reason.
- [ ] T072 [US3] Create `SmartAbsenceReport.vue` in `Modules/Shifts/Resources/js/Pages/Shifts/Absence/SmartAbsenceReport.vue` — Two tabs: "تقرير يومي" and "تقرير شهري". Daily tab: date picker + department filter, DataTable of absent employees (name, code, department, category, expected in_time). Monthly tab: employee selector, month/year picker, calendar grid with absence days highlighted red. Summary card: total work days, absent days, attendance %.

---

## Phase 6: US4 - Manager Dashboard (P2 - Manager)

**Story:** As Manager, I can view my team's schedule and absences — only showing absences on actual work days.

**Independent Test:** Login as department manager → view team calendar → verify Friday shows no absences for admin-pattern employees → verify cyclic employee only absent on work days.

### 6.1 Backend

- [ ] T073 [US4] Add `teamSchedule()` method to `ScheduleCalendarController` in `Modules/Shifts/app/Http/Controllers/ScheduleCalendarController.php` — GET team-calendar, filters by manager's department employees (via superior_id or department_id), returns consolidated calendar view
- [ ] T074 [US4] Add `teamAbsence()` method to `SmartAbsenceController` in `Modules/Shifts/app/Http/Controllers/SmartAbsenceController.php` — GET smart-absence/team, filters absent employees by manager's team only

### 6.2 Routes

- [ ] T075 [US4] Register manager routes in `Modules/Shifts/routes/web.php` — GET team-calendar, GET smart-absence/team — with auth middleware (manager permission inherited from view-shift-categories or view-attendance-by-schedule)

### 6.3 Vue Pages

- [ ] T076 [US4] Create `Manager/TeamCalendar.vue` in `Modules/Shifts/Resources/js/Pages/Shifts/Manager/TeamCalendar.vue` — reuses EmployeeCalendar logic but lists all team members in rows vs dates in columns (matrix view). Color-coded per employee per day.
- [ ] T077 [US4] Create `Manager/TeamAbsence.vue` in `Modules/Shifts/Resources/js/Pages/Shifts/Manager/TeamAbsence.vue` — reuses SmartAbsenceReport with team filter preset.

---

## Phase 7: US5 - Employee Self-Service (P3 - Employee)

**Story:** As Employee, I can see my own work pattern, upcoming schedule, and recorded absences.

**Independent Test:** Login as employee → view "جدول دوامي" → see color-coded calendar → verify rest days shown correctly for cyclic pattern → view my absence records.

### 7.1 Backend

- [ ] T078 [US5] Add `myCalendar()` method to `ScheduleCalendarController` in `Modules/Shifts/app/Http/Controllers/ScheduleCalendarController.php` — GET my-calendar, returns logged-in employee's calendar (no permission needed beyond auth)
- [ ] T079 [US5] Add `myAbsence()` method to `SmartAbsenceController` in `Modules/Shifts/app/Http/Controllers/SmartAbsenceController.php` — GET my-absence, returns logged-in employee's absence records

### 7.2 Routes

- [ ] T080 [US5] Register employee self-service routes in `Modules/Shifts/routes/web.php` — GET my-calendar, GET my-absence — with auth middleware only (no special permission, all employees can see their own)

### 7.3 Vue Pages

- [ ] T081 [US5] Create `Employee/MySchedule.vue` in `Modules/Shifts/Resources/js/Pages/Shifts/Employee/MySchedule.vue` — personal calendar view. Shows current category name + pattern, calendar grid for current/next month, upcoming work days list. Uses CyclicDaysDisplay for visual pattern.
- [ ] T082 [US5] Create `Employee/MyAbsence.vue` in `Modules/Shifts/Resources/js/Pages/Shifts/Employee/MyAbsence.vue` — personal absence log. DataTable: date, expected_in_time, status (absent/late/early), notes. Summary: total work days this month, absent days, attendance rate %.

---

## Phase 8: Polish & Cross-Cutting

**Goal:** Performance optimization, caching, queue integration, tests, final cleanup.

### 8.1 Caching Integration

- [ ] T083 [P] Add cache tags to `ShiftCategoryService` — Cache::tags(['shift_categories'])->remember() for getAll, flush on create/update/delete. TTL: 1 hour.
- [ ] T084 [P] Add cache tags to `TimeScheduleService` — Cache::tags(['time_schedules'])->remember() for getAll, flush on create/update/delete. TTL: 1 hour.
- [ ] T085 [P] Add cache tags to `AbsenceCalculationService` — Cache::tags(['employee_assignments'])->remember() for getExpectedEmployees (5min TTL), flush on assignment changes.
- [ ] T086 [P] Add cache to `CyclicScheduleCalculator` — Cache::tags(['shift_categories'])->remember() for getWorkDays() per-category per-month. TTL: 12 hours.

### 8.2 Queue Integration

- [ ] T087 Create `CalculatePeriodHoursJob` in `Modules/Shifts/app/Jobs/CalculatePeriodHoursJob.php` — dispatches HoursTrackingService::calculatePeriodHours() for a single employee-period. Queued on `attendance` queue.
- [ ] T088 Schedule `CalculatePeriodHoursJob` in `Modules/Shifts/app/Console/Commands/ScheduleHoursTracking.php` or via Laravel scheduler for end-of-period automatic calculation.

### 8.3 Performance Optimization

- [ ] T089 Verify eager loading on all list queries — ShiftCategory::with('categoryTimeSchedule.timeSchedule.breaks'), EmployeeShiftCategory::with('employee', 'shiftCategory')
- [ ] T090 Add composite indexes per data-model.md — verify all FK indexes exist, add (employee_id, end_date) index for active assignment lookups, add (period_start, period_end) index for hours tracking

### 8.4 Translations Completion

- [ ] T091 [P] Complete Arabic translations — add remaining keys for assignment, calendar, absence UI strings (assign_employee, bulk_assign, transfer, unassign, active, inactive, work, rest, absent, holiday, expected_today, absent_today, attendance_rate, my_schedule, my_absence, team_calendar, team_absence, calendar, daily_report, monthly_report)
- [ ] T092 [P] Complete English translations — same keys with English values

### 8.5 Tests

- [ ] T093 [P] Create `CyclicScheduleCalculatorTest` in `Modules/Shifts/tests/Unit/CyclicScheduleCalculatorTest.php` — test: 1+3 pattern starting July 3 2026 → correct work/rest days for July and August; 3+9 pattern → correct 12-day cycle; leap year Feb 29; start date = last day of month; getNextWorkDay / getNextRestDay edge cases
- [ ] T094 [P] Create `ShiftCategoryServiceTest` in `Modules/Shifts/tests/Unit/ShiftCategoryServiceTest.php` — test: create cyclic/ weekly/hours categories; validation errors for missing fields; name uniqueness per company; delete with active employees fails
- [ ] T095 [P] Create `AssignmentValidationServiceTest` in `Modules/Shifts/tests/Unit/AssignmentValidationServiceTest.php` — test: overlapping assignments rejected; inactive employee assignment rejected; valid assignment passes
- [ ] T096 [P] Create `ShiftCategoriesControllerTest` in `Modules/Shifts/tests/Feature/ShiftCategoriesControllerTest.php` — test: index returns paginated list; store creates category; update modifies; destroy with active employees returns error; destroy after reassignment succeeds; permission gates (403 without permission)
- [ ] T097 [P] Create `SmartAbsenceControllerTest` in `Modules/Shifts/tests/Feature/SmartAbsenceControllerTest.php` — test: daily report filters by schedule (admin pattern: absent on Tue, not absent on Fri); cyclic pattern: absent only on work day; monthly report counts only work-day absences; multi-day shift: intermediate days auto-present
- [ ] T098 [P] Create `ScheduleCalendarControllerTest` in `Modules/Shifts/tests/Feature/ScheduleCalendarControllerTest.php` — test: calendar returns correct work/rest array for cyclic pattern; calendar auto-continues across months; weekly pattern shows correct weekend days; hours-based returns null work pattern but shows tracking data

### 8.6 Final Quality

- [ ] T099 Run `php artisan pint` on entire Shifts module to fix code style
- [ ] T100 Run `php artisan test --filter=Modules\\Shifts` to verify all tests pass
- [ ] T101 Run `php artisan optimize:clear` to clear all caches and verify fresh state

---

## Dependencies & Execution Order

```
Phase 1 (Setup)
  └──► Phase 2 (Foundational)
         ├──► Phase 3 (US1: CRUD) ────► Phase 4 (US2: Assignment) ──► Phase 5 (US3: Absence)
         │                                                                    │
         │                                                              ┌─────┴─────┐
         │                                                              ▼           ▼
         │                                                        Phase 6 (US4)  Phase 7 (US5)
         │                                                              │           │
         └──────────────────────────────────────────────────────────────┴───────────┘
                                                                              │
                                                                              ▼
                                                                     Phase 8 (Polish)
```

### Parallel Opportunities

**Within Phase 1:** T001-T006 (all 6 migrations) can be created in parallel → then T007 runs sequentially.

**Within Phase 2:**
- T010-T012 (3 enums) — parallel
- T013-T018 (6 models) — parallel
- T019-T022 (4 repositories) — parallel
- T023-T026 (4 core services) — parallel
- T027-T033 (7 form requests) — parallel

**Within Phase 3 (US1):**
- T034-T035 (API resources), T042-T043 (translations), T044-T045 (Vue partials) — all parallel
- T046-T053 (8 Vue pages) — parallel after partials done

**Within Phase 5 (US3):**
- T068 (composable), T069-T070 (partials) — parallel

**Within Phase 8:**
- T083-T086 (caching) — parallel
- T091-T092 (translations) — parallel
- T093-T098 (6 tests) — parallel
- T099-T101 run sequentially at end

---

## Implementation Strategy

### MVP Scope (P1 Only)

Complete **Phase 1 + 2 + 3 + 4 + 5** for a fully functional system:
- HR Admin can create/manage shift categories and time schedules
- HR Admin can assign/transfer employees
- Smart absence reports work correctly
- Calendar works for all pattern types

This is self-contained and delivers the core value.

### Incremental Delivery

1. **Sprint 1:** Phases 1-2-3 (Categories & Schedules CRUD)
2. **Sprint 2:** Phase 4 (Employee Assignment)
3. **Sprint 3:** Phase 5 (Smart Absence & Calendar)
4. **Sprint 4:** Phases 6-7 (Manager + Employee views)
5. **Sprint 5:** Phase 8 (Polish, caching, tests)

---

## Total Task Count

| Phase | Tasks | Parallelizable |
|-------|-------|----------------|
| Phase 1: Setup | 9 | 7 |
| Phase 2: Foundational | 24 | 24 |
| Phase 3: US1 - CRUD | 20 | 12 |
| Phase 4: US2 - Assignment | 7 | 1 |
| Phase 5: US3 - Absence | 12 | 5 |
| Phase 6: US4 - Manager | 5 | 0 |
| Phase 7: US5 - Employee | 5 | 0 |
| Phase 8: Polish | 19 | 12 |
| **Total** | **101** | **61** |

---

*عدد المهام: 101*
*آخر تحديث: 2026-07-15*
