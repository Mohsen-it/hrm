# تحسين أداء قاعدة البيانات عبر الفهرسة (Database Indexing & Query Performance) - المواصفات

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-21
**الحالة:** مسودة
**النوع:** تحسين أداء (Performance Optimization) - غير مُخرّب للبيانات
**الوحدات المشمولة:** `Users`, `Attendance`, `Shifts`, `Vacations`, `FingerprintDevices`, `Companies`, `Branches`, `Departments`, `Positions`, `Grades`, `Holidays`, `Subordinations`, `Settings`, `Zones`, `AttendanceIntegration`

---

## 1. نظرة عامة (Overview)

تقوم هذه الميزة بتحسين أداء النظام عبر:

1. **إضافة فهارس قاعدة بيانات (Database Indexes)** على الأعمدة التي تُستخدم بكثرة في `WHERE` و `ORDER BY` و `JOIN` ولم تُفهرس بعد.
2. **مراجعة الاستعلامات الحرجة (Critical Queries)** للتأكد من أنها تستفيد من الفهارس الموجودة، وإصلاح أي استعلام يعمل table-scan بدلاً من index-scan.
3. **الحفاظ الكامل على البيانات الموجودة** — كل التعديلات **إضافية فقط (additive)**: لا `DROP COLUMN`، لا `TRUNCATE`، لا `migrate:fresh`، لا تغيير في بنية الجداول.

> **⚠️ ضمانات حرجة (Critical Guarantees):**
> - ✅ **لا حذف للبيانات** — جميع الـ migrations المُضافة هي `Schema::table()` لفهارس فقط، ولا تحذف أعمدة أو جداول أو سجلات.
> - ✅ **لا كسر للوظائف** — كل فهرس يُضاف مُحسِّن لاستعلام قائم، وكل استعلام مُعدَّل يحتفظ بنفس الـ output.
> - ✅ **قابلة للعكس (Reversible)** — كل migration يحوي `down()` يحذف نفس الفهارس بالضبط.
> - ✅ **آمنة لكل من MySQL و SQLite و PostgreSQL** — الكشف عن نوع الـ driver قبل إضافة الفهارس الخاصة بكل محرك.

---

## 2. قصص المستخدمين (User Stories)

- [ ] كـ **مدير النظام**، ألاحظ أن قوائم الموظفين والحضور أصبحت تُحمَّل بسرعة أكبر بعد تطبيق الفهارس الجديدة.
- [ ] كـ **مدير موارد بشرية**، أرى أن التقارير الشهرية للحضور ترجع في وقت أقل من 100ms بدلاً من ثوانٍ.
- [ ] كـ **مطوّر**، أتمكن من تشغيل `EXPLAIN` على استعلام معين ولاحظ أن `type=ref` أو `type=range` بدلاً من `type=ALL`.
- [ ] كـ **مطوّر**، أتمكن من تشغيل `php artisan migrate` بشكل نظيف، ولا يحدث أي خطأ بسبب تعارض أسماء فهارس.
- [ ] كـ **مطوّر**، أتمكن من تشغيل `php artisan migrate:rollback` على migration الفهارس فقط بدون التأثير على بيانات حقيقية.
- [ ] كـ **مدير النظام**، أتأكد أن **جميع السجلات الموجودة (real data) في قاعدة البيانات سليمة ولم تُمس** بعد تطبيق الـ migration.
- [ ] كـ **مستخدم**، لا ألاحظ أي تغيير في سلوك التطبيق أو واجهة المستخدم — كل شيء يبقى يعمل كما كان، فقط أسرع.
- [ ] كـ **مهندس DevOps**، أرى أن استخدام الـ CPU على خادم قاعدة البيانات انخفض بعد تطبيق الفهارس.

---

## 3. سيناريوهات الاستخدام (User Scenarios & Testing)

### السيناريو 1 — تطبيق الـ Migrations على بيئة تطوير فيها بيانات حقيقية
**الفاعل:** مطوّر (Developer).
**التمهيد:** قاعدة بيانات SQLite فيها بيانات حقيقية لموظفين، فروع، شركات، إلخ (مثل بيئة الإنتاج المصغّرة).
**الخطوات:**
1. تشغيل `php artisan migrate` لتطبيق جميع migrations الفهرسة الجديدة.
2. التحقق من عدم وجود خطأ.
3. تشغيل `SELECT COUNT(*) FROM users;` ومقارنتها بنفس العدد قبل الـ migration.
4. تشغيل `SELECT * FROM users LIMIT 5;` للتأكد أن البيانات سليمة.

