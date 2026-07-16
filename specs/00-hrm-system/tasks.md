# تقسيم المهام - إعادة بناء HRM
# Task Breakdown - Rebuild HRM

**الإصدار:** 2.0.0
**تاريخ:** 2026-07-13
**عدد المهام:** 150

---

## 🎯 كيفية استخدام هذه المهام

1. ابدأ من Task 1 وتابع بالترتيب
2. كل مهمة تعتمد على المهام السابقة
3. المهام الموسومة بـ `[P]` يمكن تنفيذها بالتوازي
4. بعد كل مرحلة، شغّل `php artisan migrate` و `php artisan test`
5. استخدم `/speckit.implement` لتنفيذ كل feature

---

# المرحلة 0: التهيئة Foundation (Tasks 1-10)

## Task 1: إنشاء مشروع Laravel
- **التعقيد:** بسيط
- **التبعية:** لا يوجد
- **التعليمات:**
  ```bash
  composer create-project laravel/laravel:^12.0 hrm
  cd hrm
  composer install
  npm install
  ```

## Task 2: إعداد Environment
- **التعقيد:** بسيط
- **التبعية:** Task 1
- **التعليمات:**
  - نسخ `.env.example` إلى `.env`
  - ضبط `DB_CONNECTION=sqlite`
  - ضبط `APP_NAME=HRM`
  - إنشاء `database/database.sqlite`

## Task 3: تثبيت حزم Composer
- **التعقيد:** بسيط
- **التبعية:** Task 1
- **التعليمات:**
  ```bash
  composer require nwidart/laravel-modules:^12.0
  composer require spatie/laravel-permission:^6.21
  composer require inertiajs/inertia-laravel:^3.1
  composer require tightenco/ziggy:^2.6
  composer require barryvdh/laravel-dompdf:^3.1
  composer require mpdf/mpdf:^8.2
  composer require phpoffice/phpspreadsheet:^5.4
  composer require predis/predis:^3.3
  composer require --dev laravel/pint:^1.29
  ```

## Task 4: تثبيت حزم NPM
- **التعقيد:** بسيط
- **التبعية:** Task 1
- **التعليمات:**
  ```bash
  npm install vue@^3.5 @inertiajs/vue3@^3.6
  npm install -D vite@^7.0 tailwindcss@^4.0 @tailwindcss/vite@^4.0
  npm install -D @vitejs/plugin-vue@^6.0 laravel-vite-plugin@^2.0
  npm install -D ziggy-js@^2.6 axios mitt concurrently
  ```

## Task 5: نشر إعدادات الحزم
- **التعقيد:** بسيط
- **التبعية:** Tasks 3-4
- **التعليمات:**
  ```bash
  php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
  php artisan vendor:publish --provider="Nwidart\Modules\LaravelModulesServiceProvider"
  php artisan key:generate
  ```

## Task 6: إعداد Vite + Vue 3 SPA
- **التعقيد:** متوسط
- **التبعية:** Task 4
- **الملفات:**
  - `vite.config.js` - إعداد Vite مع Vue + Tailwind + resolve alias
  - `resources/css/app.css` - استيراد Tailwind + RTL base styles
  - `resources/js/app.js` - Inertia entry point مع lazy loading للـ Pages
  - `resources/js/bootstrap.js` - Axios, CSRF, EventBus, **RTL إلزامي**
  - `resources/views/app.blade.php` - Root template (الوحيد) لـ Inertia + dir=rtl

## Task 7: إعداد AppLayout.vue + SPA Core (RTL)
- **التعقيد:** معقد
- **التبعية:** Task 6
- **الملفات:**
  - `resources/js/Layouts/AppLayout.vue` - تخطيط رئيسي **RTL** (sidebar يمين، محتوى يمين)
  - `resources/js/Pages/Auth/Login.vue` - صفحة تسجيل دخول RTL
  - `resources/js/Pages/Dashboard.vue` - لوحة تحكم RTL
  - `resources/js/Components/LanguageSwitcher.vue` - مكون تبديل اللغة
  - `resources/js/Components/StatCard.vue` - مكون بطاقة إحصائية
  - `resources/js/composables/useTranslations.js` - ترجمة + RTL direction
  - `app/Http/Middleware/HandleInertiaRequests.php` - مشاركة locale + direction
- **ملاحظة:** **كل شيء RTL**: الجداول `text-right`، النماذج `dir="rtl"`، الـ sidebar يمين، الهوامش معكوسة

## Task 8: إعداد Auth (تسجيل الدخول)
- **التعقيد:** متوسط
- **التبعية:** Task 7
- **الملفات:**
  - `app/Http/Controllers/AuthController.php` - login, logout
  - `app/Http/Middleware/SetLocale.php` - تبديل اللغة
  - `routes/web.php` - مسارات auth

## Task 9: إعداد Spec Kit
- **التعقيد:** بسيط
- **التبعية:** لا يوجد
- **التعليمات:**
  ```bash
  uv tool install specify-cli --from git+https://github.com/github/spec-kit.git@latest
  specify init . --integration copilot
  ```

## Task 10: إعداد الترحيلات الأساسية
- **التعقيد:** بسيط
- **التبعية:** Task 5
- **التعليمات:**
  ```bash
  php artisan migrate
  ```

---
`[P]` تعني أنه يمكن تنفيذ المهمة بالتوازي مع غيرها في نفس المرحلة

---

# المرحلة 1: الهيكل التنظيمي (Tasks 11-30)

## [P] Task 11: Companies Migration
- **التعقيد:** بسيط
- **التبعية:** Task 10
- **الملف:** `Modules/Companies/database/migrations/2024_01_01_000001_create_companies_table.php`
- **الحقول:** name, email, phone, address, website, logo, description, status, established_date, tax_number, commercial_number

## [P] Task 12: Company Model
- **التعقيد:** بسيط
- **التبعية:** Task 11
- **الملف:** `Modules/Companies/app/Models/Company.php`
- **المحتوى:** fillable, casts, relations (branches), scopes (active), accessors (logo_url)

