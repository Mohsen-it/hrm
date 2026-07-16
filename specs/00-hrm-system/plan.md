# خطة التنفيذ الكاملة - إعادة بناء HRM
# Complete Implementation Plan - Rebuild HRM

**الإصدار:** 2.0.0
**تاريخ:** 2026-07-13
**التقنية:** Laravel 13 + Inertia.js 3 + Vue 3 (SPA) + Tailwind CSS 4.3 + nwidart/laravel-modules

---

## 📋 نظرة عامة على الخطة

هذه الخطة تغطي إعادة بناء نظام HRM من الصفر باتباع Spec-Driven Development. 
البناء يتم على **10 مراحل**، كل مرحلة تبني على التي تسبقها.

---

## 🏗️ المرحلة 0: تهيئة المشروع (Foundation)

### 0.1 إنشاء مشروع Laravel
```bash
composer create-project laravel/laravel:^12.0 hrm
cd hrm
```

### 0.2 إعداد البيئة
```bash
# ملف .env
APP_NAME=HRM
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=sqlite
# DB_CONNECTION=mysql (production)

SESSION_DRIVER=file
QUEUE_CONNECTION=sync
CACHE_STORE=file

# Redis (اختياري)
# REDIS_CLIENT=predis
# REDIS_HOST=127.0.0.1
# REDIS_PORT=6379
```

### 0.3 تثبيت الحزم
```bash
composer require nwidart/laravel-modules:^12.0
composer require spatie/laravel-permission:^6.21
composer require inertiajs/inertia-laravel:^3.1
composer require tightenco/ziggy:^2.6
composer require barryvdh/laravel-dompdf:^3.1
composer require mpdf/mpdf:^8.2
composer require phpoffice/phpspreadsheet:^5.4
composer require predis/predis:^3.3

composer require --dev phpunit/phpunit:^11.5
composer require --dev laravel/pint:^1.29
composer require --dev fakerphp/faker:^1.23

npm install vue@^3.5 @inertiajs/vue3@^3.6
npm install -D vite@^7.0 tailwindcss@^4.0 @tailwindcss/vite@^4.0
npm install -D @vitejs/plugin-vue@^6.0 laravel-vite-plugin@^2.0
npm install ziggy-js@^2.6 axios mitt concurrently
```

### 0.4 نشر إعدادات الحزم
```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Nwidart\Modules\LaravelModulesServiceProvider"
php artisan module:make Companies
# ... والبقية
```

### 0.5 إعداد Spec Kit
```bash
# تثبيت Specify CLI
uv tool install specify-cli --from git+https://github.com/github/spec-kit.git@latest

# تهيئة المشروع
specify init . --integration gemini
```

### 0.6 إعداد Vite + Vue 3 SPA
```javascript
// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
```

### 0.7 هيكل الـ SPA الأساسي
```
resources/
├── views/
│   └── app.blade.php          ← القالب الوحيد (Inertia root)
├── js/
│   ├── app.js                 ← Inertia entry point مع lazy loading
│   ├── bootstrap.js           ← Axios, CSRF, EventBus, RTL
│   ├── ziggy.js               ← Routes (generated)
│   ├── Layouts/
│   │   └── AppLayout.vue      ← التخطيط الرئيسي (sidebar + navbar)
│   ├── Pages/                 ← صفحات Vue (تتحل تلقائياً)
│   │   ├── Auth/
│   │   │   └── Login.vue
│   │   └── Dashboard.vue
│   ├── Components/            ← مكونات مشتركة
│   └── composables/           ← Vue composables
└── css/
    └── app.css                ← Tailwind imports
```

### 0.8 Inertia Entry Point (app.js)
```javascript
import './bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'HRM';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(
        `./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')
    ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: { color: '#054239', showSpinner: true },
});
```

### 0.9 HandleInertiaRequests Middleware
```php
// app/Http/Middleware/HandleInertiaRequests.php
// يمرر بيانات مشتركة لكل الصفحات:
// - auth.user (المستخدم الحالي)
// - auth.permissions (صلاحياته)
// - app.locale (اللغة الحالية)
// - app.direction (RTL/LTR)
// - app.translations (ترجمة للـ JS)
// - flash (رسائل النجاح/الخطأ)
```

### 0.10 Controllers تستخدم Inertia
```php
// ✅ SPA - Inertia::render()
public function index()
{
    return Inertia::render('Companies/Index', [
        'companies' => $this->companyService->getAllCompanies(request()),
    ]);
}

