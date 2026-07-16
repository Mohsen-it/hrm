# تسجيل الموظفين و斐نادهم لفئات الحضور ثم斐ناد الفئات لجداول - تقسيم المهام

**التاريخ:** 2026-07-16
**عدد المهام:** 62

---

## المرحلة 1: الإعداد (Setup)

- [x] T001 إنشاء الترحيلات (migrations) لجميع الجداول الجديدة في `Modules/Attendance/database/migrations/`
  - `create_attendance_groups_table`
  - `create_attendance_employees_table`
  - `create_group_schedules_table`
  - `create_attendance_shifts_table`
  - `create_shift_details_table`
  - `create_time_intervals_table`
  - `create_break_times_table`
  - `create_time_interval_break_time_table`
  - `create_pay_codes_table`
  - `create_att_codes_table`
  - `create_group_policies_table`
  - `create_department_policies_table`
  - `create_department_schedules_table`
  - `create_employee_schedules_table`
  - `create_temporary_schedules_table`

- [x] T002 إنشاء الموديلات الأساسية في `Modules/Attendance/app/Models/`
  - `AttendanceGroup.php`
  - `AttendanceEmployee.php`
  - `GroupSchedule.php`
  - `AttendanceShift.php`
  - `ShiftDetail.php`
  - `TimeInterval.php`
  - `BreakTime.php`
  - `PayCode.php`
  - `AttCode.php`
  - `GroupPolicy.php`
  - `DepartmentPolicy.php`
  - `DepartmentSchedule.php`
  - `EmployeeSchedule.php`
  - `TemporarySchedule.php`

- [x] T003 إنشاء Seeders للبيانات الافتراضية في `Modules/Attendance/database/seeders/`
  - `PayCodeSeeder.php` — أكواد الرواتب الافتراضية (عمل، إجازة، إvertime)
  - `AttCodeSeeder.php` — أكواد الحضور الافتراضية (حاضر، غائب، متأخر)
  - `AttendanceGroupSeeder.php` — الفئات الافتراضية لكل شركة

- [x] T004 تحديث `Modules/Attendance/app/Providers/AttendanceServiceProvider.php` لتسجيل الخدمات الجديدة

---

## المرحلة 2: الأساسيات (Foundational)

- [x] T005 إنشاء AttendanceGroupRepository في `Modules/Attendance/app/Repositories/AttendanceGroupRepository.php`
  - `getAll(array $filters, int $perPage): LengthAwarePaginator`
  - `findById(int $id): ?AttendanceGroup`
  - `create(array $data): AttendanceGroup`
  - `update(AttendanceGroup $group, array $data): AttendanceGroup`
  - `delete(AttendanceGroup $group): bool`
  - `getByCompany(int $companyId): Collection`
  - `countEmployeesInGroup(int $groupId): int`

- [x] T006 إنشاء AttendanceEmployeeRepository في `Modules/Attendance/app/Repositories/AttendanceEmployeeRepository.php`
  - `getAll(array $filters, int $perPage): LengthAwarePaginator`
  - `findById(int $id): ?AttendanceEmployee`
  - `findByEmployee(int $employeeId): ?AttendanceEmployee`
  - `create(array $data): AttendanceEmployee`
  - `update(AttendanceEmployee $record, array $data): AttendanceEmployee`
  - `delete(AttendanceEmployee $record): bool`
  - `getByGroup(int $groupId): Collection`

- [x] T007 إنشاء AttendanceShiftRepository في `Modules/Attendance/app/Repositories/AttendanceShiftRepository.php`
  - `getAll(array $filters, int $perPage): LengthAwarePaginator`
  - `findById(int $id): ?AttendanceShift`
  - `create(array $data): AttendanceShift`
  - `update(AttendanceShift $shift, array $data): AttendanceShift`
  - `delete(AttendanceShift $shift): bool`
  - `getByCompany(int $companyId): Collection`

- [x] T008 إنشاء GroupScheduleRepository في `Modules/Attendance/app/Repositories/GroupScheduleRepository.php`
  - `getAll(array $filters, int $perPage): LengthAwarePaginator`
  - `findById(int $id): ?GroupSchedule`
  - `create(array $data): GroupSchedule`
  - `update(GroupSchedule $schedule, array $data): GroupSchedule`
  - `delete(GroupSchedule $schedule): bool`
  - `getActiveForGroup(int $groupId, string $date): ?GroupSchedule`
  - `getByGroup(int $groupId): Collection`
  - `hasOverlap(int $groupId, string $startDate, string $endDate, ?int $excludeId = null): bool`