## [P] Task 13: Company Repository
- **التعقيد:** متوسط
- **التبعية:** Task 12
- **الملف:** `Modules/Companies/app/Repositories/CompanyRepository.php`
- **المحتوى:** getAll (with filters), findById, create, update, delete

## [P] Task 14: Company Service
- **التعقيد:** متوسط
- **التبعية:** Tasks 12-13
- **الملف:** `Modules/Companies/app/Services/CompanyService.php`
- **المحتوى:** getAllCompanies, createCompany (مع validation ورفع logo), getCompanyById, updateCompany, deleteCompany

## [P] Task 15: Company Controller
- **التعقيد:** متوسط
- **التبعية:** Task 14
- **الملف:** `Modules/Companies/app/Http/Controllers/CompaniesController.php`
- **المحتوى:** index, create, store, show, edit, update, destroy, search

## [P] Task 16: Company Routes
- **التعقيد:** بسيط
- **التبعية:** Task 15
- **الملف:** `Modules/Companies/routes/web.php`
- **المحتوى:** resource routes مع permission middleware

## [P] Task 17: Company Vue Pages
- **التعقيد:** متوسط
- **التبعية:** Task 14
- **الملفات:**
  - `resources/js/Pages/Companies/Index.vue` ← قائمة الشركات (Inertia table)
  - `resources/js/Pages/Companies/Create.vue` ← نموذج إنشاء شركة
  - `resources/js/Pages/Companies/Edit.vue` ← تعديل شركة
  - `resources/js/Pages/Companies/Show.vue` ← عرض شركة
- **ملاحظة:** كل صفحة تستقبل props من Inertia::render() في الـ Controller

## [P] Task 18: Company Translation
- **التعقيد:** بسيط
- **التبعية:** Task 14
- **الملف:** `Modules/Companies/resources/lang/{ar,en}/companies.php`

## [P] Task 19: Branches Migration
- **التعقيد:** بسيط
- **التبعية:** Task 10
- **الملف:** `Modules/Branches/database/migrations/2024_01_01_000002_create_branches_table.php`

## [P] Task 20: Branches Model
- **التعقيد:** بسيط
- **التبعية:** Tasks 12, 19
- **الملف:** `Modules/Branches/app/Models/Branch.php`
- **المحتوى:** fillable, relations (company BelongsTo, departments HasMany, zones BelongsToMany)

## [P] Task 21: Branches Repository
- **التعقيد:** متوسط
- **التبعية:** Task 20
- **الملف:** `Modules/Branches/app/Repositories/BranchRepository.php`

## [P] Task 22: Branches Service
- **التعقيد:** متوسط
- **التبعية:** Tasks 20-21
- **الملف:** `Modules/Branches/app/Services/BranchService.php`

## [P] Task 23: Branches Controller
- **التعقيد:** متوسط
- **التبعية:** Task 22
- **الملف:** `Modules/Branches/app/Http/Controllers/BranchesController.php`

## [P] Task 24: Branches Routes + Vue Pages + Translation
- **التعقيد:** متوسط
- **التبعية:** Tasks 22-23
- **الملفات:**
  - `Modules/Branches/routes/web.php`
  - `resources/js/Pages/Branches/{Index,Create,Edit,Show}.vue`
  - `Modules/Branches/resources/lang/{ar,en}/branches.php`

## [P] Task 25: Departments Migration
- **التعقيد:** بسيط
- **التبعية:** Task 10
- **الملف:** `Modules/Departments/database/migrations/2024_01_01_000006_create_departments_table.php`

## [P] Task 26: Departments Model
- **التعقيد:** بسيط
- **التبعية:** Tasks 20, 25
- **الملف:** `Modules/Departments/app/Models/Department.php`
- **المحتوى:** fillable, relations (branch BelongsTo, users HasMany)

## [P] Task 27: Departments Service + Repository
- **التعقيد:** متوسط
- **التبعية:** Task 26
- **الملفات:**
  - `Modules/Departments/app/Services/DepartmentService.php`
  - `Modules/Departments/app/Repositories/DepartmentRepository.php`

## [P] Task 28: Departments Controller
- **التعقيد:** متوسط
- **التبعية:** Task 27
- **الملف:** `Modules/Departments/app/Http/Controllers/DepartmentsController.php`

## [P] Task 29: Departments Routes + Vue Pages + Translation
- **التعقيد:** متوسط
- **التبعية:** Tasks 27-28
- **الملفات:**
  - `Modules/Departments/routes/web.php`
  - `resources/js/Pages/Departments/{Index,Create,Edit,Show}.vue`
  - `Modules/Departments/resources/lang/{ar,en}/departments.php`

## Task 30: تشغيل ترحيلات المرحلة 1
- **التعقيد:** بسيط
- **التبعية:** Tasks 11-29
- **التعليمات:** `php artisan migrate`

---

# المرحلة 2: الكيانات المستقلة (Tasks 31-50)

## [P] Task 31: Positions Migration
- **التعقيد:** بسيط
- **التبعية:** Task 30
- **الملف:** `Modules/Positions/database/migrations/2024_01_01_000007_create_positions_table.php`

## [P] Task 32: Positions Model + Repository + Service ← NEW (كانت مفقودة)
- **التعقيد:** متوسط
- **التبعية:** Task 31
- **الملفات:**
  - `Modules/Positions/app/Models/Position.php`
  - `Modules/Positions/app/Repositories/PositionRepository.php` ← NEW
  - `Modules/Positions/app/Services/PositionService.php` ← NEW

## [P] Task 33: Positions Controller + Routes + Vue Pages + Translation
- **التعقيد:** متوسط
- **التبعية:** Task 32
- **الملفات:**
  - `Modules/Positions/app/Http/Controllers/PositionsController.php`
  - `Modules/Positions/routes/web.php`
  - `resources/js/Pages/Positions/{Index,Create,Edit,Show}.vue`
  - `Modules/Positions/resources/lang/{ar,en}/positions.php`

## [P] Task 34: Grades Migration
- **التعقيد:** بسيط
- **التبعية:** Task 30
- **الملف:** `Modules/Grades/database/migrations/2024_01_01_000003_create_grades_table.php`

## [P] Task 35: Grades Model + Service + Repository
- **التعقيد:** متوسط
- **التبعية:** Task 34