// ❌ لم نعد نستخدم view() لصفحات النظام
// return view('companies::companies.index', ...); → ملغي
```

### 0.12 RTL إلزامي - إعداد Bootstrap
```javascript
// resources/js/bootstrap.js
import axios from 'axios';
import mitt from 'mitt';

window.EventBus = mitt();

// RTL إلزامي
document.documentElement.dir = 'rtl';
document.documentElement.lang = 'ar';

// Axios - لغة عربية افتراضية
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept-Language'] = 'ar';

// CSRF Token
let token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}
```

### 0.13 RTL إلزامي - CSS
```css
/* resources/css/app.css */
@import "tailwindcss";

/* RTL Base */
[dir="rtl"] {
    direction: rtl;
    text-align: right;
}
[dir="rtl"] .rtl-flip { transform: scaleX(-1); }

/* Arabic Font */
.font-arabic { font-family: 'Tajawal', 'Cairo', sans-serif; }
```

### 0.14 RTL إلزامي - HandleInertiaRequests
```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'auth' => ['user' => $request->user()],
        'locale' => app()->getLocale(),      // 'ar' افتراضياً
        'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
        'translations' => [
            'ar' => __('messages', [], 'ar'),
        ],
    ];
}
```

### 0.11 قاعدة البيانات
```bash
touch database/database.sqlite
php artisan migrate
```

---

## 📦 المرحلة 1: الهيكل التنظيمي (Organization Structure)

### 1.1 Companies Module
**الأولوية:** الأعلى (لا يعتمد على أي وحدة)

#### الملفات المطلوبة:
```
Modules/Companies/
├── app/
│   ├── Http/Controllers/CompaniesController.php
│   ├── Models/Company.php
│   ├── Services/CompanyService.php
│   ├── Repositories/CompanyRepository.php
│   └── Providers/
│       ├── CompaniesServiceProvider.php
│       ├── RouteServiceProvider.php
│       └── EventServiceProvider.php
├── config/config.php
├── database/
│   ├── migrations/2024_01_01_000001_create_companies_table.php
│   ├── factories/CompanyFactory.php
│   └── seeders/CompanySeeder.php
├── routes/web.php
├── tests/
│   ├── Feature/CompaniesFeatureTest.php
│   └── Unit/CompanyServiceTest.php
├── composer.json
└── module.json

# صفحات Vue (في resources/js/Pages/Companies/)
resources/js/Pages/Companies/
├── Index.vue            ← قائمة الشركات
├── Create.vue           ← إنشاء شركة
├── Edit.vue             ← تعديل شركة
└── Show.vue             ← عرض شركة
```

#### Company Model
```php
// fillable: name, email, phone, address, website, logo, description, status, established_date, tax_number, commercial_number
// relations: branches() HasMany
// scopes: scopeActive()
// accessors: getLogoUrlAttribute()
```

#### CompanyService
```php
// getAllCompanies(Request) - مع فلاتر
// createCompany(array) - مع validation ورفع logo
// getCompanyById(int)
// updateCompany(int, array)
// deleteCompany(int)
// validateCompanyData(array, ?int)
// uploadLogo(UploadedFile)
```

#### CompanyController
```php
// index(Request), create(), store(Request), show(int), edit(int), update(Request, int), destroy(int), search(Request)
// all decorated with permission middleware
```

### 1.2 Branches Module
**التبعية:** Companies

```
Modules/Branches/
├── app/Http/Controllers/BranchesController.php
├── app/Models/Branch.php
├── app/Services/BranchService.php
├── app/Repositories/BranchRepository.php
├── database/migrations/
├── routes/web.php
└── tests/

# صفحات Vue:
resources/js/Pages/Branches/
├── Index.vue
├── Create.vue
├── Edit.vue
└── Show.vue
```

#### Branch Model
```php
// fillable: company_id, name, code, address, phone, email, latitude, longitude, status
// relations: company() BelongsTo, departments() HasMany, zones() BelongsToMany
// scopes: scopeActive()
```

### 1.3 Departments Module
**التبعية:** Branches

```
Modules/Departments/
├── Controllers/DepartmentsController.php
├── Models/Department.php
├── Services/DepartmentService.php
├── Repositories/DepartmentRepository.php
├── database/migrations/
├── routes/web.php
└── tests/

