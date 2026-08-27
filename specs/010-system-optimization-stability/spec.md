# 010 — تحسين وتثبيت النظام الشامل (System Optimization & Long-Term Stability) — المواصفات

**الإصدار:** 1.0.0
**التاريخ:** 2026-08-27
**الحالة:** مسودة — جاهز للتنفيذ
**النوع:** تحسين أداء + استقرار + قابلية توسع — غير مُخرّب للبيانات
**الوحدات المشمولة:** `Core (Companies, Branches, Departments, Positions, Grades, Shifts, Users, Attendance, FingerprintDevices, Holidays, Vacations, Settings, Zones, Subordinations)` + `AttendanceIntegration` + `UserActivity` + `Infrastructure (Cache, Queue, Logging, Scheduler)`
**المرجع:** `.specify/memory/constitution.md § VI + § XIV` + تقارير التدقيق 2026-08-27

---

## 1. نظرة عامة (Overview)

النظام يعمل حالياً بإعدادات تطوير (`CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database` في `D:\hrm\.env:39-41`) رغم توفر `predis/predis` و `redis` config. هذا يضع ثلاث طبقات حرجة (Cache + Queue + Session) على نفس اتصال MySQL مع polling — عنق الزجاجة رقم 1.

بالإضافة إلى انعدام `Cache::tags` على `database` store (`Modules/Settings/Models/Setting.php:121` + `Modules/Zones/Services/ZoneService.php`), وتضارب `retry_after=90` مع `timeout=600` في `AttendanceIngestionJob.php:28`, وغياب `after_commit`, و15 جدول بلا `deleted_at` index, و300+ موضع `LIKE "%term%"` بلا index, وfrontend بلا `manualChunks` مع FontAwesome كامل, وlogs بلا rotation (`hrm-laravel-server.log:19MB`, `adms-outbox.sqlite3:28MB`), وScheduler بلا cron.

**الهدف:** تحويل النظام من "يعمل في التطوير" إلى "مستقر لعمر طويل بدون فشل فجائي" عبر 4 محاور:

1. **Infrastructure** — Redis + Queue + Logging + Scheduler
2. **Database** — Indexes + Query Optimization + `whereBetween` + `deleted_at`
3. **Architecture** — إصلاح N+1 + `app()` violations + Fat Controllers + Caching
4. **Frontend** — Bundle Splitting + Component Reuse + Lazy Loading

> **⚠️ ضمانات حرجة:**
> - ✅ **لا حذف بيانات** — كل migrations إضافة فهارس فقط (`Schema::table()->index()`), لا `DROP` / `TRUNCATE`
> - ✅ **لا كسر وظائف** — كل تعديل يحافظ على نفس الـ output
> - ✅ **قابلة للعكس** — كل migration يحوي `down()` + كل config قابل للـ rollback عبر `.env`
> - ✅ **آمنة لكل البيئات** — SQLite (dev) + MySQL 8 (prod) + driver checks

---

## 2. قصص المستخدمين (User Stories)

| # | كـ | أستطيع | معيار القبول |
|---|----|--------|--------------|
| US-01 | مدير نظام | أن أرى زمن استجابة `/users` < 200ms على 10k موظف | `EXPLAIN` يستخدم `idx_users_company_status_active`, TTFB < 300ms |
| US-02 | مدير HR | أن أرى التقارير الشهرية للحضور < 500ms على 500k punch | `RawAttendanceLog` aggregation في SQL بدل PHP |
| US-03 | مهندس DevOps | أن أشغّل `CACHE_STORE=redis` بدون فقدان بيانات | `Cache::tags()->flush()` يعمل, `settings:group:*` يُمسح كاملاً |
| US-04 | مهندس DevOps | أن أرى Queue لا يكرر Jobs | `retry_after = timeout + 10`, `after_commit=true` |
| US-05 | مدير نظام | أن لا يمتلئ القرص بالـ logs | `hrm-laravel-server.log` daily rotation, `adms-outbox.sqlite3` VACUUM أسبوعي |
| US-06 | مطوّر | أن لا أواجه N+1 في `RotationsController` | `RotationGroup::with()` eager + لا `count()` داخل `map()` |
| US-07 | مستخدم نهائي | أن تحمل الصفحة < 1.5s حتى على 3G | `app.js` < 200KB initial, `DashboardChart` lazy, FontAwesome subset |
| US-08 | مطوّر | أن أشغّل `php artisan test` و `pint` بدون فشل | 0 regression |
| US-09 | مراقب | أن أرى Scheduler يعمل تلقائياً بدون تدخل يدوي | `schedule:run` كل دقيقة عبر cron/systemd + `withoutOverlapping` + `onOneServer` |

---