- [x] T009 إنشاء TimeIntervalRepository في `Modules/Attendance/app/Repositories/TimeIntervalRepository.php`
  - `getAll(array $filters, int $perPage): LengthAwarePaginator`
  - `findById(int $id): ?TimeInterval`
  - `create(array $data): TimeInterval`
  - `update(TimeInterval $interval, array $data): TimeInterval`
  - `delete(TimeInterval $interval): bool`
  - `getByCompany(int $companyId): Collection`

---

## المرحلة 3: قصة المستخدم 1 — إدارة فئات الحضور (US1)

**الهدف:** إنشاء وتعديل وحذف فئات الحضور مع عرض الموظفين المرتبطين

**معايير الاختبار المستقلة:**
- إنشاء فئة جديدة مع كود فريد داخل الشركة
- تعديل الفئة (الاسم، الخصائص)
- حذف الفئة (فقط إذا لم يكن بها موظفون نشطون)
- عرض جميع الفئات مع عدد الموظفين

- [x] T010 [US1] إنشاء AttendanceGroupService في `Modules/Attendance/app/Services/AttendanceGroupService.php`
  - `createGroup(array $data): AttendanceGroup`
  - `updateGroup(AttendanceGroup $group, array $data): AttendanceGroup`
  - `deleteGroup(AttendanceGroup $group): bool`
  - `getGroupsByCompany(int $companyId): Collection`
  - `getGroupWithEmployees(int $groupId): AttendanceGroup`
  - `assignEmployeeToGroup(int $employeeId, int $groupId, array $flags): AttendanceEmployee`
  - `removeEmployeeFromGroup(int $employeeId): bool`
  - `getEmployeesInGroup(int $groupId): Collection`

- [x] T011 [US1] إنشاء StoreAttendanceGroupRequest في `Modules/Attendance/app/Http/Requests/StoreAttendanceGroupRequest.php`
  - التحقق من: code (مطلوب، فريد داخل الشركة)، name (مطلوب)، company_id (مطلوب)

- [x] T012 [US1] إنشاء UpdateAttendanceGroupRequest في `Modules/Attendance/app/Http/Requests/UpdateAttendanceGroupRequest.php`
  - التحقق من: code (اختياري، فريد إذا تغير)، name (اختياري)

- [x] T013 [US1] إنشاء AttendanceGroupsController في `Modules/Attendance/app/Http/Controllers/AttendanceGroupsController.php`
  - `index()` — عرض القائمة
  - `create()` — نموذج الإنشاء
  - `store(StoreAttendanceGroupRequest $request)` — الحفظ
  - `show(int $id)` — التفاصيل
  - `edit(int $id)` — نموذج التعديل
  - `update(UpdateAttendanceGroupRequest $request, int $id)` — التحديث
  - `destroy(int $id)` — الحذف
  - `assignEmployee(AssignEmployeeToGroupRequest $request, int $groupId)` — تعيين موظف
  - `removeEmployee(int $groupId, int $employeeId)` — إزالة موظف
  - `employees(int $groupId)` — عرض موظفي الفئة

- [x] T014 [US1] إضافة المسارات في `Modules/Attendance/routes/web.php`
  - `attendance/groups/*` مع middleware auth + permission

- [x] T015 [US1] إنشاء صفحات Vue في `resources/js/Pages/Shifts/AttendanceGroups/`
  - `Index.vue` — قائمة الفئات مع DataTable
  - `Create.vue` — نموذج الإنشاء
  - `Edit.vue` — نموذج التعديل
  - `Show.vue` — تفاصيل الفئة + قائمة الموظفين
  - `AssignEmployee.vue` — تعيين موظف للفئة

- [x] T016 [US1] إضافة الترجمات في `Modules/Attendance/lang/ar/attendance.php` و `en/attendance.php`

---

## المرحلة 4: قصة المستخدم 2 — إدارة مناوبات الحضور (US2)

**الهدف:** إنشاء وتعديل وحذف مناوبات الحضور مع تفاصيلها اليومية

**معايير الاختبار المستقلة:**
- إنشاء مناوبة جديدة مع تفاصيل 7 أيام
- تعديل المناوبة
- حذف المناوبة (فقط إذا لم تكن مستخدمة في جداول نشطة)
- عرض المناوبة مع تفاصيلها