# صفحات Vue:
resources/js/Pages/Departments/
├── Index.vue
├── Create.vue
├── Edit.vue
└── Show.vue
```

#### Department Model
```php
// fillable: branch_id, name, code, status
// relations: branch() BelongsTo, users() HasMany
// scopes: scopeActive()
```

---

## 📦 المرحلة 2: الكيانات المستقلة (Standalone Entities)

### 2.1 Positions Module
**حالة خاصة:** يحتاج Service + Repository (موجود Controller فقط حالياً)

```
Modules/Positions/
├── Controllers/PositionsController.php
├── Models/Position.php
├── Services/PositionService.php          ← NEW
├── Repositories/PositionRepository.php  ← NEW
├── database/migrations/
├── routes/web.php
└── tests/

# صفحات Vue:
resources/js/Pages/Positions/
├── Index.vue
├── Create.vue
├── Edit.vue
└── Show.vue
```

### 2.2 Grades Module
```
Modules/Grades/
├── Controllers/GradesController.php
├── Models/Grade.php
├── Services/GradeService.php
├── Repositories/GradeRepository.php
├── database/migrations/
├── routes/web.php
└── tests/

# صفحات Vue:
resources/js/Pages/Grades/
├── Index.vue
├── Create.vue
├── Edit.vue
└── Show.vue
```

### 2.3 Shifts Module
```
Modules/Shifts/
├── Controllers/ShiftsController.php
├── Models/Shift.php
├── Services/ShiftService.php
├── Repositories/ShiftRepository.php
├── database/migrations/
├── routes/web.php
└── tests/

# صفحات Vue:
resources/js/Pages/Shifts/
├── Index.vue
├── Create.vue
├── Edit.vue
└── Show.vue
```

#### Shift Model
```php
// fillable: name, code, start_time, end_time, description, status
// relations: users() BelongsToMany (pivot: user_shifts)
```

---

## 📦 المرحلة 3: المستخدمين (Users) - الوحدة المركزية

**التبعية:** Companies, Branches, Departments, Positions, Grades, Shifts

### 3.1 Users Module
```
Modules/Users/
├── Controllers/UsersController.php
├── Models/User.php                      ← النموذج المركزي (مشترك)
├── Services/UserService.php
├── Repositories/UserRepository.php
├── database/migrations/
├── routes/web.php
└── tests/

# صفحات Vue:
resources/js/Pages/Users/
├── Index.vue
├── Create.vue
├── Edit.vue
├── Show.vue
├── Shifts.vue
└── Fingerprints.vue
```

#### User Model (Central)
```php
// Extends: Authenticatable
// Traits: HasFactory, HasRoles (Spatie), Notifiable
// fillable: id, employee_id, name, name_ar, email, password, phone, department_id, position_id, grade_id, shift_id, shift_category_id, rotation_start_date, hire_date, status, address, master_fingerprint, uid, vacation_count, zone_ids
// casts: password => hashed
// hidden: password, remember_token

// Relations:
// - department() BelongsTo
// - position() BelongsTo
// - grade() BelongsTo
// - shift() BelongsTo
// - scheduleEntries() HasMany
// - shifts() BelongsToMany (user_shifts)
// - vacations() HasMany
// - attendanceSessions() HasMany
// - fingerprintTemplates() HasMany
// - vacationBalances() HasMany
// - zones() BelongsToMany (user_zone)
// - vacationRequests() HasMany
// - company (accessor via department->branch->company)
// - branch (accessor via department->branch)

// Scopes:
// - scopeExcludeSystemAdmin()
// - scopeActive()
// - scopeByDepartment()