**معايير القبول:**
- ✅ جميع migrations الفهرسة تعمل بنجاح على SQLite (بيئة التطوير).
- ✅ عدد السجلات في `users` و `companies` و `branches` و `departments` و `attendance_sessions` و `raw_attendance_logs` و `schedule_entries` لم يتغيّر (لا زيادة ولا نقصان).
- ✅ تطبيق `php artisan migrate:rollback --step=N` لـ migrations الفهرسة فقط يعيد الحالة بدون فقدان بيانات.

### السيناريو 2 — تطبيق الـ Migrations على بيئة MySQL/PostgreSQL
**الفاعل:** مهندس DevOps.
**التمهيد:** قاعدة بيانات MySQL 8 في staging.
**الخطوات:**
1. تشغيل `php artisan migrate` على staging.
2. تشغيل `EXPLAIN SELECT * FROM users WHERE company_id = 1 AND status = 1 ORDER BY id LIMIT 20;`.
3. مراقبة أداء الجداول عبر `SHOW INDEX FROM users;`.

**معايير القبول:**
- ✅ الـ migration تعمل على MySQL بنفس النتيجة.
- ✅ الـ EXPLAIN يظهر أن الاستعلام يستخدم `ref` index بدلاً من `ALL` (table scan).
- ✅ لا يظهر `Duplicate key name` error.

### السيناريو 3 — التأكد من أن الـ down() Migration لا يحذف بيانات
**الخطوات:**
1. تشغيل `php artisan migrate` لتطبيق فهارس.
2. تشغيل `php artisan migrate:rollback --step=1` للتراجع عن آخر migration.
3. تشغيل `SELECT COUNT(*) FROM users;` ومقارنتها بالعدد الأصلي.

**معايير القبول:**
- ✅ `down()` يحذف الـ indexes فقط (عبر `dropIndex`)، ولا يحذف أي أعمدة أو سجلات.
- ✅ عدد السجلات لم يتغيّر.

### السيناريو 4 — استعلام مفلتر معقد (اختبار الفهرسة المركّبة)
**الخطوات:**
1. تشغيل استعلام Eloquent:
   ```php
   User::where('company_id', 1)
       ->where('branch_id', 2)
       ->where('status', 1)
       ->where('is_active_employee', true)
       ->orderBy('id')
       ->paginate(20);
   ```
2. تشغيل `EXPLAIN` على SQL الناتج.

**معايير القبول:**
- ✅ الـ EXPLAIN يستخدم composite index `(company_id, branch_id, status, is_active_employee)` أو على الأقل `(company_id, branch_id)` (index prefix).
- ✅ زمن التنفيذ < 50ms على 10,000+ سجل.

### السيناريو 5 — استعلام الإجازات حسب التاريخ
**الخطوات:**
1. تشغيل: `UserVacationRequest::where('status', 'pending')->whereBetween('start_date', [$from, $to])->get();`
2. تشغيل `EXPLAIN`.

**معايير القبول:**
- ✅ يوجد فهرس على `(status, start_date, end_date)` أو على الأقل `(start_date, end_date)`.
- ✅ `type = range` في EXPLAIN.

### السيناريو 6 — استعلام سجلات البصمة الخام
**الخطوات:**
1. تشغيل: `RawAttendanceLog::where('device_id', 5)->where('punch_time', '>=', $from)->where('processed', false)->get();`

**معايير القبول:**
- ✅ يوجد فهرس مركّب `(device_id, processed, punch_time)`.
- ✅ زمن التنفيذ < 100ms على 100,000+ سجل.

### السيناريو 7 — لا تغيير في واجهة المستخدم
**الفاعل:** مستخدم نهائي.
**الخطوات:**
1. فتح `/users`، `/attendance/sessions`، `/vacations/requests`، `/shifts/calendar`، إلخ.

**معايير القبول:**
- ✅ كل الصفحات تعمل بنفس السلوك تماماً (نفس الأعمدة، نفس الفلاتر، نفس الـ pagination).
- ✅ لا توجد console errors أو Vue warnings جديدة.

### السيناريو 8 — الحفاظ على البيانات الموجودة
**الخطوات:**
1. مقارنة `SELECT COUNT(*)` من كل جدول قبل وبعد الـ migration.

**معايير القبول:**
- ✅ عدد السجلات متطابق 100% في كل الجداول.
- ✅ لا توجد سجلات بأعمدة `null` لم تكن `null` من قبل.
- ✅ لا Foreign Key violations بعد الـ migration.

---

## 4. المتطلبات الوظيفية (Functional Requirements)

### 4.1 قواعد الفهرسة (Indexing Business Rules)