- [x] T017 [US2] إنشاء AttendanceShiftService في `Modules/Attendance/app/Services/AttendanceShiftService.php`
  - `createShift(array $data): AttendanceShift`
  - `updateShift(AttendanceShift $shift, array $data): AttendanceShift`
  - `deleteShift(AttendanceShift $shift): bool`
  - `getShiftsByCompany(int $companyId): Collection`
  - `createShiftDetail(array $data): ShiftDetail`
  - `getShiftWithDetails(int $shiftId): AttendanceShift`

- [x] T018 [US2] إنشاء StoreAttendanceShiftRequest في `Modules/Attendance/app/Http/Requests/StoreAttendanceShiftRequest.php`
  - التحقق من: alias (مطلوب)، cycle_unit (مطلوب)، shift_cycle (مطلوب)، company_id (مطلوب)، details (مصفوفة)

- [x] T019 [US2] إنشاء AttendanceShiftsController في `Modules/Attendance/app/Http/Controllers/AttendanceShiftsController.php`
  - `index()` — عرض القائمة
  - `create()` — نموذج الإنشاء
  - `store(StoreAttendanceShiftRequest $request)` — الحفظ
  - `show(int $id)` — التفاصيل
  - `edit(int $id)` — نموذج التعديل
  - `update(UpdateAttendanceShiftRequest $request, int $id)` — التحديث
  - `destroy(int $id)` — الحذف

- [x] T020 [US2] إضافة المسارات في `Modules/Attendance/routes/web.php`
  - `attendance/shifts/*` مع middleware auth + permission

- [x] T021 [US2] إنشاء صفحات Vue في `resources/js/Pages/Shifts/AttendanceShifts/`
  - `Index.vue` — قائمة المناوبات
  - `Create.vue` — نموذج الإنشاء مع تفاصيل الأيام
  - `Edit.vue` — نموذج التعديل
  - `Show.vue` — تفاصيل المناوبة مع جدول التفاصيل

---

## المرحلة 5: قصة المستخدم 3 — إدارة جداول الفئات (US3)

**الهدف:** ربط الفئات بجداول المناوبة مع تحديد الفترة الزمنية

**معايير الاختبار المستقلة:**
- إنشاء جدول جديد للفئة
- تعديل الجدول
- حذف الجدول (فقط إذا لم يكن في فترة حالية أو مستقبلية)
- التحقق من عدم التداخل مع جداول أخرى

- [x] T022 [US3] إنشاء GroupScheduleService في `Modules/Attendance/app/Services/GroupScheduleService.php`
  - `createGroupSchedule(array $data): GroupSchedule`
  - `updateGroupSchedule(GroupSchedule $schedule, array $data): GroupSchedule`
  - `deleteGroupSchedule(GroupSchedule $schedule): bool`
  - `getActiveScheduleForGroup(int $groupId, string $date): ?GroupSchedule`
  - `getSchedulesForGroup(int $groupId): Collection`

- [x] T023 [US3] إنشاء StoreGroupScheduleRequest في `Modules/Attendance/app/Http/Requests/StoreGroupScheduleRequest.php`
  - التحقق من: group_id (مطلوب)، shift_id (مطلوب)، start_date (مطلوب)، end_date (مطلوب)
  - التحقق من عدم التداخل مع جداول أخرى

- [x] T024 [US3] إنشاء GroupSchedulesController في `Modules/Attendance/app/Http/Controllers/GroupSchedulesController.php`
  - `index()` — عرض القائمة
  - `create()` — نموذج الإنشاء
  - `store(StoreGroupScheduleRequest $request)` — الحفظ
  - `show(int $id)` — التفاصيل
  - `edit(int $id)` — نموذج التعديل
  - `update(UpdateGroupScheduleRequest $request, int $id)` — التحديث
  - `destroy(int $id)` — الحذف

- [x] T025 [US3] إضافة المسارات في `Modules/Attendance/routes/web.php`
  - `attendance/group-schedules/*` مع middleware auth + permission

- [x] T026 [US3] إنشاء صفحات Vue في `resources/js/Pages/Shifts/GroupSchedules/`
  - `Index.vue` — قائمة جداول الفئات
  - `Create.vue` — نموذج الإنشاء
  - `Edit.vue` — نموذج التعديل
  - `Show.vue` — تفاصيل الجدول

---

## المرحلة 6: قصة المستخدم 4 — إدارة الفواصل الزمنية (US4)

**الهدف:** إنشاء وتعديل وحذف الفواصل الزمنية مع فترات الاستراحة