// Statuses: active, inactive, suspended
```

#### User Features
- CRUD كامل مع بحث وفلاتر
- إدارة المناوبات (تعيين/إزالة)
- إدارة البصمات
- إدارة رصيد الإجازات
- تغيير الحالة
- عمليات مجمعة (نقل قسم، تصدير لأجهزة)

---

## 📦 المرحلة 4: نظام الحضور (Attendance)

**التبعية:** Users, Shifts

### 4.1 Attendance Module
```
Modules/Attendance/
├── Controllers/ (7)
│   ├── AttendanceSessionController.php
│   ├── DailyAttendanceSummaryController.php
│   ├── RawAttendanceLogController.php
│   ├── AttendanceReportController.php
│   ├── MonthlyReportController.php
│   ├── YearlyReportController.php
│   └── LiveAttendanceController.php
├── Models/ (3)
│   ├── AttendanceSession.php
│   ├── DailyAttendanceSummary.php
│   └── RawAttendanceLog.php
├── Services/ (11)
│   ├── AttendanceSessionService.php
│   ├── DailyAttendanceSummaryService.php
│   ├── DailyAttendanceAutoCalculationService.php
│   ├── RawAttendanceLogService.php
│   ├── AttendanceReportService.php
│   ├── MonthlyReportService.php
│   ├── YearlyReportService.php
│   ├── AttendanceCacheService.php
│   ├── AttendanceMonitoringService.php
│   ├── AttendanceNotificationService.php
│   └── AttendanceSessionTypeService.php
├── Repositories/
│   └── RawAttendanceLogRepository.php
├── Events/ (3)
│   ├── AttendanceSessionCreated.php
│   ├── AttendanceSessionUpdated.php
│   └── AttendanceSessionDeleted.php
├── Jobs/ (3)
│   ├── ProcessRawAttendanceLogsChunk.php
│   ├── RecalculateDailySummariesChunk.php
│   └── RecalculateDateRangeChunk.php
├── Listeners/
│   └── AttendanceSessionEventListener.php
├── Notifications/ (3)
│   ├── AttendanceIssueNotification.php
│   ├── MonthlyAttendanceReportNotification.php
│   └── WeeklyAttendanceSummaryNotification.php
├── Exceptions/
│   ├── AttendanceException.php
│   └── AttendanceCalculationException.php
├── Support/
│   └── TimeFormatter.php
├── database/migrations/
├── routes/web.php
└── tests/

# صفحات Vue:
resources/js/Pages/Attendance/
├── Sessions/
│   ├── Index.vue
│   └── Show.vue
├── DailySummaries/
│   ├── Index.vue
│   └── Show.vue
├── RawLogs/
│   ├── Index.vue
│   ├── Create.vue
│   └── Show.vue
├── Reports/
│   ├── Index.vue
│   ├── Comprehensive.vue
│   ├── Monthly.vue
│   └── Yearly.vue
└── Live.vue
```

### Attendance Data Flow
```
ZKTeco Device
    ↓ (HTTP Push / SDK Pull)
RawAttendanceLog (خام)
    ↓ (Process Job)
AttendanceSession (جلسات)
    ↓ (Daily Calculation)
DailyAttendanceSummary (ملخص يومي)
    ↓
Reports (Monthly, Yearly, Custom)
```

### Key Business Rules
- **Check-in**: أول بصمة في اليوم = check_in
- **Check-out**: آخر بصمة في اليوم = check_out
- **Overtime**: الوقت بعد نهاية المناوبة
- **Absent**: لا توجد بصمة في اليوم
- **Late**: أول بصمة بعد بداية المناوبة
- **Early Leave**: آخر بصمة قبل نهاية المناوبة
- **Holiday/Vacation**: أيام العطل/الإجازات

---

## 📦 المرحلة 5: أجهزة البصمة (FingerprintDevices)

**التبعية:** Companies, Branches

### 5.1 FingerprintDevices Module
```
Modules/FingerprintDevices/
├── Controllers/ (7)
│   ├── FingerprintDeviceController.php
│   ├── FingerprintDeviceTypeController.php
│   ├── DashboardController.php
│   ├── DeviceMonitoringController.php
│   ├── DevicePushController.php
│   ├── AdmsPushController.php
│   └── Controller.php (base)
├── Middleware/ (3)
│   ├── DeviceAccessMiddleware.php
│   ├── DeviceConnectionMiddleware.php
│   └── DeviceTypeAccessMiddleware.php
├── Models/ (3)
│   ├── FingerprintDevice.php
│   ├── FingerprintDeviceType.php
│   └── UserFingerprint.php
├── Services/
│   ├── FingerprintDeviceService.php
│   ├── FingerprintDeviceTypeService.php
│   ├── MasterFingerprintService.php
│   └── ZKTecoAdapter.php
├── Services/Contracts/
│   └── DeviceSdkInterface.php
├── Repositories/
│   ├── FingerprintDeviceRepository.php
│   └── FingerprintDeviceTypeRepository.php
├── Jobs/
│   ├── ExportFingerprintTemplatesJob.php
│   ├── ImportAllFingerprintTemplatesJob.php
│   └── ProcessUserImportChunk.php
├── database/migrations/
├── routes/web.php
└── tests/