1. **BR-1** كل `foreign_id` (Foreign Key) في الجداول الرئيسية يجب أن يكون indexed. هذا ينطبق على الجداول التي أُنشئت بدون `constrained()->index()` ضمنياً.
2. **BR-2** كل عمود يظهر في `WHERE` أو `JOIN` أو `ORDER BY` في استعلامات Repository/Service حرجة (المُستدعاة في كل صفحة) يجب أن يكون indexed.
3. **BR-3** Composite indexes (الفهارس المركّبة) تُستخدم عند وجود عمودين أو أكثر يتم البحث بهما معاً دائماً، وترتيبها يتبع ترتيب الاستخدام في الاستعلام (مساواة أولاً، ثم نطاق).
4. **BR-4** لا يُضاف فهرس جديد على عمود إذا كان:
   - جدول يحتوي على < 1000 سجل متوقع (overhead أكبر من الفائدة).
   - العمود لا يُستخدم إلا نادراً في الاستعلامات.
5. **BR-5** لا يُضاف FULLTEXT index إلا على MySQL (لأن SQLite/PostgreSQL لهما آليات مختلفة).
6. **BR-6** كل migration يحوي `up()` و `down()` متطابقين عكسياً — `down()` يحذف فقط ما `up()` أضافه.
7. **BR-7** كل migration يجب أن يكون `idempotent` عبر try/catch لـ `Duplicate key name` (السلامة في التشغيل المتعدد).

### 4.2 قواعد الاستعلام (Query Business Rules)

8. **BR-8** كل استعلام في Repository يستخدم `select()` صريح للأعمدة المطلوبة فقط (لا `SELECT *` ضمنياً) — باستثناء حالات `count()` أو `exists()`.
9. **BR-9** كل استعلام يستخدم `with()` أو `load()` للعلاقات التي ستُعرض في الـ Resource (منع N+1).
10. **BR-10** كل استعلام يستخدم `when()` للفلاتر الشرطية بدلاً من `if` يدوي يكرر الـ query.
11. **BR-11** في استعلامات الإحصائيات والتقارير، يُستخدم `selectRaw()` مع `COUNT/SUM/AVG` بدلاً من `get()` ثم حساب في PHP.
12. **BR-12** لا `DB::raw()` بدون prepared statements. أي قيمة مدخلة من المستخدم تمر عبر `?` placeholders.

### 4.3 قواعد الحفاظ على البيانات (Data Preservation Rules)

13. **BR-13** ❌ **ممنوع منعاً باتاً** استخدام أي من الأوامر التالية في هذا الـ feature:
    - `migrate:fresh` ❌
    - `migrate:refresh` ❌
    - `DB::table()->truncate()` ❌
    - `Schema::dropIfExists()` على جداول موجودة فعلياً ❌
    - `$table->dropColumn()` على أعمدة تحتوي على بيانات ❌
    - `Model::truncate()` ❌
    - `User::query()->delete()` (بدون where) ❌
14. **BR-14** كل migration يجب أن يعمل فقط عبر `Schema::table()->index()` أو `$table->unique()` أو `$table->fulltext()` (إضافة فهارس فقط).
15. **BR-15** قبل وبعد كل migration، يجب التحقق من `COUNT(*)` للجداول المتأثرة للتأكد من عدم فقدان بيانات.

### 4.4 قواعد الأداء (Performance Targets)

16. **BR-16** كل استعلام Index-Scan يجب أن يكون < 100ms على 10,000 سجل (متوسط). للتقارير المعقدة < 500ms.
17. **BR-17** لا استعلام يستخدم `LIKE '%foo%'` (wildcard في البداية) — يُستبدل بـ FULLTEXT أو بحث ثنائي المرحلة.

---

## 5. بنية البيانات (Data Model)

> **ملاحظة:** لا تغيير هيكلي على الجداول. كل التعديلات هي **إضافة indexes** فقط.

### 5.1 فهارس جدول `users` (الحرج — يُستخدم في كل صفحة)

| الفهرس | الأعمدة | النوع | السبب (الاستعلام) |
|--------|---------|-------|-----------------|
| `idx_users_company_status_active` | `(company_id, status, is_active_employee)` | composite | `UsersController::index` و scope `active()` |
| `idx_users_branch_status` | `(branch_id, status)` | composite | تصفية حسب الفرع |
| `idx_users_department_status` | `(department_id, status)` | composite | تصفية حسب القسم |
| `idx_users_position_status` | `(position_id, status)` | composite | تصفية حسب الموقع الوظيفي |
| `idx_users_grade_status` | `(grade_id, status)` | composite | تصفية حسب الدرجة |
| `idx_users_employment_type` | `employment_type` | simple | تقارير نوع التوظيف |
| `idx_users_employee_code` | `employee_code` | unique (إن لم يكن) | بحث بكود الموظف |
| `idx_users_hire_date` | `hire_date` | simple | تقارير تاريخ التعيين |

> ملاحظة: بعض هذه الأعمدة (company_id, branch_id, إلخ) مفهرسة تلقائياً عبر `foreignId()->constrained()` (الـ FK ينشئ index). الفهارس الجديدة للمركّبات فقط.