## [P] Task 36: Grades Controller + Routes + Vue Pages + Translation
- **التعقيد:** متوسط
- **التبعية:** Task 35
- **الملفات:**
  - `Modules/Grades/app/Http/Controllers/GradesController.php`
  - `Modules/Grades/routes/web.php`
  - `resources/js/Pages/Grades/{Index,Create,Edit,Show}.vue`
  - `Modules/Grades/resources/lang/{ar,en}/grades.php`

## [P] Task 37: Shifts Migration
- **التعقيد:** بسيط
- **التبعية:** Task 30
- **الملف:** `Modules/Shifts/database/migrations/2024_01_01_000005_create_shifts_table.php`

## [P] Task 38: Shifts Model + Service + Repository
- **التعقيد:** متوسط
- **التبعية:** Task 37

## [P] Task 39: Shifts Controller + Routes + Vue Pages + Translation
- **التعقيد:** متوسط
- **التبعية:** Task 38
- **الملفات:**
  - `Modules/Shifts/app/Http/Controllers/ShiftsController.php`
  - `Modules/Shifts/routes/web.php`
  - `resources/js/Pages/Shifts/{Index,Create,Edit,Show}.vue`
  - `Modules/Shifts/resources/lang/{ar,en}/shifts.php`

## Task 40: تشغيل ترحيلات المرحلة 2
- **التعقيد:** بسيط
- **التبعية:** Tasks 31-39
- **التعليمات:** `php artisan migrate`

---

# المرحلة 3: المستخدمين (Tasks 41-60) - الوحدة المركزية

## Task 41: Users Migration + Pivot Tables
- **التعقيد:** معقد
- **التبعية:** Task 40
- **الملفات:**
  - `database/migrations/2024_01_01_000009_create_users_table.php`
  - `Modules/Users/database/migrations/2024_01_01_000020_create_user_shifts_table.php`
  - `Modules/Users/database/migrations/2024_01_01_000021_create_user_zone_table.php`

## Task 42: User Model (Central Model)
- **التعقيد:** معقد
- **التبعية:** Task 41
- **الملف:** `Modules/Users/app/Models/User.php`
- **المحتوى:**
  - Extends Authenticatable
  - Traits: HasFactory, HasRoles, Notifiable
  - جميع الحقول القابلة للتعبئة
  - جميع العلاقات (department, position, grade, shift, shifts, vacations, attendanceSessions, fingerprintTemplates, vacationBalances, zones, vacationRequests, company, branch)
  - Accessors و scopes
  - System admin ID 10000 exclusion

## [P] Task 43: User Repository
- **التعقيد:** معقد
- **التبعية:** Task 42
- **الملف:** `Modules/Users/app/Repositories/UserRepository.php`
- **المحتوى:** getAll مع فلاتر، findById، create، update، delete، search، byCompany، byDepartment، byShift، byGrade، bulk operations

## [P] Task 44: User Service
- **التعقيد:** معقد
- **التبعية:** Tasks 42-43
- **الملف:** `Modules/Users/app/Services/UserService.php`
- **المحتوى:** CRUD، إدارة المناوبات، إدارة البصمات، إدارة الإجازات، عمليات مجمعة، بحث متقدم

## [P] Task 45: Users Controller
- **التعقيد:** معقد
- **التبعية:** Task 44
- **الملف:** `Modules/Users/app/Http/Controllers/UsersController.php`
- **المحتوى:** CRUD + بحث + فلاتر + عمليات مجمعة + إدارة المناوبات + إدارة البصمات + إدارة الإجازات

## [P] Task 46: Users Routes
- **التعقيد:** متوسط
- **التبعية:** Task 45
- **الملف:** `Modules/Users/routes/web.php`
- **المحتوى:** جميع مسارات CRUD + بحث + bulk + شفتات + بصمات + إجازات

## [P] Task 47: Users Vue Pages
- **التعقيد:** معقد
- **التبعية:** Task 44
- **الملفات:**
  - `resources/js/Pages/Users/Index.vue` - قائمة الموظفين مع فلاتر وبحث (Inertia table)
  - `resources/js/Pages/Users/Create.vue` - إنشاء موظف (نموذج Vue)
  - `resources/js/Pages/Users/Edit.vue` - تعديل موظف
  - `resources/js/Pages/Users/Show.vue` - عرض موظف مع كل بياناته
  - `resources/js/Pages/Users/Shifts.vue` - إدارة مناوبات الموظف
  - `resources/js/Pages/Users/Fingerprints.vue` - إدارة بصمات الموظف

## [P] Task 48: Users Translation
- **التعقيد:** متوسط
- **التبعية:** Task 44
- **الملف:** `Modules/Users/resources/lang/{ar,en}/users.php`

## Task 49: تشغيل ترحيلات المستخدمين
- **التعقيد:** بسيط
- **التبعية:** Tasks 41-48
- **التعليمات:** `php artisan migrate`

## Task 50: User Seeder (Admin User)
- **التعقيد:** بسيط
- **التبعية:** Task 49
- **الملف:** `database/seeders/UserSeeder.php`
- **المحتوى:** إنشاء مستخدم super-admin برقم 10000

---

# المرحلة 4: الحضور (Tasks 51-85) - الأكثر تعقيداً

## [P] Task 51: Attendance Migrations (3 tables)
- **التعقيد:** متوسط
- **التبعية:** Task 49
- **الملفات:**
  - `create_attendance_sessions_table.php`
  - `create_raw_attendance_logs_table.php`
  - `create_daily_attendance_summaries_table.php`

## [P] Task 52: AttendanceSession Model
- **التعقيد:** بسيط
- **التبعية:** Task 51
- **الملف:** `Modules/Attendance/app/Models/AttendanceSession.php`
- **المحتوى:** relations (user BelongsTo), scopes

## [P] Task 53: DailyAttendanceSummary Model
- **التعقيد:** بسيط
- **التبعية:** Task 51
- **الملف:** `Modules/Attendance/app/Models/DailyAttendanceSummary.php`

## [P] Task 54: RawAttendanceLog Model
- **التعقيد:** بسيط
- **التبعية:** Task 51
- **الملف:** `Modules/Attendance/app/Models/RawAttendanceLog.php`