# صفحات Vue:
resources/js/Pages/FingerprintDevices/
├── Index.vue
├── Create.vue
├── Edit.vue
├── Show.vue
└── Dashboard.vue
```

### ZKTeco Integration Architecture
```
PHP (Laravel)
    ↕ HTTP REST
Python Microservice (port 5000)
    ↕ TCP/UDP (port 4370)
ZKTeco Device
```

### Push Endpoints (Device → Server)
```
GET|POST /iclock/connect.aspx         → Handshake
GET|POST /iclock/pushattlog.aspx      → Receive attendance logs
GET|POST /iclock/getrequest.aspx      → Device requests
POST /api/fingerprint-devices/realtime-push → Real-time push
```

---

### Python Microservice Code (zkteco-service/)

#### هيكل الخدمة
```
zkteco-service/
├── app.py                 ← Flask HTTP API (pyzk library, port 5000)
├── adms_server.py         ← TCP Server (ADMS push protocol, port 8081)
├── requirements.txt       ← Python dependencies
├── start.bat              ← تشغيل الخدمة على Windows
├── start.sh               ← تشغيل الخدمة على Linux
├── install-deps.bat       ← تثبيت المتطلبات
├── README.md              ← توثيق الخدمة
└── venv/                  ← Python virtual environment
```

#### app.py - Flask Microservice (1092 lines)
```
ZKTecoService class (Python):
├── __init__(ip, port, password, timeout, force_udp, ommit_ping)
├── connect()              ← الاتصال بالجهاز (TCP/UDP مع fallback)
├── disconnect()           ← قطع الاتصال
├── get_users()            ← get_users() من pyzk
├── get_attendance()       ← get_attendance() من pyzk ← الأهم
├── get_fingerprint_templates(uid) ← get_templates() لـ uid معين
├── get_all_templates()    ← get_templates() للكل
├── add_user(uid, ...)     ← set_user() عبر pyzk
├── add_users_batch(data)  ← إضافة مجمعة مع UID management ذكي
├── delete_user(uid)       ← delete_user() عبر pyzk
├── export_template(...)   ← save_user_template() عبر pyzk
├── clear_attendance()     ← clear_attendance()
├── get_device_info()      ← firmware, serial, platform, counts
└── test_template_upload_support() ← اختبار دعم رفع البصمات

Flask Endpoints (REST API):
├── GET  /health                 ← فحص الخدمة
├── POST /device/test-connection ← اختبار اتصال الجهاز
├── POST /device/get-attendance  ← سحب سجلات الحضور
├── POST /device/get-users       ← سحب المستخدمين
├── POST /device/get-templates   ← سحب قوالب البصمات
├── POST /device/add-user        ← إضافة مستخدم
├── POST /device/add-users-batch ← إضافة مستخدمين (batch)
├── POST /device/delete-user     ← حذف مستخدم
├── POST /device/export-template ← رفع قالب بصمة
├── POST /device/export-templates-batch ← رفع بصمات (batch)
├── POST /device/clear-attendance ← مسح سجلات الحضور
├── POST /device/info            ← معلومات الجهاز
└── POST /device/adms-config     ← إعدادات ADMS push
```

#### adms_server.py - TCP ADMS Server (451 lines)
```
ADMServer class:
├── start()                ← استماع TCP على port 8081
├── stop()                 ← إيقاف الخادم
└── get_server_ip()        ← كشف IP السيرفر المحلي

ZKTecoADMSHandler class:
├── handle()               ← معالجة اتصال جهاز واحد
├── parse_adms_data()      ← تحليل البيانات (نص/ثنائي)
├── parse_text_format()    ← تنسيق نصي (tab-separated)
├── parse_binary_format()  ← تنسيق ثنائي (binary protocol)
└── parse_datetime()       ← تحليل التاريخ بتنسيقات متعددة