### 5.2 فهارس جدول `attendance_sessions` (الأكبر حجماً)

| الفهرس | الأعمدة | السبب |
|--------|---------|-------|
| `idx_att_sessions_user_date_status` | `(user_id, attendance_date, status)` | تقارير حضور موظف بمدى زمني |
| `idx_att_sessions_date_status_type` | `(attendance_date, status, session_type)` | إحصائيات يومية |
| `idx_att_sessions_created_by` | `(created_by, attendance_date)` | تتبع من أنشأ الجلسة |
| `idx_att_sessions_checkout` | `(check_out_at, status)` | البحث عن جلسات بدون check-out |

### 5.3 فهارس جدول `daily_attendance_summaries`

| الفهرس | الأعمدة | السبب |
|--------|---------|-------|
| `idx_daily_summaries_date_calculated` | `(summary_date, calculated_at)` | التقارير اليومية + التحديث |
| `idx_daily_summaries_status_date` | `(status, summary_date)` | تصفية حسب الحالة (غائب/حاضر) |

### 5.4 فهارس جدول `raw_attendance_logs`

| الفهرس | الأعمدة | السبب |
|--------|---------|-------|
| `idx_raw_logs_dedup` | `(device_id, device_user_id, punch_time)` | منع التكرار في الاستيراد |
| `idx_raw_logs_user_time` | `(device_user_id, punch_time)` | البحث بالـ device-side id |
| `idx_raw_logs_processed_punch` | `(processed, punch_time)` | معالجة السجلات غير المعالجة |

### 5.5 فهارس جدول `iclock_transaction` (جدول أجهزة البصمة الخارجي)

| الفهرس | الأعمدة | السبب |
|--------|---------|-------|
| `idx_iclock_emp_punch` | `(emp_id, punch_time)` | التزامن (sync) مع الأجهزة |
| `idx_iclock_punch_time` | `(punch_time)` | تقارير زمنية |

### 5.6 فهارس جدول `schedule_entries` (الجداول الشهرية)

| الفهرس | الأعمدة | السبب |
|--------|---------|-------|
| `idx_schedule_entries_date_emp` | `(date, employee_id, day_status)` | استعلام التقويم |
| `idx_schedule_entries_period_status` | `(schedule_period_id, day_status)` | إحصائيات فترة |

### 5.7 فهارس جدول `user_vacation_requests`

| الفهرس | الأعمدة | السبب |
|--------|---------|-------|
| `idx_vacation_req_status_start` | `(status, start_date, end_date)` | استعلام "الطلبات المعلقة الآن" |
| `idx_vacation_req_user_dates` | `(user_id, start_date)` | تاريخ إجازات موظف |
| `idx_vacation_req_decided` | `(decided_at, status)` | تتبع من اتخذ القرار |

### 5.8 فهارس جدول `user_vacation_balance_transactions`

| الفهرس | الأعمدة | السبب |
|--------|---------|-------|
| `idx_vacation_bal_tx_date` | `(user_id, vacation_type_id, created_at)` | سجل رصيد الإجازات |

### 5.9 فهارس جدول `fingerprint_devices`

| الفهرس | الأعمدة | السبب |
|--------|---------|-------|
| `idx_devices_company_branch` | `(company_id, branch_id, status)` | قائمة الأجهزة حسب الفرع |
| `idx_devices_last_pushed` | `(last_pushed_at, status)` | الأجهزة التي لم تتم مزامنتها |

### 5.10 فهارس جدول `device_sync_logs`

| الفهرس | الأعمدة | السبب |
|--------|---------|-------|
| `idx_sync_logs_device_date` | `(device_id, started_at)` | تاريخ المزامنة |
| `idx_sync_logs_status_date` | `(status, started_at)` | إحصائيات نجاح/فشل |

### 5.11 فهارس جدول `attendance_integration_audit_logs`

| الفهرس | الأعمدة | السبب |
|--------|---------|-------|
| `idx_audit_correlation` | `(correlation_id, occurred_at)` | تتبع التدفق |
| `idx_audit_actor` | `(actor_id, occurred_at)` | من فعل ماذا متى |

### 5.12 فهارس جدول `audit_logs` (Logs module)

| الفهرس | الأعمدة | السبب |
|--------|---------|-------|
| `idx_audit_action_date` | `(action, created_at)` | البحث حسب نوع الإجراء |

### 5.13 فهارس جدول `holidays`

| الفهرس | الأعمدة | السبب |
|--------|---------|-------|
| `idx_holidays_date_active` | `(start_date, end_date, is_active)` | تقويم العطلات |

### 5.14 فهارس جدول `subordinations`