## [P] Task 55: RawAttendanceLog Repository
- **التعقيد:** متوسط
- **التبعية:** Tasks 52-54
- **الملف:** `Modules/Attendance/app/Repositories/RawAttendanceLogRepository.php`

## [P] Task 56: AttendanceSessionService
- **التعقيد:** معقد
- **التبعية:** Task 55
- **الملف:** `Modules/Attendance/app/Services/AttendanceSessionService.php`
- **المحتوى:** إنشاء جلسات الحضور، إدارة check-in/check-out، التحقق من التكرار

## [P] Task 57: DailyAttendanceSummaryService
- **التعقيد:** معقد
- **التبعية:** Task 56
- **الملف:** `Modules/Attendance/app/Services/DailyAttendanceSummaryService.php`
- **المحتوى:** حساب ساعات العمل، حساب التأخير، حساب انصراف مبكر، حساب overtime

## [P] Task 58: DailyAttendanceAutoCalculationService
- **التعقيد:** معقد
- **التبعية:** Task 57
- **الملف:** `Modules/Attendance/app/Services/DailyAttendanceAutoCalculationService.php`
- **المحتوى:** الحساب التلقائي اليومي

## [P] Task 59: RawAttendanceLogService
- **التعقيد:** متوسط
- **التبعية:** Task 55
- **الملف:** `Modules/Attendance/app/Services/RawAttendanceLogService.php`
- **المحتوى:** استيراد السجلات الخام، معالجتها

## [P] Task 60: AttendanceReportService
- **التعقيد:** معقد
- **التبعية:** Task 57
- **الملف:** `Modules/Attendance/app/Services/AttendanceReportService.php`
- **المحتوى:** تقارير شاملة، أداء موظف، مقارنة أقسام، overtime analysis

## [P] Task 61: MonthlyReportService
- **التعقيد:** معقد
- **التبعية:** Task 57
- **الملف:** `Modules/Attendance/app/Services/MonthlyReportService.php`
- **المحتوى:** تقارير شهرية مع cache

## [P] Task 62: YearlyReportService
- **التعقيد:** معقد
- **التبعية:** Task 57
- **الملف:** `Modules/Attendance/app/Services/YearlyReportService.php`

## [P] Task 63: AttendanceCacheService
- **التعقيد:** متوسط
- **التبعية:** Task 57
- **الملف:** `Modules/Attendance/app/Services/AttendanceCacheService.php`

## [P] Task 64: AttendanceMonitoringService
- **التعقيد:** متوسط
- **التبعية:** Task 56
- **الملف:** `Modules/Attendance/app/Services/AttendanceMonitoringService.php`

## [P] Task 65: AttendanceNotificationService
- **التعقيد:** متوسط
- **التبعية:** Task 56
- **الملف:** `Modules/Attendance/app/Services/AttendanceNotificationService.php`

## [P] Task 66: AttendanceSessionTypeService
- **التعقيد:** متوسط
- **التبعية:** Task 56
- **الملف:** `Modules/Attendance/app/Services/AttendanceSessionTypeService.php`

## [P] Task 67: Controllers (7 controllers)
- **التعقيد:** معقد
- **التبعية:** Tasks 56-66
- **الملفات:**
  - `AttendanceSessionController.php`
  - `DailyAttendanceSummaryController.php`
  - `RawAttendanceLogController.php`
  - `AttendanceReportController.php`
  - `MonthlyReportController.php`
  - `YearlyReportController.php`
  - `LiveAttendanceController.php`

## [P] Task 68: Events + Listeners
- **التعقيد:** متوسط
- **التبعية:** Task 67
- **الملفات:**
  - `Events/AttendanceSessionCreated.php`
  - `Events/AttendanceSessionUpdated.php`
  - `Events/AttendanceSessionDeleted.php`
  - `Listeners/AttendanceSessionEventListener.php`

## [P] Task 69: Jobs
- **التعقيد:** متوسط
- **التبعية:** Task 67
- **الملفات:**
  - `Jobs/ProcessRawAttendanceLogsChunk.php`
  - `Jobs/RecalculateDailySummariesChunk.php`
  - `Jobs/RecalculateDateRangeChunk.php`

## [P] Task 70: Notifications
- **التعقيد:** متوسط
- **التبعية:** Task 67
- **الملفات:**
  - `Notifications/AttendanceIssueNotification.php`
  - `Notifications/MonthlyAttendanceReportNotification.php`
  - `Notifications/WeeklyAttendanceSummaryNotification.php`

## [P] Task 71: Attendance Routes
- **التعقيد:** معقد
- **التبعية:** Tasks 67-70
- **المحتوى:** جميع مسارات attendance مع permission middleware

## [P] Task 72: Attendance Vue Pages
- **التعقيد:** معقد
- **التبعية:** Tasks 67-71
- **الملفات:**
  - `resources/js/Pages/Attendance/Sessions/Index.vue`
  - `resources/js/Pages/Attendance/Sessions/Show.vue`
  - `resources/js/Pages/Attendance/DailySummaries/Index.vue`
  - `resources/js/Pages/Attendance/RawLogs/Index.vue`
  - `resources/js/Pages/Attendance/Reports/Index.vue`
  - `resources/js/Pages/Attendance/Reports/Monthly.vue`
  - `resources/js/Pages/Attendance/Reports/Yearly.vue`
  - `resources/js/Pages/Attendance/Live.vue`

## [P] Task 73: Attendance Translation
- **التعقيد:** متوسط
- **التبعية:** Tasks 67-71

## [P] Task 74: Attendance Support + Exceptions
- **التعقيد:** بسيط
- **التبعية:** Tasks 56-66
- **الملفات:**
  - `Support/TimeFormatter.php`
  - `Exceptions/AttendanceException.php`
  - `Exceptions/AttendanceCalculationException.php`

## Task 75: تشغيل ترحيلات الحضور
- **التعقيد:** بسيط
- **التبعية:** Tasks 51-74
- **التعليمات:** `php artisan migrate`

---

# المرحلة 5: أجهزة البصمة (Tasks 76-95)