Functions:
├── save_attendance_log()  ← حفظ سجل عبر php artisan tinker
├── find_device_id_by_ip() ← إيجاد device_id من IP
└── process_attendance()   ← تشغيل المعالجة التلقائية
```

#### آلية الاتصال المزدوجة

```
┌─────────────────────────────────────────────────┐
│                   Laravel (PHP)                   │
│  ZKTecoPythonBridgeService.php                    │
│  ┌──────────────────────────────────────────┐    │
│  │ getAttendance(ip, port, password)        │    │
│  │   ↓ HTTP POST /device/get-attendance     │    │
│  └──────────────────────────────────────────┘    │
│  ┌──────────────────────────────────────────┐    │
│  │ getTemplates(ip, uid)                    │    │
│  │   ↓ HTTP POST /device/get-templates      │    │
│  └──────────────────────────────────────────┘    │
│  ┌──────────────────────────────────────────┐    │
│  │ exportTemplate(ip, uid, fingerId, data)  │    │
│  │   ↓ HTTP POST /device/export-template    │    │
│  └──────────────────────────────────────────┘    │
│  ┌──────────────────────────────────────────┐    │
│  │ testConnection(ip, port, password)       │    │
│  │   ↓ HTTP POST /device/test-connection    │    │
│  └──────────────────────────────────────────┘    │
└──────────────────────┬──────────────────────────┘
                       │ HTTP (REST)
┌──────────────────────▼──────────────────────────┐
│           Python Microservice (Flask)             │
│              app.py (port 5000)                    │
│                                                    │
│  ZKTecoService.connect()                           │
│    ↓ pyzk library (TCP/UDP)                        │
│  ZKTecoService.get_attendance()                    │
│    ↓ pyzk library                                  │
│  Returns JSON → Laravel                            │
└──────────────────────┬──────────────────────────┘
                       │ TCP/UDP (port 4370)
┌──────────────────────▼──────────────────────────┐
│              ZKTeco Device (iFace, etc.)          │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│                ADMS Push (TCP)                    │
│                                                    │
│  ZKTeco Device (master)                            │
│    ↓ TCP connect (ADMS mode)                       │
│  adms_server.py (port 8081)                        │
│    ↓ parse attendance data                          │
│    ↓ php artisan tinker → DB insert                │
│  Laravel RawAttendanceLog                          │
└─────────────────────────────────────────────────┘
```

#### متطلبات التشغيل
```bash
# 1. إنشاء البيئة الافتراضية
cd zkteco-service
python -m venv venv
source venv/bin/activate  # Linux/Mac
# أو
.\venv\Scripts\activate   # Windows

# 2. تثبيت المتطلبات
pip install -r requirements.txt
# محتوى requirements.txt:
# flask==3.0.0
# flask-cors==4.0.0
# pyzk==0.9
# python-dotenv==1.0.0
# requests==2.31.0

# 3. تشغيل الخدمة
python app.py --port 5000 &

# 4. تشغيل ADMS server (اختياري - للـ push المباشر)
python adms_server.py --port 8081 &
```

#### ZKTecoPythonBridgeService.php (Bridge Layer in Laravel)
```
Namespace: App\Services
الملف: app/Services/ZKTecoPythonBridgeService.php (525 lines)

الميثودز:
├── __construct()                    ← تجهيز URL الخدمة، تشغيل تلقائي
├── ensureServiceRunning()           ← تشغيل Python تلقائياً إذا كان متوقفاً
├── isAvailable()                    ← GET /health
├── testConnection(ip, port, pass)   ← POST /device/test-connection
├── getAttendance(ip, port, pass, timeout, forceUdp, ommitPing)
│                                    ← POST /device/get-attendance
├── getUsers(ip, port, pass)         ← POST /device/get-users
├── getTemplates(ip, port, pass, uid) ← POST /device/get-templates
├── getDeviceInfo(ip, port, pass)    ← POST /device/info
├── addUser(ip, port, pass, uid, userId, name, ...)
│                                    ← POST /device/add-user
├── addUsersBatch(ip, port, pass, usersData)
│                                    ← POST /device/add-users-batch
├── deleteUser(ip, port, pass, uid)  ← POST /device/delete-user
├── exportTemplate(ip, port, pass, uid, fingerId, templateData)
│                                    ← POST /device/export-template
├── exportTemplatesBatch(ip, port, pass, templates)
│                                    ← POST /device/export-templates-batch
└── clearAttendance(ip, port, pass)  ← POST /device/clear-attendance
```

#### ZKTeco Artisan Command
```bash
php artisan zkteco:service start    ← تشغيل خدمة Python
php artisan zkteco:service stop     ← إيقاف الخدمة
php artisan zkteco:service status   ← حالة الخدمة
```

**الكود في:** `app/Console/Commands/ZKTecoServiceCommand.php`

---

## 📦 المرحلة 6: الإجازات والعطل (Time Off)

### 6.1 Vacations Module
**التبعية:** Users

```
Modules/Vacations/
├── Controllers/
│   └── VacationRequestController.php
├── Models/
│   ├── Vacation.php
│   ├── UserVacationRequest.php
│   ├── UserVacationBalance.php
│   └── UserVacationBalanceTransaction.php
├── Services/
├── Repositories/
├── Events/
├── Notifications/
├── database/migrations/
├── routes/web.php
└── tests/