| الفهرس | الأعمدة | السبب |
|--------|---------|-------|
| `idx_subordinations_status_order` | `(status, sort_order)` | القائمة المنسدلة |

### 5.15 فهارس جدول `att_hours_tracking`

| الفهرس | الأعمدة | السبب |
|--------|---------|-------|
| `idx_hours_user_date` | `(user_id, date)` | حساب ساعات شهرية |
| `idx_hours_employee_category` | `(employee_id, shift_category_id, date)` | تفصيل مناوبة موظف |

### 5.16 فهارس جدول `att_rotation_assignments`

| الفهرس | الأعمدة | السبب |
|--------|---------|-------|
| `idx_rotation_assign_emp_dates` | `(employee_id, start_date, end_date)` | معرفة مجموعة موظف في تاريخ |

### 5.17 فهارس جدول `att_employee_shift_categories` (تأكيد)

| الفهرس | الأعمدة | السبب |
|--------|---------|-------|
| `idx_esc_active` | `(is_active, employee_id)` | الفلتر الافتراضي في الـ scope |

### 5.18 جداول لا تحتاج فهارس إضافية (موثقة)

| الجدول | السبب |
|--------|-------|
| `companies` | عدد السجلات صغير (< 100)، الـ PK يكفي |
| `branches` | عدد السجلات صغير (< 1000) |
| `departments` | عدد السجلات صغير |
| `positions`, `grades` | عدد السجلات صغير |
| `zones` | عدد السجلات صغير |
| `settings` | يُمستدعى عبر cache ولا query مباشر |

---

## 6. الاستعلامات الحرجة المُراجَعة (Critical Queries Audit)

### 6.1 `UserRepository::getAll()`

**الحالي:**
```php
return $this->query()
    ->select(['id', 'employee_code', ...])
    ->with([...])
    ->when($search, ...)
    ->when($company_id, fn($q) => $q->where('company_id', $companyId))
    ...
    ->latest()
    ->paginate($perPage);
```

**التحسينات:**
- ✅ `select()` صريح موجود (جيد).
- ✅ `with()` للعلاقات (جيد).
- ✅ `when()` للفلاتر (جيد).
- ⚠️ `latest()` يستخدم `created_at` — يجب التأكد من وجود index على `(status, created_at)` أو `created_at` فقط.

**الإجراء:** إضافة `idx_users_company_status_active` (مذكور في 5.1).

### 6.2 `AttendanceSessionRepository::getByDateRange()`

**الحالي:** استعلام نموذج: `WHERE user_id = ? AND attendance_date BETWEEN ? AND ?`

**الإجراء:** التحقق من أن `(user_id, attendance_date)` index يخدم هذا (موجود في `att_sessions_user_date_idx`). لا تغيير.

### 6.3 `RawAttendanceLogRepository::getUnprocessed()`

**الحالي:** `WHERE processed = 0 ORDER BY punch_time LIMIT 1000`

**الإجراء:** إضافة `idx_raw_logs_processed_punch` (مذكور في 5.4).

### 6.4 `ScheduleEntryRepository::getCalendar()`

**الحالي:** `WHERE schedule_period_id = ? AND date BETWEEN ? AND ?`

**الإجراء:** إضافة `idx_schedule_entries_date_emp` (مذكور في 5.6).

### 6.5 `UserVacationRequestRepository::getPending()`

**الحالي:** `WHERE status = 'pending' ORDER BY requested_at DESC`

**الإجراء:** إضافة `idx_vacation_req_status_start` (مذكور في 5.7).

### 6.6 `FingerprintDevice::getActive()`

**الحالي:** `WHERE status = 1 AND company_id = ?`

**الإجراء:** إضافة `idx_devices_company_branch` (مذكور في 5.9).

### 6.7 استعلام `withoutSuperAdmin()` Scope

**الحالي:** `WHERE id != 10000`

**ملاحظة:** هذا شرط بسيط جداً، والـ PK index يخدمه. لا حاجة لفهرس جديد.

### 6.8 استعلام `is_active_employee = true AND status = 1`

**الحالي:** مُستخدم في 23+ مكان (DevicePushService, AbsenceCalculationService, إلخ).

**الإجراء:** composite index `(status, is_active_employee, company_id)` (مذكور في 5.1).

---

## 7. خطة تطبيق الـ Migrations (Migration Plan)

> **⚠️ كل الـ migrations هنا additive فقط — لا DROP، لا DELETE، لا TRUNCATE.**

### 7.1 Migration جديد: `database/migrations/2026_07_21_000001_add_users_composite_indexes.php`
- يضيف الفهارس في 5.1 فقط.
- يحوي `try/catch` لـ `QueryException` لتجاهل `Duplicate key name` (idempotent).
- يحوي `down()` يحذف نفس الفهارس.