## 3. سيناريوهات الاستخدام والاختبار (Scenarios)

### السيناريو 1 — تفعيل Redis (P0)
1. تثبيت `phpredis` أو استخدام `predis` (تغيير `REDIS_CLIENT`), تشغيل `redis-server`
2. تغيير `.env`: `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis`
3. `php artisan config:clear && php artisan cache:clear`
4. اختبار: `Cache::tags(['settings'])->put('k','v',3600); Cache::tags(['settings'])->flush(); assert Cache::missing('k')`

**القبول:** `tags()->flush()` يمسح كل المفاتيح, latency < 2ms

### السيناريو 2 — إصلاح Queue retry
1. تغيير `config/queue.php:43` `retry_after=610` أو `AttendanceIngestionJob.php:28` `timeout=80`
2. تشغيل job داخل `DB::transaction` + `rollback` → الـ job لا يبقى في queue
3. تشغيل job طويل 100s → لا يُعاد قبل انتهائه

### السيناريو 3 — البحث لا يعمل table scan
1. `User::where('name','like','%ahmed%')->get()` → استبداله بـ `FULLTEXT` أو `where('name','like','ahmed%')` مع index
2. `EXPLAIN` يظهر `type=ref/range` بدل `ALL`

### السيناريو 4 — التقارير لا تستهلك ذاكرة
1. `AbsenceCalculationService::getMonthlyAbsenceReport()` على شهر بـ 2000 موظف → كان 60k iteration + `Holiday::active()->get()` 5 مرات
2. بعد: `Cache::remember('holidays:active',3600,...)` + تجميع SQL + `chunk(1000)`

### السيناريو 5 — Frontend
1. فتح Network tab → `app-*.js` < 120KB, `chart-*.js` منفصل, FontAwesome < 30KB
2. Lighthouse Performance > 85

### السيناريو 6 — Logs
1. `storage/logs/hrm-laravel-server.log` لم يعد ينمو بلا حدود → daily rotation
2. `zkteco-service/logs/bridge.log` rotation

### السيناريو 7 — لا تغيير وظيفي
1. فتح `/users`, `/attendance/sessions`, `/shifts/rotations`, `/vacations/requests`
2. نفس الأعمدة, نفس الفلاتر, نفس الـ pagination, لا console errors

---

## 4. المتطلبات الوظيفية (Functional Requirements)

### 4.1 Infrastructure (P0)

| # | القاعدة | التفصيل |
|---|---------|---------|
| BR-I1 | Cache = redis | `CACHE_STORE=redis` + `REDIS_CLIENT=predis` (أو تثبيت `phpredis`). إصلاح `Setting::forgetGroup` ليستخدم `tags` فعلياً |
| BR-I2 | Queue = redis | `QUEUE_CONNECTION=redis`, `retry_after = timeout + 10` لكل connection, `after_commit=true` للـ jobs الحساسة |
| BR-I3 | Session = redis أو file | لا `database` للـ session في الإنتاج |
| BR-I4 | Logging channels | إضافة `attendance_push` (daily, 14 days) + تحويل `hrm-laravel-server.log` إلى daily channel + `biodata` موجود |
| BR-I5 | Scheduler | تفعيل `php artisan schedule:run` كل دقيقة (cron/systemd), إضافة `prune-failed --hours=72`, `cache:prune-stale-tags` |
| BR-I6 | Queue workers | تشغيل عبر Supervisor/systemd: `queue:work --sleep=3 --max-jobs=1000 --max-time=3600 --tries=3 --backoff=10 --queue=default,biometrics` |

### 4.2 Database (P0-P1)

| # | القاعدة |
|---|---------|
| BR-D1 | إضافة `deleted_at` للفهارس المركبة على كل softDeletes table مفقود (12 جدول) |
| BR-D2 | إصلاح `AttendanceSession::scopeBetweenDates` من `whereDate` إلى `whereBetween` |
| BR-D3 | استبدال `LIKE "%term%"` بـ `FULLTEXT` أو `LIKE "term%"` حيث أمكن (`UserRepository.php:291`) |
| BR-D4 | منع `per_page=all` على الجداول الكبيرة (>10k) أو استخدام `cursorPaginate` |
| BR-D5 | إضافة `idx_esc_active (is_active, employee_id)` المفقود على `att_employee_shift_categories` |
| BR-D6 | حذف الفهارس المكررة (`att_sessions_user_date_idx` redundancy) — اختياري |
| BR-D7 | كل migration additive فقط + `safeIndex()` helper + `down()` |

### 4.3 Architecture (P1-P2)