**معايير الاختبار المستقلة:**
- إنشاء فاصل زمني جديد
- تعديل الفاصل
- حذف الفاصل (فقط إذا لم يكن مستخدماً في مناوبات نشطة)
- ربط الفاصل بفترات استراحة

- [ ] T027 [US4] إنشاء TimeIntervalService في `Modules/Attendance/app/Services/TimeIntervalService.php`
  - `createTimeInterval(array $data): TimeInterval`
  - `updateTimeInterval(TimeInterval $interval, array $data): TimeInterval`
  - `deleteTimeInterval(TimeInterval $interval): bool`
  - `getTimeIntervalsByCompany(int $companyId): Collection`

- [ ] T028 [US4] إنشاء StoreTimeIntervalRequest في `Modules/Attendance/app/Http/Requests/StoreTimeIntervalRequest.php`
  - التحقق من: alias (مطلوب، فريد)، in_time (مطلوب)، duration (مطلوب)، company_id (مطلوب)

- [ ] T029 [US4] إنشاء TimeIntervalsController في `Modules/Attendance/app/Http/Controllers/TimeIntervalsController.php`
  - `index()` — عرض القائمة
  - `create()` — نموذج الإنشاء
  - `store(StoreTimeIntervalRequest $request)` — الحفظ
  - `show(int $id)` — التفاصيل
  - `edit(int $id)` — نموذج التعديل
  - `update(UpdateTimeIntervalRequest $request, int $id)` — التحديث
  - `destroy(int $id)` — الحذف

- [ ] T030 [US4] إضافة المسارات في `Modules/Attendance/routes/web.php`
  - `attendance/time-intervals/*` مع middleware auth + permission

- [ ] T031 [US4] إنشاء صفحات Vue في `resources/js/Pages/Shifts/TimeIntervals/`
  - `Index.vue` — قائمة الفواصل
  - `Create.vue` — نموذج الإنشاء
  - `Edit.vue` — نموذج التعديل
  - `Show.vue` — تفاصيل الفاصل مع فترات الاستراحة

---

## المرحلة 7: قصة المستخدم 5 — تعيين الموظفين للفئات (US5)

**الهدف:** تعيين الموظفين لفئات الحضور عند الإنشاء أو التعديل مع تحديد الصلاحيات

**معايير الاختبار المستقلة:**
- تعيين موظف جديد لفئة عند إنشائه
- نقل موظف من فئة إلى أخرى مع تحديد التاريخ
- عرض الفئة الحالية لأي موظف
- تغيير الفئة من صفحة تعديل الموظف

- [ ] T032 [US5] تحديث `Modules/Users/app/Services/UserService.php`
  - إضافة حقل `attendance_group_id` في عملية الإنشاء
  - إضافة حقل `attendance_group_id` في عملية التعديل

- [ ] T033 [US5] تحديث `Modules/Users/app/Http/Requests/StoreUserRequest.php`
  - إضافة حقل `attendance_group_id` (اختياري)

- [ ] T034 [US5] تحديث `Modules/Users/app/Http/Requests/UpdateUserRequest.php`
  - إضافة حقل `attendance_group_id` (اختياري)

- [ ] T035 [US5] تحديث `Modules/Users/app/Http/Controllers/UsersController.php`
  - تمرير الفئات المتاحة في `create()` و `edit()`

- [ ] T036 [US5] تحديث `resources/js/Pages/Users/Create.vue`
  - إضافة حقل `attendance_group_id` (FormSelect اختياري)

- [ ] T037 [US5] تحديث `resources/js/Pages/Users/Edit.vue`
  - إضافة حقل `attendance_group_id` (FormSelect اختياري)

- [ ] T038 [US5] تحديث `resources/js/Pages/Users/Show.vue`
  - إضافة قسم "فئة الحضور" مع عرض الفئة الحالية وسجل التغييرات

---

## المرحلة 8: قصة المستخدم 6 — محرك الحساب (US6)

**الهدف:** احتساب الحضور تلقائياً عند وصول البصمات مع تحديد الحالة

**معايير الاختبار المستقلة:**
- تحديد الفئة تلقائياً من الموظف (أو الإسناد التلقائي للافتراضية)
- تحديد الجدول النشط (أولوية: جدول الموظف ← جدول الفئة)
- حساب التاخير والخروج المبكر
- حساب ساعات العمل
- حساب فترات الاستراحة
- حساب الإvertime