## [P] Task 76: FingerprintDevices Migrations
- **التعقيد:** متوسط
- **التبعية:** Task 49
- **الملفات:**
  - `create_fingerprint_device_types_table.php`
  - `create_fingerprint_devices_table.php`

## [P] Task 77: Fingerprint Models (3 models)
- **التعقيد:** بسيط
- **التبعية:** Task 76
- **الملفات:**
  - `Models/FingerprintDevice.php`
  - `Models/FingerprintDeviceType.php`
  - `Models/UserFingerprint.php`

## [P] Task 78: FingerprintRepositories (with interfaces)
- **التعقيد:** متوسط
- **التبعية:** Task 77
- **الملفات:**
  - `Repositories/FingerprintDeviceRepository.php`
  - `Repositories/FingerprintDeviceRepositoryInterface.php`
  - `Repositories/FingerprintDeviceTypeRepository.php`
  - `Repositories/FingerprintDeviceTypeRepositoryInterface.php`

## [P] Task 79: ZKTecoAdapter
- **التعقيد:** معقد
- **التبعية:** Task 78
- **الملف:** `Services/ZKTecoAdapter.php`
- **المحتوى:** الاتصال بجهاز ZKTeco، إرسال/استقبال بيانات

## [P] Task 80: DeviceSdkInterface
- **التعقيد:** بسيط
- **التبعية:** Task 79
- **الملف:** `Services/Contracts/DeviceSdkInterface.php`

## [P] Task 81: FingerprintDeviceService
- **التعقيد:** معقد
- **التبعية:** Tasks 78-80
- **الملف:** `Services/FingerprintDeviceService.php`
- **المحتوى:** CRUD، test connection، sync attendance، export users

## [P] Task 82: MasterFingerprintService
- **التعقيد:** متوسط
- **التبعية:** Task 78
- **الملف:** `Services/MasterFingerprintService.php`

## [P] Task 83: Controllers (7 controllers)
- **التعقيد:** معقد
- **التبعية:** Tasks 81-82
- **الملفات:**
  - `FingerprintDeviceController.php`
  - `FingerprintDeviceTypeController.php`
  - `DashboardController.php`
  - `DeviceMonitoringController.php`
  - `DevicePushController.php`
  - `AdmsPushController.php`

## [P] Task 84: Middleware (3 middleware)
- **التعقيد:** متوسط
- **التبعية:** Task 83
- **الملفات:**
  - `Middleware/DeviceAccessMiddleware.php`
  - `Middleware/DeviceConnectionMiddleware.php`
  - `Middleware/DeviceTypeAccessMiddleware.php`

## [P] Task 85: Jobs
- **التعقيد:** متوسط
- **التبعية:** Task 83
- **الملفات:**
  - `Jobs/ExportFingerprintTemplatesJob.php`
  - `Jobs/ImportAllFingerprintTemplatesJob.php`
  - `Jobs/ProcessUserImportChunk.php`

## [P] Task 86: Fingerprint Routes
- **التعقيد:** متوسط
- **التبعية:** Tasks 83-85

## [P] Task 87: Fingerprint Vue Pages + Translation
- **التعقيد:** متوسط
- **التبعية:** Tasks 83-86
- **الملفات:**
  - `resources/js/Pages/FingerprintDevices/Index.vue`
  - `resources/js/Pages/FingerprintDevices/Create.vue`
  - `resources/js/Pages/FingerprintDevices/Edit.vue`
  - `resources/js/Pages/FingerprintDevices/Show.vue`
  - `resources/js/Pages/FingerprintDevices/Dashboard.vue`
  - `Modules/FingerprintDevices/resources/lang/{ar,en}/fingerprint_devices.php`

## Task 88: تشغيل ترحيلات الأجهزة
- **التعقيد:** بسيط
- **التبعية:** Tasks 76-87
- **التعليمات:** `php artisan migrate`

---

# المرحلة 6: الإجازات والعطل (Tasks 89-105)

## [P] Task 89: Vacations Migrations
- **التعقيد:** متوسط
- **التبعية:** Task 49
- **الملفات:**
  - `create_vacations_table.php`
  - `create_user_vacation_request_table.php`
  - `create_user_vacation_balance_table.php`
  - `create_user_vacation_balance_transactions_table.php`

## [P] Task 90: Vacation Models (4 models)
- **التعقيد:** بسيط
- **التبعية:** Task 89
- **الملفات:**
  - `Models/Vacation.php`
  - `Models/UserVacationRequest.php`
  - `Models/UserVacationBalance.php`
  - `Models/UserVacationBalanceTransaction.php`

## [P] Task 91: Vacation Service + Repository
- **التعقيد:** معقد
- **التبعية:** Task 90
- **المحتوى:** إنشاء طلب إجازة، الموافقة/الرفض، حساب الرصيد، ترحيل الرصيد السنوي

## [P] Task 92: Vacation Controller + Routes
- **التعقيد:** متوسط
- **التبعية:** Task 91

## [P] Task 93: Vacation Vue Pages + Translation
- **التعقيد:** متوسط
- **التبعية:** Task 91
- **الملفات:**
  - `resources/js/Pages/Vacations/Index.vue`
  - `resources/js/Pages/Vacations/Create.vue`
  - `resources/js/Pages/Vacations/Show.vue`
  - `resources/js/Pages/Vacations/Requests.vue`
  - `Modules/Vacations/resources/lang/{ar,en}/vacations.php`

## [P] Task 94: Holidays Migrations
- **التعقيد:** بسيط
- **التبعية:** Task 49
- **الملفات:**
  - `create_holidays_table.php`
  - `create_holiday_yearly_dates_table.php`
  - `create_holiday_groups_table.php`

## [P] Task 95: Holiday Models
- **التعقيد:** بسيط
- **التبعية:** Task 94
- **الملف:** `Modules/Holidays/app/Models/Holiday.php`

## [P] Task 96: Holiday Service + Repository
- **التعقيد:** متوسط
- **التبعية:** Task 95