### 7.2 Migration جديد: `database/migrations/2026_07_21_000002_add_attendance_query_indexes.php`
- يضيف الفهارس في 5.2 و 5.3 و 5.4.
- `FULLTEXT index` فقط على MySQL.

### 7.3 Migration جديد: `Modules/AttendanceIntegration/database/migrations/2026_07_21_000005_add_audit_logs_indexes.php`
- يضيف الفهارس في 5.10 و 5.11.

### 7.4 Migration جديد: `Modules/Users/database/migrations/2026_07_21_000001_add_vacation_query_indexes.php`
- يضيف الفهارس في 5.7 و 5.8.

### 7.5 Migration جديد: `Modules/FingerprintDevices/database/migrations/2026_07_21_000002_add_device_query_indexes.php`
- يضيف الفهارس في 5.9 و 5.10.

### 7.6 Migration جديد: `Modules/Shifts/database/migrations/2026_07_21_000001_add_schedule_query_indexes.php`
- يضيف الفهارس في 5.6 و 5.15 و 5.16 و 5.17.

### 7.7 Migration جديد: `Modules/Holidays/database/migrations/2026_07_21_000001_add_holiday_query_index.php`
- يضيف الفهرس في 5.13.

### 7.8 Migration جديد: `Modules/Subordinations/database/migrations/2026_07_21_000001_add_subordination_query_index.php`
- يضيف الفهرس في 5.14.

### 7.9 Migration جديد: `database/migrations/2026_07_21_000003_add_general_audit_indexes.php`
- يضيف الفهارس في 5.12.

---

## 8. تعديلات على الاستعلامات (Query Modifications)

> **⚠️ القاعدة الذهبية:** كل تعديل هنا يجب ألا يغيّر الـ output أو الـ behavior — فقط يجعل الاستعلام يستخدم index بدلاً من table scan.

### 8.1 `UserRepository::getAll()`

**قبل:**
```php
->latest()
->paginate($perPage);
```

**بعد:**
```php
->orderBy('users.id', 'desc')  // يضمن استخدام PK index
->paginate($perPage);
```

**ملاحظة:** `latest()` يستخدم `created_at` افتراضياً. التغيير إلى `id` يجعل الترتيب يستخدم PK index (أسرع).

### 8.2 `UserRepository::applyFilters()`

**قبل:**
```php
$query->when($filters['company_id'] ?? null, function (Builder $q, int $companyId): void {
    $q->where('company_id', $companyId);
});
```

**بعد:** (لا تغيير — فقط توثيق)

### 8.3 `AttendanceSessionRepository::getByUserAndDate()`

**قبل:**
```php
->where('user_id', $userId)
->where('attendance_date', $date)
->get();
```

**بعد:** (لا تغيير — index `(user_id, attendance_date)` موجود)

### 8.4 `ScheduleEntryRepository::getCalendar()`

**قبل:**
```php
->where('schedule_period_id', $periodId)
->whereBetween('date', [$from, $to])
->orderBy('date')
->get();
```

**بعد:** (لا تغيير منطقي — الـ index الجديد في 5.6 يخدمه)

### 8.5 إضافة Method جديدة `UserRepository::getActiveByCompany()`

```php
/**
 * Get active employees for a company, optimised for dropdowns.
 *
 * @return Collection<int, User>
 */
public function getActiveByCompany(int $companyId): Collection
{
    return $this->query()
        ->select(['id', 'employee_code', 'first_name', 'last_name', 'name', 'email', 'branch_id', 'department_id'])
        ->where('company_id', $companyId)
        ->where('status', 1)
        ->where('is_active_employee', true)
        ->orderBy('first_name')
        ->get();
}
```

**السبب:** يستخدم الفهرس الجديد `idx_users_company_status_active`.

---

## 9. اختبارات التحقق (Verification Tests)

### 9.1 Test: تطبيق الـ Migrations على بيانات حقيقية

```php
test('adds indexes without losing data', function () {
    // قبل
    $userCountBefore = User::count();
    $sessionCountBefore = AttendanceSession::count();
    $logCountBefore = RawAttendanceLog::count();

    // تشغيل migrations الفهرسة
    artisan('migrate', ['--path' => 'database/migrations/2026_07_21_*']);
    artisan('migrate', ['--path' => 'Modules/*/database/migrations/2026_07_21_*']);

    // بعد
    expect(User::count())->toBe($userCountBefore);
    expect(AttendanceSession::count())->toBe($sessionCountBefore);
    expect(RawAttendanceLog::count())->toBe($logCountBefore);
});
```

### 9.2 Test: EXPLAIN يستخدم Index