- [ ] T039 [US6] إنشاء AttendanceCalculationService في `Modules/Attendance/app/Services/AttendanceCalculationService.php`
  - `calculateAttendance(int $employeeId, DateTimeInterface $punchTime): AttendanceSession`
  - `resolveEmployeeGroup(int $employeeId): ?AttendanceGroup`
  - `resolveShiftForEmployee(int $employeeId, string $date): ?AttendanceShift`
  - `resolveTimeInterval(AttendanceShift $shift, string $date): ?TimeInterval`
  - `determineStatus(AttendanceSession $session, TimeInterval $interval): string`
  - `calculateLateMinutes(DateTimeInterface $actual, DateTimeInterface $expected, TimeInterval $interval): int`
  - `calculateEarlyLeaveMinutes(DateTimeInterface $actual, DateTimeInterface $expected, TimeInterval $interval): int`
  - `calculateOvertimeMinutes(DateTimeInterface $actual, DateTimeInterface $expected, TimeInterval $interval): int`
  - `calculateBreakMinutes(AttendanceSession $session): int`

- [ ] T040 [US6] تحديث `Modules/Attendance/app/Services/RawAttendanceLogService.php`
  - ربط `processLog()` بـ `AttendanceCalculationService`

- [ ] T041 [US6] تحديث `Modules/Attendance/app/Services/DailyAttendanceSummaryService.php`
  - ربط `recalculateForUserAndDate()` بـ `AttendanceCalculationService`

- [ ] T042 [US6] إنشاء AttendanceGroupSeeder للفئات الافتراضية في `Modules/Attendance/database/seeders/AttendanceGroupSeeder.php`
  - إنشاء فئة افتراضية لكل شركة موجودة
  - تعيين جميع الموظفين بدون فئة للفئة الافتراضية

- [ ] T043 [US6] تحديث `Modules/Users/app/Models/User.php`
  - إضافة relationship `attendanceGroup()` → BelongsTo → AttendanceGroup
  - إضافة relationship `attendanceEmployee()` → HasOne → AttendanceEmployee

---

## المرحلة 9: قصة المستخدم 7 — إدارة أكواد الرواتب والحضور (US7)

**الهدف:** إنشاء وإدارة أكواد الرواتب وأكواد الحضور

**معايير الاختبار المستقلة:**
- إنشاء كود راتب جديد
- إنشاء كود حضور جديد
- عرض جميع الأكواد
- حذف كود (فقط إذا لم يكن مستخدماً)

- [ ] T044 [US7] إنشاء PayCodeService في `Modules/Attendance/app/Services/PayCodeService.php`
  - `createPayCode(array $data): PayCode`
  - `getPayCodesByCompany(int $companyId): Collection`
  - `getWorkCodes(int $companyId): Collection`

- [ ] T045 [US7] إنشاء AttCodeService في `Modules/Attendance/app/Services/AttCodeService.php`
  - `createAttCode(array $data): AttCode`
  - `getAttCodes(): Collection`

- [ ] T046 [US7] إنشاء PayCodesController في `Modules/Attendance/app/Http/Controllers/PayCodesController.php`
  - CRUD كامل

- [ ] T047 [US7] إنشاء AttCodesController في `Modules/Attendance/app/Http/Controllers/AttCodesController.php`
  - CRUD كامل

- [ ] T048 [US7] إضافة المسارات في `Modules/Attendance/routes/web.php`
  - `attendance/pay-codes/*`
  - `attendance/att-codes/*`

- [ ] T049 [US7] إنشاء صفحات Vue في `resources/js/Pages/Shifts/PayCodes/`
  - `Index.vue` — قائمة أكواد الرواتب
  - `Create.vue` — إنشاء كود جديد
  - `Edit.vue` — تعديل كود

- [ ] T050 [US7] إنشاء صفحات Vue في `resources/js/Pages/Shifts/AttCodes/`
  - `Index.vue` — قائمة أكواد الحضور
  - `Create.vue` — إنشاء كود جديد
  - `Edit.vue` — تعديل كود

---

## المرحلة 10: قصة المستخدم 8 — سياسات الفئات والأقسام (US8)

**الهدف:** إنشاء وإدارة سياسات الحضور للفئات والأقسام

**criteria الاختبار المستقلة:**
- إنشاء سياسة لفئة
- إنشاء سياسة لقسم
- تعديل السياسة
- عرض السياسات

- [ ] T051 [US8] إنشاء GroupPolicyService في `Modules/Attendance/app/Services/GroupPolicyService.php`
  - `createPolicy(array $data): GroupPolicy`
  - `updatePolicy(GroupPolicy $policy, array $data): GroupPolicy`
  - `getPolicyForGroup(int $groupId): ?GroupPolicy`