## [P] Task 97: Holiday Controller + Routes + Vue Pages + Translation
- **التعقيد:** متوسط
- **التبعية:** Task 96
- **الملفات:**
  - `Modules/Holidays/app/Http/Controllers/HolidaysController.php`
  - `Modules/Holidays/routes/web.php`
  - `resources/js/Pages/Holidays/{Index,Create,Edit}.vue`
  - `Modules/Holidays/resources/lang/{ar,en}/holidays.php`

## Task 98: تشغيل ترحيلات الإجازات
- **التعقيد:** بسيط
- **التبعية:** Tasks 89-97
- **التعليمات:** `php artisan migrate`

---

# المرحلة 7: المناطق (Tasks 99-108)

## [P] Task 99: Zones Migration + Pivot
- **التعقيد:** بسيط
- **التبعية:** Task 49
- **الملفات:**
  - `create_zones_table.php`
  - `create_zone_branches_table.php`

## [P] Task 100: Zone Model (نقل من Entities/ إلى Models/)
- **التعقيد:** بسيط
- **التبعية:** Task 99
- **الملف:** `Modules/Zones/app/Models/Zone.php` ← NEW location
- **ملاحظة:** حذف الملف القديم `Entities/Zone.php`

## [P] Task 101: Zone Repository + Service ← NEW
- **التعقيد:** متوسط
- **التبعية:** Task 100
- **الملفات:**
  - `Modules/Zones/app/Repositories/ZoneRepository.php` ← NEW
  - `Modules/Zones/app/Services/ZoneService.php` ← NEW

## [P] Task 102: Zone Controller + Routes
- **التعقيد:** متوسط
- **التبعية:** Task 101

## [P] Task 103: Zone Vue Pages + Translation
- **التعقيد:** متوسط
- **التبعية:** Task 101
- **الملفات:**
  - `resources/js/Pages/Zones/{Index,Create,Edit}.vue`
  - `Modules/Zones/resources/lang/{ar,en}/zones.php`

## Task 104: تشغيل ترحيلات المناطق
- **التعقيد:** بسيط
- **التبعية:** Tasks 99-103

---

# المرحلة 8: الإعدادات والأدوار (Tasks 105-115)

## [P] Task 105: Settings Migration
- **التعقيد:** بسيط
- **التبعية:** Task 49
- **الملف:** `create_settings_table.php`

## [P] Task 106: Setting Model
- **التعقيد:** بسيط
- **التبعية:** Task 105
- **الملف:** `Modules/Settings/app/Models/Setting.php`

## [P] Task 107: Setting Service + Repository
- **التعقيد:** متوسط
- **التبعية:** Task 106

## [P] Task 108: Settings Controller + Routes + Vue Page + Translation
- **التعقيد:** متوسط
- **التبعية:** Task 107
- **الملفات:**
  - `Modules/Settings/app/Http/Controllers/SettingsController.php`
  - `Modules/Settings/routes/web.php`
  - `resources/js/Pages/Settings/Index.vue`
  - `Modules/Settings/resources/lang/{ar,en}/settings.php`

## [P] Task 109: Roles & Permissions Configuration
- **التعقيد:** متوسط
- **التبعية:** Task 49
- **الملفات:**
  - `config/permissions.php` - تعريف جميع الصلاحيات
  - `config/permission.php` - إعدادات Spatie

## [P] Task 110: RoleAndPermissionSeeder
- **التعقيد:** متوسط
- **التبعية:** Task 109
- **الملف:** `database/seeders/RoleAndPermissionSeeder.php`
- **المحتوى:** إنشاء الأدوار (super-admin, admin, manager, employee) وجميع الصلاحيات

## [P] Task 111: RoleController
- **التعقيد:** متوسط
- **التبعية:** Task 110
- **الملف:** `app/Http/Controllers/RoleController.php`

## [P] Task 112: PermissionController
- **التعقيد:** بسيط
- **التبعية:** Task 110
- **الملف:** `app/Http/Controllers/PermissionController.php`

---

# المرحلة 9: الوحدات الإضافية (Tasks 116-135)

## [P] Task 116: ShiftRotation Module
- **التعقيد:** معقد
- **التبعية:** Tasks 49-50
- **المسار:** `Modules/ShiftRotation/`
- **المحتوى:** تناوب المناوبات مع ScheduleEntry

## [P] Task 117: Payroll Module
- **التعقيد:** معقد
- **التبعية:** Tasks 49-50
- **المسار:** `Modules/Payroll/`

## [P] Task 118: Visitor Module
- **التعقيد:** متوسط
- **التبعية:** Tasks 49-50
- **المسار:** `Modules/Visitor/`

## [P] Task 119: Meeting Module
- **التعقيد:** متوسط
- **التبعية:** Tasks 49-50
- **المسار:** `Modules/Meeting/`

## [P] Task 120: AccessControl Module
- **التعقيد:** متوسط
- **التبعية:** Tasks 49-50
- **المسار:** `Modules/AccessControl/`

## [P] Task 121: Workflow Module
- **التعقيد:** معقد
- **التبعية:** Tasks 49-50
- **المسار:** `Modules/Workflow/`

## [P] Task 122: Mobile Module (API)
- **التعقيد:** معقد
- **التبعية:** Tasks 49-50
- **المسار:** `Modules/Mobile/`

## [P] Task 123: Sync Module
- **التعقيد:** متوسط
- **التبعية:** Tasks 49-50
- **المسار:** `Modules/Sync/`

---

# المرحلة 10: الأوامر والخدمات (Tasks 124-135)

## [P] Task 124: ZKTecoServiceCommand
- **التعقيد:** متوسط
- **التبعية:** Task 88
- **الملف:** `app/Console/Commands/ZKTecoServiceCommand.php`
- **المحتوى:** start, stop, status

## [P] Task 125: Attendance Commands (16 commands)
- **التعقيد:** معقد
- **التبعية:** Task 75
- **الملفات:** `Modules/Attendance/app/Console/Commands/*.php`

## [P] Task 126: General Commands
- **التعقيد:** متوسط
- **التبعية:** Task 49
- **الملفات:**
  - `app/Console/Commands/PullFingerprintsFromDevices.php`
  - `app/Console/Commands/RealtimeFingerprintPull.php`
  - `app/Console/Commands/ProcessMasterFingerprints.php`
  - وغيرها