```php
test('user query uses index', function () {
    DB::enableQueryLog();
    User::where('company_id', 1)
        ->where('status', 1)
        ->where('is_active_employee', true)
        ->limit(10)
        ->get();

    $queries = DB::getQueryLog();
    $sql = $queries[0]['query'];

    // في MySQL: يجب أن لا يحتوي 'ALL' (table scan)
    if (DB::getDriverName() === 'mysql') {
        $explain = DB::select('EXPLAIN ' . $sql);
        expect($explain[0]->type)->not->toBe('ALL');
    }
});
```

### 9.3 Test: Rollback لا يحذف بيانات

```php
test('rollback does not delete data', function () {
    $userCountBefore = User::count();

    artisan('migrate:rollback', ['--step' => 9]); // كل migrations الفهرسة

    expect(User::count())->toBe($userCountBefore);

    artisan('migrate'); // إعادة
});
```

### 9.4 Test: لا تغيير في المخرجات (Output Equivalence)

```php
test('repository output unchanged after optimization', function () {
    $before = User::with($this->defaultWith)
        ->where('company_id', 1)
        ->paginate(20)
        ->toArray();

    artisan('migrate', ['--path' => 'database/migrations/2026_07_21_*']);

    $after = User::with($this->defaultWith)
        ->where('company_id', 1)
        ->paginate(20)
        ->toArray();

    expect($after)->toEqual($before);
});
```

### 9.5 Test: الفهارس موجودة فعلياً

```php
test('indexes are created', function () {
    $indexes = DB::select("SHOW INDEX FROM users");
    $indexNames = collect($indexes)->pluck('Key_name')->unique();

    expect($indexNames)->toContain('idx_users_company_status_active');
});
```

---

## 10. معايير النجاح (Success Criteria)

> **كل المعايير قابلة للقياس ومرتبطة بنتيجة المستخدم/الأعمال، لا بالتنفيذ التقني.**

1. **SC-1** زمن تحميل قائمة الموظفين (`/users` مع 20 سجل لكل صفحة) **< 200ms** على 10,000+ موظف (قبل: > 1 ثانية).
2. **SC-2** زمن استعلام حضور موظف في شهر كامل (`attendance_sessions WHERE user_id = ? AND attendance_date BETWEEN`) **< 100ms**.
3. **SC-3** زمن استعلام الإجازات المعلقة (`user_vacation_requests WHERE status = 'pending'`) **< 50ms** على 50,000+ طلب.
4. **SC-4** زمن استعلام التقويم الشهري (`schedule_entries WHERE schedule_period_id = ? AND date BETWEEN`) **< 150ms** على 100,000+ سجل.
5. **SC-5** 100% من البيانات الموجودة في كل جدول محفوظة بعد تطبيق الـ migration (لا فقدان سجلات).
6. **SC-6** كل الاختبارات الموجودة (existing tests) تستمر بالنجاح بدون تعديل.
7. **SC-7** كل صفحة في التطبيق تستمر بالعمل بدون تغيير في الـ output.
8. **SC-8** عدد الـ indexes المضافة: **30-40 فهرس** موزعة على ~9 migrations.
9. **SC-9** كل migration له `down()` متطابق، و`migrate:rollback` ينجح بدون خطأ.
10. **SC-10** استخدام CPU على خادم قاعدة البيانات ينخفض **20-40%** في ساعات الذروة (مراقبة عبر `SHOW PROCESSLIST`).

---

## 11. الافتراضات (Assumptions)

1. **A-1** بيئة التطوير الحالية تستخدم SQLite — يجب أن تعمل الفهارس على SQLite و MySQL و PostgreSQL.
2. **A-2** حجم البيانات في الإنتاج يتراوح بين 5,000-50,000 موظف و 100,000-1,000,000 سجل حضور شهرياً.
3. **A-3** الاستعلامات الحرجة مُحددة في 6.x بناءً على قراءة الكود الحالي.
4. **A-4** الفهارس الحالية (الموجودة في migrations السابقة) تعمل كما هو متوقع.
5. **A-5** لا توجد خطة حالياً لـ partitioning أو sharding — الفهارس كافية للمرحلة الحالية.
6. **A-6** لم يتم تشغيل `migrate:fresh` أو `migrate:refresh` في الماضي القريب (البيانات حقيقية).
7. **A-7** الـ DB user المستخدم في الإنتاج يملك صلاحية `CREATE INDEX` (عادة نعم في MySQL/PostgreSQL، وقد يحتاج `GRANT` على بعض الاستضافات).
8. **A-8** الفهارس ستزيد حجم قاعدة البيانات بنسبة 10-25% — مقبول مقابل مكاسب السرعة.

---

## 12. المخاطر (Risks & Mitigations)