# صفحات Vue:
resources/js/Pages/Vacations/
├── Index.vue
├── Create.vue
├── Show.vue
└── Requests.vue
```

### Vacation Types
- سنوية (Annual)
- مرضية (Sick)
- طارئة (Emergency)
- غير مدفوعة (Unpaid)

### Business Rules
- كل موظف له رصيد إجازات (UserVacationBalance)
- طلب الإجازة يمر بمراحل: pending → approved/rejected
- الموافقة تحتاج صلاحية approve-vacation-requests
- الخصم من الرصيد عند الموافقة
- ترحيل الرصيد السنوي في بداية كل عام

### 6.2 Holidays Module
```
Modules/Holidays/
├── Controllers/HolidaysController.php
├── Models/Holiday.php
├── Services/HolidayService.php
├── Repositories/HolidayRepository.php
├── database/migrations/
├── routes/web.php
└── tests/

# صفحات Vue:
resources/js/Pages/Holidays/
├── Index.vue
├── Create.vue
└── Edit.vue
```

### Holiday Features
- عطل لمرة واحدة (one-time)
- عطل متكررة سنوياً (yearly recurrence)
- مجموعات عطل (Holiday Groups)
- تأثير العطل على حساب الحضور

---

## 📦 المرحلة 7: المناطق والمناطق الزمنية (Zones)

### 7.1 Zones Module
**التبعية:** Branches
**ملاحظة:** يحتاج إصلاح (Entities/ → Models/)

```
Modules/Zones/
├── Controllers/ZonesController.php
├── Models/Zone.php                    ← بدلاً من Entities/Zone.php
├── Services/ZoneService.php           ← NEW
├── Repositories/ZoneRepository.php    ← NEW
├── database/migrations/
├── routes/web.php
└── tests/

# صفحات Vue:
resources/js/Pages/Zones/
├── Index.vue
├── Create.vue
└── Edit.vue
```

### Zone Features
- إدارة المناطق (CRUD)
- ربط الفروع بالمناطق (zone_branches pivot)
- تقارير حسب المنطقة

---

## 📦 المرحلة 8: الإعدادات والأدوار (Settings & Auth)

### 8.1 Settings Module
```
Modules/Settings/
├── Controllers/SettingsController.php
├── Models/Setting.php
├── Services/SettingService.php
├── Repositories/SettingRepository.php
├── database/migrations/
├── routes/web.php
└── tests/

# صفحة Vue:
resources/js/Pages/Settings/
└── Index.vue
```

### Settings Features
- Key-value store مع group و type
- تخزين مؤقت لمدة ساعة
- إعادة تعيين للإعدادات الافتراضية

### 8.2 Roles & Permissions (Auth)
```
app/Http/Controllers/
├── RoleController.php
├── PermissionController.php
├── AuthController.php

config/permissions.php                  ← 311 lines
config/permission.php                   ← Spatie config
```

### Permission Format
```php
// config/permissions.php
return [
    'companies' => ['view', 'create', 'edit', 'delete'],
    'branches' => ['view', 'create', 'edit', 'delete'],
    // ...
    'special' => ['process-attendance', 'approve-vacation-requests'],
];
```

### Seeders
```bash
php artisan db:seed --class=RoleAndPermissionSeeder
```

---

## 📦 المرحلة 9: الوحدات الإضافية (Additional Modules)

### 9.1 Payroll Module
```
Modules/Payroll/
├── Controllers/
├── Models/
├── Services/
├── Repositories/
├── database/migrations/
├── routes/web.php
└── tests/
```

### 9.2 ShiftRotation Module
```
Modules/ShiftRotation/
├── Controllers/
├── Models/
├── Services/
├── Repositories/
├── database/migrations/
├── routes/web.php
└── tests/

# صفحات Vue:
resources/js/Pages/ShiftRotation/
├── Index.vue
└── Schedule.vue
```

### 9.3 Visitor Module
```
Modules/Visitor/
├── Controllers/
├── Models/
├── Services/
├── Repositories/
├── database/migrations/
├── routes/web.php
└── tests/