## Task 127: إنشاء ملف requirements.txt + هيكل المجلد
- **التعقيد:** بسيط
- **التبعية:** لا يوجد
- **الملفات:**
  - `zkteco-service/requirements.txt`
  - `zkteco-service/start.bat`
  - `zkteco-service/start.sh`
  - `zkteco-service/install-deps.bat`
- **المحتوى:**
  ```txt
  flask==3.0.0
  flask-cors==4.0.0
  pyzk==0.9
  python-dotenv==1.0.0
  requests==2.31.0
  ```

## Task 128: إنشاء Flask App - ZKTecoService Class
- **التعقيد:** معقد
- **التبعية:** Task 127
- **الملف:** `zkteco-service/app.py`
- **المحتوى (550 سطر):**
  - Class `ZKTecoService` مع:
    - `__init__(ip, port=4370, password=0, timeout=300, force_udp=None, ommit_ping=None)`
    - `connect()` - اتصال TCP مع fallback إلى UDP
    - `disconnect()` - قطع الاتصال
    - `get_attendance()` - سحب سجلات الحضور (الأهم)
    - `get_users()` - سحب المستخدمين
    - `add_user(uid, user_id, name, ...)` - إضافة مستخدم
    - `add_users_batch(users_data)` - إضافة مجمعة مع UID management
    - `delete_user(uid)` - حذف مستخدم
    - `get_fingerprint_templates(uid)` - سحب بصمات مستخدم
    - `get_all_templates()` - سحب كل البصمات
    - `export_template(uid, finger_id, template_data)` - رفع بصمة
    - `clear_attendance()` - مسح السجلات
    - `get_device_info()` - معلومات الجهاز
    - `test_template_upload_support()` - اختبار رفع البصمات

## Task 129: إنشاء Flask Endpoints
- **التعقيد:** معقد
- **التبعية:** Task 128
- **الملف:** `zkteco-service/app.py` (يضاف إلى نفس الملف - 542 سطر إضافي)
- **المحتوى:**
  - `GET /health` ← فحص الخدمة
  - `POST /device/test-connection` ← اختبار اتصال
  - `POST /device/get-attendance` ← سحب الحضور (مع handling لـ disconnect warning)
  - `POST /device/get-users` ← سحب المستخدمين
  - `POST /device/get-templates` ← سحب البصمات (للكل أو لـ uid)
  - `POST /device/add-user` ← إضافة مستخدم
  - `POST /device/add-users-batch` ← إضافة مجمعة
  - `POST /device/delete-user` ← حذف مستخدم
  - `POST /device/export-template` ← رفع بصمة
  - `POST /device/export-templates-batch` ← رفع بصمات متعددة
  - `POST /device/clear-attendance` ← مسح السجلات
  - `POST /device/info` ← معلومات الجهاز
  - `POST /device/adms-config` ← إعدادات ADMS

## Task 130: إنشاء ADMS TCP Server
- **التعقيد:** معقد
- **التبعية:** Task 127
- **الملف:** `zkteco-service/adms_server.py`
- **المحتوى:**
  - Class `ADMServer` - خادم TCP على port 8081
  - Class `ZKTecoADMSHandler` - معالجة اتصال جهاز واحد
  - `parse_adms_data()` - تحليل بيانات ADMS (نص/ثنائي)
  - `parse_text_format()` - تحليل تنسيق نصي tab-separated
  - `parse_binary_format()` - تحليل تنسيق ثنائي (struct)
  - `save_attendance_log()` - حفظ السجل في DB عبر `php artisan tinker`
  - `find_device_id_by_ip()` - إيجاد device_id من IP

## Task 131: إنشاء ZKTecoPythonBridgeService.php (Laravel Bridge)
- **التعقيد:** معقد
- **التبعية:** Task 129
- **الملف:** `app/Services/ZKTecoPythonBridgeService.php`
- **المحتوى (525 سطر):**
  - `__construct()` - URL الخدمة + timeout + تشغيل تلقائي
  - `ensureServiceRunning()` - تشغيل Python تلقائياً إذا كان متوقفاً
  - `isAvailable()` - فحص الصحة
  - `testConnection(ip, port, password)` - اختبار اتصال
  - `getAttendance(ip, port, password, timeout, forceUdp, ommitPing)` - سحب الحضور (مع handling للـ disconnect)
  - `getUsers(ip, port, password)` - سحب المستخدمين
  - `getTemplates(ip, port, password, uid)` - سحب البصمات
  - `getDeviceInfo(ip, port, password)` - معلومات الجهاز
  - `addUser(ip, port, password, uid, userId, name, ...)` - إضافة مستخدم
  - `addUsersBatch(ip, port, password, usersData)` - إضافة مجمعة
  - `deleteUser(ip, port, password, uid)` - حذف مستخدم
  - `exportTemplate(ip, ...)` - رفع بصمة
  - `exportTemplatesBatch(ip, ...)` - رفع بصمات متعددة
  - `clearAttendance(ip, port, password)` - مسح السجلات

## Task 132: إنشاء ZKTecoServiceCommand (Artisan)
- **التعقيد:** متوسط
- **التبعية:** Task 131
- **الملف:** `app/Console/Commands/ZKTecoServiceCommand.php`
- **المحتوى:**
  - `php artisan zkteco:service start` ← تشغيل Python
  - `php artisan zkteco:service stop` ← إيقاف Python
  - `php artisan zkteco:service status` ← حالة Python

## Task 133: إنشاء Config للخدمة
- **التعقيد:** بسيط
- **التبعية:** Task 131
- **الملف:** `config/services.php` (إضافة zkteco_python config)
- **المحتوى:**
  ```php
  'zkteco_python' => [
      'url' => env('ZKTECO_PYTHON_SERVICE_URL', 'http://localhost:5000'),
      'port' => env('ZKTECO_PYTHON_SERVICE_PORT', 5000),
      'timeout' => env('ZKTECO_PYTHON_SERVICE_TIMEOUT', 60),
  ],
  ```

