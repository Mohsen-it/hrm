# تسجيل الموظفين و斐نادهم لفئات الحضور ثم斐ناد الفئات لجداول - خطة التنفيذ التقنية

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-16

---

## السياق التقني (Technical Context)

### البنية التحتية
- **Framework:** Laravel 13 + PHP 8.3+
- **Frontend:** Vue 3 (Composition API) + Inertia.js + Tailwind CSS 4.3
- **Database:** SQLite (dev) / PostgreSQL (prod) / MySQL 8.0+ (belle)
- **ORM:** Eloquent
- **Architecture:** Modular (nwidart/laravel-modules)
- **Auth:** Spatie Permission
- **Build:** Vite

### الوحدات المشاركة
- `Modules\Attendance` — يحتوي على الحضور الحالي (3 models, 11 services)
- `Modules\Shifts` — يحتوي على المناوبات الحالية (Shift, ShiftCategory, TimeSchedule)
- `Modules\Users` — يحتوي على سجل الموظفين (User model)
- `Modules\Companies` — إدارة الشركات
- `Modules\Departments` — إدارة الأقسام
- `Modules\FingerprintDevices` — أجهزة البصمة

### التبعيات الحالية
- `Modules\Users\Models\User` — السجل المركزي للموظفين
- `Modules\Shifts\Models\Shift` — المناوبات البسيطة
- `Modules\Shifts\Models\ShiftCategory` — فئات المناوبة (att_shift_categories)
- `Modules\Shifts\Models\TimeSchedule` — الجداول الزمنية (att_time_schedules)
- `Modules\Attendance\Models\AttendanceSession` — جلسات الحضور
- `Modules\Attendance\Models\DailyAttendanceSummary` — الملخص اليومي
- `Modules\Attendance\Models\RawAttendanceLog` — البصمات الخام

### غير معروف (NEEDS CLARIFICATION)
- لا يوجد — جميع النقاط تم توضيحها في `/speckit.clarify`

---

## فحص الدستور (Constitution Check)

| المادة | الحالة | ملاحظات |
|--------|--------|---------|
| II: بنية الوحدات | ✅ متوافق | يتبع Controller → Service → Repository → Model |
| III: التسمية | ✅ متوافق | Models مفرد، Controllers جمع، Migrations snake_case |
| IV: قاعدة البيانات | ✅ متوافق | SQLite dev / PostgreSQL prod، Foreign Keys، Indexes |
| V: الأمان | ✅ متوافق | Spatie Permission، Validation في Service |
| VI: الأداء | ✅ متوافق | Eager loading، Pagination، Cache |
| VII: المكونات | ✅ متوافق | DataTable، FormInput، FormModal، etc. |
| IX: التوثيق | ✅ متوافق | PHPDoc على Methods العامة |
| X: البساطة | ✅ متوافق | لا مكتبات غير ضرورية |
| XIV: التوسع | ✅ متوافق | DI، Stateless Services، No app()/resolve() |

**النتيجة:** ✅ لا توجد انتهاكات للدستور

---

## المرحلة 0: البحث والتحليل (Research)

### قرارات البحث المطلوبة

| # | القرار | البديل الم Considered | السبب |
|---|--------|----------------------|-------|
| 1 | جداول `att_*` الأصلية | إنشاء جداول جديدة | الحفاظ على التوافق مع النظام الأصلي ZKTeco |
| 2 | موديلات منفصلة | توسيع الموديلات الحالية | كل جدول له خصائصه وعلاقاته — الفصل أوضح |
| 3 | محرك حساب منفصل | دمج في Services الحالية | Responsibility clarity — كل خدمة مسؤولة عن جزء |
| 4 | Vue Pages منفصلة | تعديل الصفحات الحالية | كل وحدة لها واجهتها — لا اختلاط |
| 5 | Seeders للبيانات الافتراضية | إدخال يدوي | ضمان الاتساق بين بيئات التطوير والإنتاج |

---

## المرحلة 1: التصميم والعقود (Design & Contracts)

### نموذج البيانات (Data Model)

**الملف:** `specs/005-employee-group-schedule-attendance/data-model.md`

**الكيانات الرئيسية:**
1. `AttendanceGroup` — فئة الحضور
2. `AttendanceEmployee` — تعيين الموظف للفئة
3. `GroupSchedule` — جدول الفئة
4. `AttendanceShift` — المناوبة
5. `ShiftDetail` — تفصيل المناوبة
6. `TimeInterval` — الفاصل الزمني
7. `BreakTime` — فترات الاستراحة
8. `PayCode` — أكواد الرواتب
9. `AttCode` — أكواد الحضور
10. `GroupPolicy` — سياسة الفئة
11. `DepartmentPolicy` — سياسة القسم
12. `DepartmentSchedule` — جدول القسم
13. `EmployeeSchedule` — جدول الموظف الفردي
14. `TemporarySchedule` — الجدول المؤقت