| # | القاعدة |
|---|---------|
| BR-A1 | إزالة `app()` من Services → DI عبر `__construct` (`DevicePushService.php:41`, `HolidayIntegrationService.php:87`) |
| BR-A2 | نقل منطق Business من Controllers إلى Services (`RotationsController.php:438`, `SmartAbsenceController.php:500`) |
| BR-A3 | إصلاح N+1: لا `count()`/`find()` داخل `map()`/`foreach` (`RotationsController.php:860,792`) |
| BR-A4 | Cache لـ Holidays: `Cache::tags(['holidays'])->remember('holidays:active',3600, ...)` بدل 5× `get()` |
| BR-A5 | تجميع SQL بدل PHP في `AbsenceCalculationService::getMonthlyAbsenceReport` + `chunk()` |
| BR-A6 | إضافة `tries=3, backoff=10, failed()` لكل Jobs الناقصة (6 jobs) |
| BR-A7 | إضافة `FormRequest` لـ `addGroup/transfer/bulkTransfer` في `RotationsController` |

### 4.4 Frontend (P1-P2)

| # | القاعدة |
|---|---------|
| BR-F1 | `vite.config.js` — إضافة `build.rollupOptions.output.manualChunks` (vendor, charts, fontawesome) |
| BR-F2 | `DashboardChart.vue` — `defineAsyncComponent(() => import('chart.js'))` + `registerables` subset |
| BR-F3 | FontAwesome — استيراد أيقونات محددة فقط بدل `all.min.css` |
| BR-F4 | استبدال 15 `<table>` خام بـ `<DataTable />` أو `ViolationTable.vue` |
| BR-F5 | حذف CSS الميت `app.css:274-410` بعد التأكد من عدم الاستخدام |

---

## 5. المتطلبات غير الوظيفية (Non-Functional)

| المعيار | الحد المسموح | القياس |
|---------|--------------|--------|
| TTFB (صفحة) | < 1.5s | Lighthouse |
| Inertia request | < 300ms | Network tab |
| DB query (عادي) | < 100ms | `DB::listen` + `slow_query_log` |
| DB query (تقرير) | < 500ms | نفس |
| JS bundle initial | < 200KB | `vite build --analyze` |
| Queue job | < 30s (أو < timeout) | `queue:monitor` |
| Cache latency | < 2ms (redis) vs 10ms (database) | `Cache::get` benchmark |
| عدد queries لكل صفحة | < 10 | `Debugbar` |
| استقرار | 99.9% uptime, لا OOM | `supervisorctl status` + `storage/logs` |

---

## 6. بنية البيانات — الفهارس الجديدة (Data Model)

### 6.1 إصلاحات حرجة (P0)

| الجدول | الفهرس | الأعمدة | السبب |
|--------|--------|---------|-------|
| `attendance_sessions` | `idx_att_sessions_deleted` | `(deleted_at)` أو إضافته لنهاية composite | `WHERE deleted_at IS NULL` بلا index |
| `raw_attendance_logs` | `idx_raw_logs_deleted` | `(deleted_at)` | نفس |
| `shifts` | `idx_shifts_deleted` | `(deleted_at)` | نفس |
| `att_employee_shift_categories` | `idx_esc_active` | `(is_active, employee_id)` | مفقود تماماً (spec 008 §5.17) — `scope active()` |

### 6.2 إصلاحات استعلامات (P1)

| الموقع | قبل | بعد |
|--------|-----|-----|
| `AttendanceSession.php:144` | `whereDate('attendance_date', ...)` | `whereBetween('attendance_date', [$from,$to])` |
| `UserRepository.php:291` | `where('name','like',"%$search%")` | `FULLTEXT` أو `where('name','like',"$search%")` + index |
| `PaginatesResults.php:18` | `get()` لكل `per_page=all` | `cursorPaginate` أو رفض `all` على الجداول الكبيرة |

---

## 7. الاستعلامات الحرجة المُراجعة

| # | الاستعلام | المشكلة | الإصلاح |
|---|-----------|---------|---------|
| Q1 | `Holiday::active()->get()` ×5 في `AbsenceCalculationService` | 5 queries متطابقة بدون cache | `Cache::remember` واحد |
| Q2 | `RawAttendanceLog::whereBetween` + `get()` + `groupBy` في PHP | يحمل 500k صف في الذاكرة | تجميع SQL `selectRaw COUNT/SUM` + `chunk(5000)` |
| Q3 | `RotationsController:860` `count()` داخل `map()` | N+1 | `withCount('assignments')` |
| Q4 | `User::select` بدون `LIMIT` في `getActive()` | قد يرجع 10k | `paginate` أو `select` محدود |

---

## 8. خطة تطبيق الـ Migrations