## Task 134: إضافة متغيرات البيئة
- **التعقيد:** بسيط
- **التبعية:** Task 133
- **الملف:** `.env`
- **المحتوى:**
  ```
  ZKTECO_PYTHON_SERVICE_ENABLED=true
  ZKTECO_PYTHON_SERVICE_URL=http://localhost:5000
  ZKTECO_PYTHON_SERVICE_TIMEOUT=60
  ZKTECO_PYTHON_SERVICE_OS=windows
  ```

## Task 135: اختبار الخدمة
- **التعقيد:** متوسط
- **التبعية:** Tasks 127-134
- **اختبار يدوي:**
  ```bash
  # 1. تشغيل الخدمة
  cd zkteco-service
  python -m venv venv
  pip install -r requirements.txt
  python app.py &
  
  # 2. اختبار الصحة
  curl http://localhost:5000/health
  
  # 3. اختبار من Laravel
  php artisan zkteco:service status
  
  # 4. اختبار اتصال جهاز
  php artisan tinker
  >>> $bridge = app(App\Services\ZKTecoPythonBridgeService::class);
  >>> $bridge->testConnection('192.168.1.100', 4370, 0);
  ```

---

# المرحلة 11: التكامل النهائي (Tasks 136-150)

## [P] Task 136: RamadanDates Migration + Seeder
- **التعقيد:** بسيط
- **التبعية:** Task 49
- **الملفات:**
  - `create_ramadan_dates_table.php`
  - `RamadanDatesSeeder.php`

## [P] Task 137: Performance Indexes Migration
- **التعقيد:** بسيط
- **التبعية:** Task 75
- **الملف:** `add_performance_indexes_for_attendance_calculation.php`

## [P] Task 138: DatabaseSeeder
- **التعقيد:** متوسط
- **التبعية:** كل seeders
- **الملف:** `database/seeders/DatabaseSeeder.php`
- **المحتوى:** استدعاء جميع seeders بالترتيب الصحيح

## [P] Task 139: FingerprintTemplate Model + Controller
- **التعقيد:** متوسط
- **التبعية:** Task 49
- **الملف:** `app/Models/FingerprintTemplate.php`
- **الملف:** `app/Http/Controllers/FingerprintTemplateController.php`

## [P] Task 140: Vacation Controller (Core)
- **التعقيد:** متوسط
- **التبعية:** Task 92
- **الملف:** `app/Http/Controllers/VacationController.php`

## [P] Task 141: LanguageController
- **التعقيد:** بسيط
- **التبعية:** Task 8
- **الملف:** `app/Http/Controllers/LanguageController.php`

## [P] Task 142: Dashboard Controller + View
- **التعقيد:** متوسط
- **التبعية:** Task 49
- **الملف:** `app/Http/Controllers/DashboardController.php`

## [P] Task 143: HandleInertiaRequests Middleware
- **التعقيد:** بسيط
- **التبعية:** Task 6
- **الملف:** `app/Http/Middleware/HandleInertiaRequests.php`

## Task 144: Run All Migrations
- **التعقيد:** بسيط
- **التبعية:** Tasks 30, 40, 49, 75, 88, 98, 104, جميع المهام السابقة
- **التعليمات:**
  ```bash
  php artisan migrate:fresh
  php artisan db:seed
  ```

## Task 145: Write Unit Tests for Services
- **التعقيد:** معقد
- **التبعية:** Task 144
- **الملفات:** `tests/Unit/*.php`, `Modules/*/tests/Unit/*.php`
- **المحتوى:** اختبار جميع الخدمات الرئيسية

## Task 146: Write Feature Tests for Controllers
- **التعقيد:** معقد
- **التبعية:** Task 144
- **الملفات:** `tests/Feature/*.php`, `Modules/*/tests/Feature/*.php`

## Task 147: Run Tests & Fix
- **التعقيد:** متوسط
- **التبعية:** Tasks 145-146
- **التعليمات:**
  ```bash
  php artisan test
  # Fix any failures
  ```

## Task 148: Run Pint for Code Style
- **التعقيد:** بسيط
- **التبعية:** Task 147
- **التعليمات:**
  ```bash
  php artisan pint
  ```

## Task 149: Verify All Routes
- **التعقيد:** بسيط
- **التبعية:** Task 144
- **التعليمات:**
  ```bash
  php artisan route:list
  # Verify all routes are present and correct
  ```

## Task 150: Final Smoke Test
- **التعقيد:** بسيط
- **التبعية:** Tasks 147-149
- **قائمة التحقق:**
  - [ ] `php artisan serve` يعمل
  - [ ] `npm run dev` يعمل
  - [ ] صفحة تسجيل الدخول تظهر
  - [ ] لوحة التحكم تظهر بعد تسجيل الدخول
  - [ ] جميع وحدات CRUD تعمل
  - [ ] التبديل بين العربي والإنجليزي يعمل
  - [ ] RTL يعمل للعربية
  - [ ] `php artisan test` يمر بنجاح
  - [ ] `php artisan pint` يمر بنجاح

---

## 📊 ملخص المهام

| المرحلة | المهام | العدد |
|---------|--------|-------|
| 0: Foundation (SPA) | 1-10 | 10 |
| 1: الهيكل التنظيمي | 11-30 | 20 |
| 2: الكيانات المستقلة | 31-40 | 10 |
| 3: المستخدمين | 41-50 | 10 |
| 4: الحضور | 51-75 | 25 |
| 5: أجهزة البصمة | 76-88 | 13 |
| 6: الإجازات والعطل | 89-98 | 10 |
| 7: المناطق | 99-104 | 6 |
| 8: الإعدادات والأدوار | 105-115 | 11 |
| 9: الوحدات الإضافية | 116-123 | 8 |
| 10: الأوامر والخدمات | 124-126 | 3 |
| **ZS: Python Service** | **127-135** | **9** |
| 11: التكامل النهائي | 136-150 | 15 |
| **المجموع** | | **159** |

---

## 🚀 كيفية البدء

```bash
# 1. شغّل الـ AI agent في مجلد المشروع
cd hrm

# 2. ابدأ بتنفيذ المهام بالتسلسل
/speckit.implement Task 1: Create Laravel project

# 3. بعد كل مرحلة، تحقق من النتائج
php artisan test
php artisan pint
```

---

*نهاية تقسيم المهام*