### العقود (Contracts)

**الملف:** `specs/005-employee-group-schedule-attendance/contracts/`

**العقود المطلوبة:**
1. `attendance-group-api.md` — عقد API فئات الحضور
2. `attendance-shift-api.md` — عقد API مناوبات الحضور
3. `group-schedule-api.md` — عقد API جداول الفئات
4. `time-interval-api.md` — عقد API الفواصل الزمنية
5. `attendance-calculation-api.md` — عقد محرك الحساب

### الدليل السريع (Quickstart)

**الملف:** `specs/005-employee-group-schedule-attendance/quickstart.md`

**السيناريوهات:**
1. إنشاء فئة حضور جديدة
2. تعيين موظف لفئة
3. إنشاء جدول للفئة
4. إنشاء مناوبة مع تفاصيل
5. إنشاء فاصل زمني
6. اختبار محرك الحساب (بصمة تجريبية)

---

## ترتيب التنفيذ

### الموجة 1: قاعدة البيانات والموديلات
1. Migration: `create_attendance_groups_table`
2. Migration: `create_attendance_employees_table`
3. Migration: `create_group_schedules_table`
4. Migration: `create_attendance_shifts_table`
5. Migration: `create_shift_details_table`
6. Migration: `create_time_intervals_table`
7. Migration: `create_break_times_table`
8. Migration: `create_time_interval_break_time_table`
9. Migration: `create_pay_codes_table`
10. Migration: `create_att_codes_table`
11. Migration: `create_group_policies_table`
12. Migration: `create_department_policies_table`
13. Migration: `create_department_schedules_table`
14. Migration: `create_employee_schedules_table`
15. Migration: `create_temporary_schedules_table`
16. Models: جميع الموديلات مع العلاقات والأوسمة
17. Seeders: البيانات الافتراضية

### الموجة 2: الخدمات والمستودعات
1. Repositories: جميع المستودعات
2. Services: AttendanceGroupService
3. Services: AttendanceShiftService
4. Services: TimeIntervalService
5. Services: GroupScheduleService
6. Services: AttendanceCalculationService
7. Services: PayCodeService
8. Services: AttCodeService

### الموجة 3: التحكم والمسارات
1. FormRequests: جميع طلبات التحقق
2. Controllers: AttendanceGroupsController
3. Controllers: AttendanceShiftsController
4. Controllers: GroupSchedulesController
5. Controllers: TimeIntervalsController
6. Routes: جميع المسارات
7. Seeders: الصلاحيات

### الموجة 4: الواجهات
1. Vue Pages: AttendanceGroups (Index, Create, Edit, Show, AssignEmployee)
2. Vue Pages: AttendanceShifts (Index, Create, Edit, Show)
3. Vue Pages: GroupSchedules (Index, Create, Edit, Show)
4. Vue Pages: TimeIntervals (Index, Create, Edit, Show)
5. تعديل: Users/Create.vue (إضافة حقل الفئة)
6. تعديل: Users/Edit.vue (إضافة حقل الفئة)
7. تعديل: Users/Show.vue (إضافة قسم الفئة)
8. Translation: ملفات الترجمة

### الموجة 5: محرك الحساب
1. تنفيذ AttendanceCalculationService
2. ربط RawAttendanceLogService بمحرك الحساب
3. ربط DailyAttendanceSummaryService بمحرك الحساب
4. اختبار الحسابات

### الموجة 6: الاختبارات والتحسين
1. Unit Tests للخدمات
2. Feature Tests للمسارات
3. مراجعة الأداء
4. تنظيف الكود (pint)

---

## الاعتبارات

### الأمان
- Spatie Permission على جميع المسارات
- Validation في Service layer
- لا secrets في الكود
- CSRF protection

### الأداء
- Eager loading لكل العلاقات
- Pagination للقوائم (20 عنصر افتراضياً)
- Cache للفئات والمناوبات (5 دقائق)
- لا N+1 queries

### اللغة والـ RTL
- ترجمة للعربية والإنجليزية
- RTL في جميع المكونات
- useTranslations() composable

### المكونات المشتركة
- DataTable للجداول
- FormInput للحقول
- FormSelect للقوائم المنسدلة
- FormModal للنوافذ المنبثقة
- PageHeader للعناوين
- Badge للحالات
- Alert للرسائل

---

## التحقق من الدستور (Post-Design)

| المادة | الحالة |
|--------|--------|
| II: بنية الوحدات | ✅ يتبع النمط المطلوب |
| III: التسمية | ✅ متوافق |
| IV: قاعدة البيانات | ✅ متوافق |
| V: الأمان | ✅ متوافق |
| VII: المكونات | ✅ يستخدم المكونات المشتركة |
| XIV: التوسع | ✅ DI، Stateless |

**النتيجة:** ✅ التصميم متوافق مع الدستور

---

*آخر تحديث: 2026-07-16*