# صفحات Vue:
resources/js/Pages/Visitor/
├── Index.vue
├── Create.vue
├── Edit.vue
└── Show.vue
```

### 9.4 Meeting Module
```
Modules/Meeting/
├── Controllers/
├── Models/
├── Services/
├── Repositories/
├── database/migrations/
├── routes/web.php
└── tests/

# صفحات Vue:
resources/js/Pages/Meeting/
├── Index.vue
├── Create.vue
└── Edit.vue
```

### 9.5 AccessControl Module
### 9.6 Workflow Module
### 9.7 Mobile Module
### 9.8 Sync Module

---

## 📦 المرحلة 10: التكامل والاختبارات (Integration & Testing)

### 10.1 التكاملات
```bash
# ZKTeco Python Service
cd zkteco-service
python -m venv venv
pip install -r requirements.txt
python main.py &

# Queue
php artisan queue:listen &

# Vite
npm run dev &
```

### 10.2 الاختبارات
```bash
# إعداد البيئة
cp .env.example .env.testing
# DB_CONNECTION=sqlite
# DB_DATABASE=:memory:

# تشغيل الاختبارات
php artisan config:clear
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# تنسيق الكود
php artisan pint
```

### 10.3 Seeders (10)
```bash
php artisan db:seed
# DatabaseSeeder.php يستدعي:
# - RoleAndPermissionSeeder.php
# - UserSeeder.php
# - ZoneSeeder.php
# - FingerprintDeviceTypeSeeder.php
# - FingerprintDeviceSeeder.php
# - RamadanDatesSeeder.php
# - EnableWorkOnQueueSeeder.php
# - ImportBioTimeDataSeeder.php (اختياري)
```

### 10.4 قائمة تحقق الإطلاق
- [ ] جميع الترحيلات تعمل: `php artisan migrate:fresh --seed`
- [ ] جميع الاختبارات تمر: `php artisan test`
- [ ] التنسيق سليم: `php artisan pint`
- [ ] مسارات API تعمل: `php artisan route:list`
- [ ] ZKTeco متصل: `php artisan zkteco:service status`
- [ ] Queue يعمل: `php artisan queue:listen --once`
- [ ] Vite يبني: `npm run build`

---

## 📊 جدول زمني تقديري

| المرحلة | الوحدات | الوقت المقدر |
|---------|---------|-------------|
| 0: Foundation | - | 1 يوم |
| 1: الهيكل التنظيمي | Companies, Branches, Departments | 2-3 أيام |
| 2: الكيانات المستقلة | Positions, Grades, Shifts | 1-2 أيام |
| 3: المستخدمين | Users | 2-3 أيام |
| 4: الحضور | Attendance | 4-5 أيام |
| 5: أجهزة البصمة | FingerprintDevices | 2-3 أيام |
| 6: الإجازات والعطل | Vacations, Holidays | 2 يوم |
| 7: المناطق | Zones | 1 يوم |
| 8: الإعدادات والأدوار | Settings, Auth | 1 يوم |
| 9: الوحدات الإضافية | 8 وحدات | 4-5 أيام |
| 10: التكامل والاختبارات | - | 2-3 أيام |

**المجموع التقديري:** 22-30 يوماً

---

## 🛡️ الاعتبارات الأمنية

1. **كل مسار** يحتاج auth middleware
2. **كل عملية** محمية بـ Spatie Permission
3. **CSRF** على كل النماذج
4. **Validation** في Service layer
5. **XSS** protection من Blade
6. **SQL Injection** محمي بـ Eloquent
7. **Rate Limiting** على API endpoints

---

## 📐 معايير الجودة

### Code Style
```bash
# Laravel Pint (PSR-12)
php artisan pint

# الاختبارات
php artisan test --coverage  # عندما يتوفر XDebug
```

### Architecture Rules
```php
// ✅ Controller يتصل بـ Service
$this->companyService->createCompany($data);

// ❌ Controller لا يتصل مباشرة بـ Model
Company::create($data);  // ممنوع
```

### Performance Rules
```php
// ✅ Eager loading
User::with('department.branch.company')->get();

// ✅ Pagination
User::paginate(25);

// ✅ Cache للقراءات المتكررة
Cache::remember('settings', 3600, fn() => Setting::all());
```

---

*نهاية خطة التنفيذ الكاملة*