- [ ] T052 [US8] إنشاء DepartmentPolicyService في `Modules/Attendance/app/Services/DepartmentPolicyService.php`
  - `createPolicy(array $data): DepartmentPolicy`
  - `updatePolicy(DepartmentPolicy $policy, array $data): DepartmentPolicy`
  - `getPolicyForDepartment(int $departmentId): ?DepartmentPolicy`

- [ ] T053 [US8] إنشاء GroupPoliciesController في `Modules/Attendance/app/Http/Controllers/GroupPoliciesController.php`
  - `index()` — عرض السياسات
  - `create()` — نموذج الإنشاء
  - `store()` — الحفظ
  - `edit()` — التعديل
  - `update()` — التحديث

- [ ] T054 [US8] إنشاء DepartmentPoliciesController في `Modules/Attendance/app/Http/Controllers/DepartmentPoliciesController.php`
  - CRUD كامل

- [ ] T055 [US8] إضافة المسارات في `Modules/Attendance/routes/web.php`
  - `attendance/group-policies/*`
  - `attendance/department-policies/*`

---

## المرحلة 11: النظافة والتحسين (Polish)

- [ ] T056 تحديث Sidebar لإضافة روابط الصفحات الجديدة في `resources/js/Components/layout/Sidebar.vue`

- [ ] T057 تحديث `modules_statuses.json` لتفعيل وحدة Attendance الجديدة

- [ ] T058 إنشاء اختبارات Feature في `Modules/Attendance/tests/Feature/`
  - `AttendanceGroupTest.php`
  - `AttendanceShiftTest.php`
  - `GroupScheduleTest.php`
  - `TimeIntervalTest.php`

- [ ] T059 إنشاء اختبارات Unit في `Modules/Attendance/tests/Unit/`
  - `AttendanceGroupServiceTest.php`
  - `AttendanceCalculationServiceTest.php`

- [x] T060 تشغيل `php artisan pint` لتنسيق الكود

- [ ] T061 تشغيل `php artisan test` للتأكد من مرور الاختبارات

- [ ] T062 تحديث `AGENTS.md` لتوثيق المكونات الجديدة

---

## التبعيات (Dependencies)

```
T001 (Migrations) ← T002 (Models) ← T005-T009 (Repositories)
                                         ↓
                                    T010-T055 (Services + Controllers + Vue)
                                         ↓
                                    T056-T062 (Polish)
```

### ترتيب câu المستخدم
```
US1 (فئات الحضور) ← US5 (تعيين الموظفين)
US2 (المناوبات) ← US3 (جداول الفئات)
US4 (الفواصل الزمنية) ← US2 (المناوبات)
US6 (محرك الحساب) ← US1 + US2 + US3 + US4
US7 (أكواد الرواتب) — مستقل
US8 (السياسات) — مستقل
```

---

## فرص التنفيذ المتوازي

### المجموعة A (الممكن تنفيذها معاً)
- T010-T016 (US1: فئات الحضور)
- T017-T021 (US2: المناوبات)
- T044-T050 (US7: أكواد الرواتب)

### المجموعة B (تعتمد على المجموعة A)
- T022-T026 (US3: جداول الفئات) — تعتمد على US1 + US2
- T027-T031 (US4: الفواصل الزمنية) — مستقلة
- T051-T055 (US8: السياسات) — مستقلة

### المجموعة C (تعتمد على المجموعتين A و B)
- T032-T038 (US5: تعيين الموظفين) — تعتمد على US1
- T039-T043 (US6: محرك الحساب) — تعتمد على US1 + US2 + US3 + US4

---

## استراتيجية التنفيذ

### MVP (الأقل قابلية للمحافظة)
1. T001-T004 (الإعداد)
2. T005-T009 (المستودعات)
3. T010-T016 (US1: فئات الحضور فقط)
4. T017-T021 (US2: المناوبات فقط)
5. T022-T026 (US3: جداول الفئات فقط)

### التسليم التدريجي
- **الدفعة 1:** فئات الحضور + المناوبات + جداول الفئات (US1 + US2 + US3)
- **الدفعة 2:** الفواصل الزمنية + تعيين الموظفين (US4 + US5)
- **الدفعة 3:** محرك الحساب + أكواد الرواتب (US6 + US7)
- **الدفعة 4:** السياسات + النظافة (US8 + Polish)

---

*عدد المهام: 62*
*آخر تحديث: 2026-07-16*