| المخاطرة | الاحتمال | الأثر | التخفيف |
|----------|---------|-------|---------|
| فشل migration بسبب `Duplicate key name` | متوسط | منخفض | `try/catch` حول `$table->index()` |
| بطء شديد في `CREATE INDEX` على جدول ضخم | منخفض | متوسط | استخدام `ALTER TABLE ... LOCK=NONE` (إن وُجد) أو جدولة في نافذة صيانة |
| تعارض مع index موجود بنفس الاسم | منخفض | منخفض | التحقق من `SHOW INDEX` قبل الإضافة |
| ازدياد حجم DB بشكل ملحوظ | مؤكد | منخفض | مقبول (< 25%) — يمكن المراقبة |
| كسر استعلام بسبب تغيير orderBy | منخفض | متوسط | اختبار `output equivalence` قبل النشر |

---

## 13. خطة التنفيذ (Implementation Plan)

```
المرحلة 1 — فهارس جدول users (الأساسي)         [~1 ساعة]
المرحلة 2 — فهارس Attendance + Raw Logs          [~1 ساعة]
المرحلة 3 — فهارس Vacations                     [~30 دقيقة]
المرحلة 4 — فهارس FingerprintDevices + Sync      [~30 دقيقة]
المرحلة 5 — فهارس Shifts + Schedules            [~1 ساعة]
المرحلة 6 — فهارس Tables صغيرة (Holidays, Subordinations) [~15 دقيقة]
المرحلة 7 — تعديلات الاستعلامات الحرجة          [~1 ساعة]
المرحلة 8 — اختبارات التحقق                      [~1 ساعة]
المرحلة 9 — اختبارات EXISTING (php artisan test)  [~30 دقيقة]
المرحلة 10 — اختبار الأداء (EXPLAIN)              [~30 دقيقة]
```

**الزمن الإجمالي المقدر:** ~7-8 ساعات عمل.

---

## 14. قائمة المراجعة قبل النشر (Pre-Deploy Checklist)

- [ ] كل الـ migrations الجديدة تعمل على SQLite و MySQL في بيئة التطوير.
- [ ] كل migration يحوي `try/catch` لـ `QueryException` (idempotent).
- [ ] كل migration يحوي `down()` متطابق.
- [ ] `php artisan migrate:rollback --step=N` يعمل بنجاح.
- [ ] `php artisan test` ينجح بدون أخطاء.
- [ ] `php artisan pint` يمر بدون تغييرات.
- [ ] `COUNT(*)` لكل الجداول متطابق قبل وبعد.
- [ ] `EXPLAIN` على الاستعلامات الحرجة يُظهر استخدام index.
- [ ] لا تغيير في أي UI أو output.
- [ ] لا تغيير في Repository/Service signatures (backward compatible).

---

## 15. الملاحق (Appendix)

### 15.1 أوامر التحقق اليدوية

```bash
# 1. تشغيل migrations
php artisan migrate

# 2. التحقق من البيانات
php artisan tinker
>>> \Modules\Users\Models\User::count()
>>> \Modules\Attendance\Models\AttendanceSession::count()
>>> \Modules\Attendance\Models\RawAttendanceLog::count()

# 3. التحقق من الفهارس (MySQL)
DB::statement('SHOW INDEX FROM users');

# 4. EXPLAIN استعلام
EXPLAIN SELECT * FROM users WHERE company_id = 1 AND status = 1 LIMIT 20;

# 5. تشغيل الاختبارات
php artisan test

# 6. تنسيق الكود
php artisan pint
```

### 15.2 ملاحظات على Database Drivers

| Driver | دعم FULLTEXT | دعم Composite Index | دعم Expression Index |
|--------|--------------|----------------------|----------------------|
| MySQL 8 | ✅ | ✅ | ⚠️ محدود |
| PostgreSQL 14+ | ✅ (مع `tsvector`) | ✅ | ✅ |
| SQLite 3 | ⚠️ (FTS5) | ✅ | ❌ |

### 15.3 معايير الرفض (Rejection Criteria)

يُرفض الـ PR إذا:
- ❌ أي migration يحوي `dropColumn` على عمود فيه بيانات.
- ❌ أي migration يحوي `dropIfExists` على جدول.
- ❌ `php artisan test` يفشل.
- ❌ `COUNT(*)` يتغيّر قبل/بعد.
- ❌ أي UI يتغير.
- ❌ أي Repository/Service public method يتغير توقيعه.

---

## 16. خلاصة (Summary)

هذه الميزة تحسينية بحتة — **لا تحذف، لا تغيّر، لا تكسر**. تضيف فهارس ذكية على أعمدة تُستخدم بكثرة في استعلامات موجودة فعلاً، وتعدّل بعض الاستعلامات لتستخدم هذه الفهارس. النتيجة: **نفس التطبيق، نفس البيانات، نفس الواجهة، فقط أسرع**.