| # | الملف | الجداول | النوع |
|---|-------|---------|-------|
| M1 | `database/migrations/2026_08_27_000001_add_missing_deleted_at_indexes.php` | `attendance_sessions`, `raw_attendance_logs`, `shifts`, `pay_codes`, `att_codes` | إضافة `deleted_at` |
| M2 | `database/migrations/2026_08_27_000002_add_esc_active_index.php` | `att_employee_shift_categories` | `idx_esc_active` |
| M3 | `database/migrations/2026_08_27_000003_fix_holidays_index.php` | `holidays` | تعديل `end_date` في الفهرس |
| M4 | — | — | لا migrations أخرى (الباقي إصلاح كود) |

كل migration يحوي `safeIndex()` + `down()` + `try/catch` لـ `QueryException`.

---

## 9. معايير النجاح (Success Criteria)

| # | المعيار | قابل للقياس |
|---|---------|-------------|
| SC-1 | `Cache::tags()->flush()` يمسح كل مفاتيح settings | Test: `put` ثم `flush` ثم `missing` |
| SC-2 | لا duplicate jobs (retry_after > timeout) | Log: 0 `DuplicatePunchException` مكرر |
| SC-3 | زمن `/users` < 200ms على 10k | `php artisan test` + `EXPLAIN type != ALL` |
| SC-4 | زمن تقرير الحضور الشهري < 500ms | Benchmark قبل/بعد |
| SC-5 | حجم `hrm-laravel-server.log` لا يتجاوز 14 يوم rotation | `storage/logs` < 50MB إجمالي |
| SC-6 | `adms-outbox.sqlite3` < 10MB بعد VACUUM | cron weekly |
| SC-7 | JS initial < 200KB | `vite build` analysis |
| SC-8 | 100% tests تمر | `php artisan test` |
| SC-9 | `php artisan pint` نظيف | 0 diff |
| SC-10 | 0 تغيير وظيفي (نفس الـ output) | Manual QA على 10 صفحات |

---

## 10. الافتراضات (Assumptions)

- A-1: `redis-server` يمكن تثبيته على نفس الخادم (أو استخدام `predis` بدون extension)
- A-2: الإنتاج MySQL 8.0+, التطوير SQLite — كل migrations متوافقة
- A-3: حجم البيانات: 5k-50k موظف, 100k-1M punch شهرياً
- A-4: `APP_ENV=local` حالياً → سيتحول لـ `production` عند النشر مع `APP_DEBUG=false`
- A-5: الفريق يوافق على `Supervisor` أو `systemd` لـ queue workers

---

## 11. المخاطر والتخفيف

| المخاطرة | الاحتمال | الأثر | التخفيف |
|----------|---------|-------|---------|
| Redis غير متاح | متوسط | حرج | `failover` store (`database` كـ fallback) أو `CACHE_STORE=file` مؤقتاً |
| `CREATE INDEX` على جدول ضخم | منخفض | متوسط | `try/catch` + تشغيل off-peak |
| كسر `Cache::tags` عند الرجوع لـ database | منخفض | متوسط | `Setting::forgetGroup` يدعم كلا الحالتين |
| زيادة حجم DB ~15% | مؤكد | منخفض | مقبول |
| كسر UI بعد bundle split | منخفض | متوسط | `vite build` + manual QA |

---

## 12. خارج النطاق (Out of Scope)

- FULLTEXT search كامل (يؤجل لـ 011)
- Partitioning / Sharding
- Read replicas
- Migration إلى PostgreSQL
- إعادة كتابة `AbsenceCalculationService` بالكامل (فقط تحسينات موضعية)

---

## 13. خطة التنفيذ — المراحل

```
المرحلة P0 — Infrastructure الحرج          [يوم 1]
المرحلة P1 — Database + Architecture       [يوم 2-3]
المرحلة P2 — Frontend + Logging + Polish  [يوم 4]
المرحلة P3 — Monitoring + Long-term       [يوم 5]
```

**الزمن الإجمالي:** ~5 أيام عمل (متقطع).

---

## 14. قائمة المراجعة قبل النشر (Pre-Deploy)

- [ ] `.env` الجديد مُختبر في staging (`CACHE_STORE=redis` يعمل)
- [ ] كل migrations تعمل على SQLite + MySQL
- [ ] `down()` يحذف نفس ما `up()` أضافه
- [ ] `php artisan migrate:rollback --step=N` ينجح
- [ ] `php artisan test` ينجح
- [ ] `php artisan pint` نظيف
- [ ] `COUNT(*)` متطابق قبل/بعد
- [ ] `EXPLAIN` يستخدم index
- [ ] لا تغيير UI
- [ ] `schedule:run` cron مُضاف

---

*آخر تحديث: 2026-08-27*
*المؤلف: Muse Spark — تدقيق شامل 4 محاور*
