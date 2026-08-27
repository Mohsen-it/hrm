# 010 — تحسين وتثبيت النظام — خطة التنفيذ التقنية

**الإصدار:** 1.0.0
**التاريخ:** 2026-08-27
**الحالة:** جاهز للتنفيذ (`tasks.md` التالي)
**المواصفة:** [spec.md](./spec.md)
**المهام:** [tasks.md](./tasks.md)
**التقدم:** [progress.md](./progress.md)
**الفرع:** `010-system-optimization-stability`

---

## 1. Technical Context

| البند | القيمة | المبرر |
|------|--------|--------|
| **لغة الخلفية** | PHP 8.3+ | Constitution § 0 + AGENTS.md |
| **إطار الخلفية** | Laravel 13 + `nwidart/laravel-modules` | Constitution § II |
| **قاعدة البيانات** | SQLite (dev) / MySQL 8.0+ (prod) | Constitution § IV |
| **Cache الحالي** | `database` → **سيتحول لـ `redis`** | `.env:39` + `config/cache.php:121` tags لا تعمل على database |
| **Queue الحالي** | `database` → **سيتحول لـ `redis`** | `config/queue.php:16` + 20 Job |
| **Session الحالي** | `database` → `redis` أو `file` | `.env:41` |
| **Redis client** | `predis/predis ^3.5` (متاح) أو `phpredis` | `composer.json` + `config/database.php:146` |
| **عدد migrations جديدة** | 3 | spec § 8 |
| **عدد ملفات config معدلة** | 4 (`cache.php` لا, `queue.php`, `logging.php`, `database.php`) | — |
| **عدد Services معدلة** | 4 (`AbsenceCalculationService`, `DevicePushService`, `HolidayIntegrationService`, `ZoneService`) | BR-A1..A5 |
| **عدد Controllers معدلة** | 3 (`RotationsController`, `ScheduleCalendarController`, `SmartAbsenceController`) | BR-A2..A3 |
| **Frontend** | `vite.config.js` + `app.js` + `DashboardChart.vue` | BR-F1..F5 |
| **لا مكتبات جديدة** | — | Constitution § X |
| **التنسيق** | `php artisan pint` | Constitution § VIII |
| **الاختبارات** | `php artisan test` | Constitution § VIII |

**القرارات المعمارية:** راجع spec § 4 + § 11. لا أسئلة مفتوحة.

---

## 2. Constitution Check

### Gate 1 — المعمارية الطبقية (§ II + § XIV.1)

| القاعدة | الحالة | الدليل |
|---------|--------|--------|
| Controller → Service → Repository → Model | ✅ PASS | إصلاح المخالفات: نقل `RotationGroup::find` من Controller إلى `RotationService` |
| Service يحوي المنطق | ✅ PASS | نقل `AbsenceCalculationService` تجميع إلى Service (كان في Controller) |
| Repository Eloquent فقط | ✅ PASS | نقل `ShiftReportsService` DB::raw إلى Repository |
| Controller نحيف | ✅ PASS | `RotationsController:438` loop → `RotationService::getTimeline()` |
| DI بدل `app()` | ✅ PASS | `DevicePushService:41` → `__construct(DeviceCommandService)` |

### Gate 2 — الأمان (§ V)

| القاعدة | الحالة | الدليل |
|---------|--------|--------|
| لا secrets | ✅ PASS | لا credentials في الكود |
| Spatie Permission | ✅ N/A | لا صلاحيات جديدة |
| `APP_DEBUG=false` في prod | ✅ PASS | P0 task |

### Gate 3 — الأداء (§ VI)

| القاعدة | الحالة | الدليل |
|---------|--------|--------|
| Eager loading | ✅ PASS | إصلاح N+1 في `RotationsController:860` → `withCount` |
| Indexes على FK | ✅ PASS | 3 migrations جديدة |
| Composite indexes | ✅ PASS | `idx_esc_active` + `deleted_at` |
| `select only needed` | ✅ PASS | `UserRepository::getActiveByCompany` يحدد columns |
| لا DB داخل loop | ✅ PASS | إزالة `find()` من حلقة `bulkTransfer` |
| Pagination | ✅ PASS | منع `per_page=all` على الجداول الكبيرة |
| Caching | ✅ PASS | `Cache::tags` مع redis + `holidays:active` |

### Gate 4 — الواجهة (§ VII)

| القاعدة | الحالة | الدليل |
|---------|--------|--------|
| `<DataTable />` بدل `<table>` | ✅ PASS | استبدال 15 table خام |
| `defineAsyncComponent` | ✅ PASS | `DashboardChart` lazy |
| RTL | ✅ N/A | لا تغيير |

### Gate 5 — البساطة (§ X)

| القاعدة | الحالة | الدليل |
|---------|--------|--------|
| لا مكتبات جديدة | ✅ PASS | لا `composer require` جديد (predis موجود) |
| لا future-proofing | ✅ PASS | فقط إصلاحات تخدم كود قائم |

### Gate 6 — قابلية التوسع (§ XIV)

| القاعدة | الحالة | الدليل |
|---------|--------|--------|
| Stateless Services | ✅ PASS | لا state جديد |
| Queue for heavy | ✅ PASS | `after_commit=true` + `timeout` fix |
| Lazy Loading | ✅ PASS | frontend chunks + backend `chunk()` |

### Gate 7 — الحفاظ على البيانات

| القاعدة | الحالة | الدليل |
|---------|--------|--------|
| BR-13: ممنوع DROP | ✅ PASS | migrations إضافة index فقط |
| BR-14: `Schema::table()->index()` فقط | ✅ PASS | spec § 8 |
| BR-15: COUNT parity | ✅ PASS | progress.md § validation |

**Constitution Check overall: ✅ PASS**

---

## 3. ملخص التغييرات

### 3.1 الإعدادات (Config + Env)

| الملف | التغيير |
|------|---------|
| `D:\hrm\.env` | `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis`, `REDIS_CLIENT=predis`, `VITE_REVERB_HOST=10.10.250.2`, `APP_DEBUG=false` (prod), `LOG_STACK=daily` |
| `config/queue.php:43-44` | `retry_after=610` (أو env `DB_QUEUE_RETRY_AFTER=610`), `after_commit=true` |
| `config/logging.php` | إضافة channel `attendance_push` (daily, 14 days), `hrm_server` (daily) |
| `config/cache.php` | لا تغيير (redis موجود) |
| `config/database.php:35` | `busy_timeout=5000, journal_mode=WAL` لـ sqlite, `sticky=true` لـ mysql |

### 3.2 قاعدة البيانات (3 migrations)

| الملف | الجداول | Indexes |
|------|---------|---------|
| `database/migrations/2026_08_27_000001_add_missing_deleted_at_indexes.php` | `attendance_sessions`, `raw_attendance_logs`, `shifts`, `pay_codes`, `att_codes` | 5 × `deleted_at` |
| `database/migrations/2026_08_27_000002_add_esc_active_index.php` | `att_employee_shift_categories` | `idx_esc_active` |
| `database/migrations/2026_08_27_000003_fix_holidays_index.php` | `holidays` | تعديل ليشمل `end_date` |

### 3.3 النماذج (Models)

| الملف | التغيير |
|------|---------|
| `Modules/Attendance/app/Models/AttendanceSession.php:144` | `whereDate` → `whereBetween` في `scopeBetweenDates` |
| لا نماذج جديدة | — |

### 3.4 الخدمات (Services)

| الملف | التغيير |
|------|---------|
| `Modules/Shifts/app/Services/AbsenceCalculationService.php:221,406,560,636,833` | `Cache::remember('holidays:active',3600, fn()=>Holiday::active()->get())` بدل 5× `get()` + تجميع SQL + `chunk` |
| `Modules/FingerprintDevices/app/Services/DevicePushService.php:41` | `__construct(DeviceCommandService)` بدل `app()` |
| `Modules/Holidays/app/Services/HolidayIntegrationService.php:87` | `__construct(HolidayService)` بدل `app()` |
| `Modules/Zones/Services/ZoneService.php` | إصلاح `Cache::tags` (يعمل مع redis) |
| `Modules/Shifts/app/Services/RotationService.php:415` | استخدام Repository بدل `Model::find` مباشر |

### 3.5 المستودعات (Repositories)

| الملف | التغيير |
|------|---------|
| `Modules/Shifts/app/Repositories/ShiftReportsService.php:53` | نقل `DB::raw` إلى `ShiftReportRepository` |
| `app/Traits/PaginatesResults.php:18` | منع `per_page=all` على الجداول الكبيرة أو `cursorPaginate` |
| `Modules/Users/app/Repositories/UserRepository.php:291` | إصلاح `LIKE "%"` → `whereFullText` أو `like "term%"` |

### 3.6 المتحكمات (Controllers)

| الملف | التغيير |
|------|---------|
| `Modules/Shifts/app/Http/Controllers/RotationsController.php:210,438,860,792` | نقل business إلى `RotationService`, `withCount`, إزالة N+1 |
| `Modules/Shifts/app/Http/Controllers/ScheduleCalendarController.php:65` | DI بدل `app(AbsenceCalculationService)` |
| `Modules/Shifts/app/Http/Controllers/SmartAbsenceController.php:500` | نقل تقرير كامل إلى `AbsenceCalculationService` |

### 3.7 FormRequests

| الملف | التغيير |
|------|---------|
| `Modules/Shifts/app/Http/Requests/StoreRotationGroupRequest.php` | جديد لـ `addGroup` |
| `Modules/Shifts/app/Http/Requests/TransferRotationRequest.php` | جديد لـ `transfer/bulkTransfer` |

### 3.8 الوظائف المجدولة (Scheduler)

| الملف | التغيير |
|------|---------|
| `routes/console.php` | نقل Schedules من Providers + إضافة `prune-failed`, `cache:prune-stale-tags`, `adms:vacuum` |
| `Modules/Attendance/Providers/AttendanceServiceProvider.php:90` | إضافة `onOneServer()` + `withoutOverlapping(10)` |
| `Modules/FingerprintDevices/Providers/FingerprintDevicesServiceProvider.php:70` | نفس |

### 3.9 الواجهة الأمامية (Frontend)

| الملف | التغيير |
|------|---------|
| `vite.config.js` | إضافة `build.rollupOptions.output.manualChunks` + `chunkSizeWarningLimit` |
| `resources/js/app.js:2` | إزالة `all.min.css` → import أيقونات محددة |
| `resources/js/Components/dashboard/DashboardChart.vue:3` | `defineAsyncComponent` + subset `registerables` |
| `resources/js/Pages/Attendance/DailySummaries/Index.vue:386` | استخراج `ViolationTable.vue` |
| `resources/css/app.css:274-410` | حذف CSS الميت بعد التأكد |

### 3.10 Jobs

| الملف | التغيير |
|------|---------|
| `Modules/Attendance/Jobs/ProcessRawLogsChunk.php` | إضافة `tries=3, backoff=10, failed()` |
| `Modules/Attendance/Jobs/RecalculateDailySummariesChunk.php` | نفس |
| `Modules/FingerprintDevices/Jobs/SyncUserToDeviceViaBridgeJob.php:18` | `tries=3` بدل `1` |
| `Modules/AttendanceIntegration/Jobs/AttendanceIngestionJob.php:28` | `timeout=80` أو `retry_after=610` |

### 3.11 اختبارات

| الملف | التغيير |
|------|---------|
| `tests/Feature/SystemStabilityTest.php` | جديد — 6 tests (cache tags, queue retry, indexes, no N+1, logs) |

---

## 4. ترتيب التنفيذ

### المرحلة P0 — Infrastructure الحرج (يوم 1) — لا يعتمد على شيء

1. `config/queue.php` — `retry_after`, `after_commit`
2. `config/logging.php` — إضافة `attendance_push` channel
3. `.env.example` + `.env` — `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER`, `REDIS_CLIENT`, `VITE_REVERB_HOST`
4. `config/database.php` — `busy_timeout`, `WAL`, `sticky`

**اختبار:** `php artisan config:clear && php artisan cache:clear && Cache::tags()->flush()` يعمل

### المرحلة P1 — Database (يوم 1-2) — يعتمد على P0

5. Migration `2026_08_27_000001` — deleted_at indexes
6. Migration `2026_08_27_000002` — esc_active
7. Migration `2026_08_27_000003` — holidays
8. `AttendanceSession.php:144` — `whereBetween`
9. `php artisan migrate` + `verify_counts`

### المرحلة P2 — Architecture (يوم 2-3) — يعتمد على P0+P1

10. `DevicePushService`, `HolidayIntegrationService` — DI
11. `AbsenceCalculationService` — cache + SQL aggregation
12. `RotationsController` — نقل business + N+1 fix
13. `PaginatesResults` — منع `all`
14. Jobs — `tries/backoff`

### المرحلة P3 — Frontend (يوم 3-4) — مستقل

15. `vite.config.js` — manualChunks
16. `app.js` + `DashboardChart.vue` — lazy + subset
17. `ViolationTable.vue` — استخراج
18. `app.css` — حذف الميت
19. `vite build` + Lighthouse

### المرحلة P4 — Scheduler + Validation (يوم 4-5)

20. `routes/console.php` — نقل schedules + إضافة prune
21. `Supervisor/systemd` — إعداد (توثيق فقط)
22. `SystemStabilityTest.php` — 6 tests
23. `php artisan test && pint && migrate:rollback && migrate`

---

## 5. الاعتبارات الخاصة

### 5.1 Redis بدون extension
إذا لم يكن `phpredis` مثبتاً على Windows, الحل: `REDIS_CLIENT=predis` (pure PHP, موجود في `composer.json`). لا حاجة لـ `pecl install`.

### 5.2 Migration على جدول ضخم
`CREATE INDEX` على 1M سجل < 10s على SSD. لا حاجة لـ `LOCK=NONE` في هذه المرحلة. `try/catch` يضمن idempotency.

### 5.3 Fallback لـ Cache
`failover` store موجود في `config/cache.php:100` (`[database, array]`). يمكن تفعيله: `CACHE_STORE=failover` لو Redis سقط.

### 5.4 Rollback
كل migration `down()` يحذف نفس index. كل config `down` = إعادة `.env` القديم + `config:clear`.

---

## 6. خطة التحقق (Validation Plan)

1. **Snapshot counts** قبل أي migration
2. `php artisan migrate` — ينجح
3. **Verify counts** — متطابق
4. **Cache tags test** — `flush` يعمل
5. **Queue test** — `retry_after` > `timeout`
6. **EXPLAIN** — لا `ALL`
7. **Rollback + Re-migrate** — آمن
8. **php artisan test** — 100%
9. **pint** — نظيف
10. **UI manual** — لا تغيير
11. **vite build** — < 200KB initial
12. **Lighthouse** — > 85

---

## 7. خطة النشر (Deployment)

### Staging
```bash
# 1. Backup
mysqldump -u root -p hrm > backup_pre_010.sql
cp .env .env.bak.010

# 2. Pull branch
git checkout 010-system-optimization-stability
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 3. Env
# عدّل .env: CACHE_STORE=redis, QUEUE_CONNECTION=redis, ...

# 4. Migrate
php artisan migrate --force

# 5. Cache
php artisan config:clear
php artisan cache:clear
php artisan queue:restart

# 6. Supervisor
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl restart hrm-queue:*

# 7. Cron
(crontab -l; echo "* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1") | crontab -

# 8. Verify
php artisan test
php artisan schedule:list
redis-cli ping  # PONG
```

### Production
نفس الخطوات + نافذة صيانة خفيفة + مراقبة `slow_query_log` 24h.

### Rollback
```bash
php artisan migrate:rollback --step=3 --force
cp .env.bak.010 .env
php artisan config:clear
sudo supervisorctl restart hrm-queue:*
```

---

## 8. المخاطر والتخفيف

| المخاطرة | التخفيف |
|----------|---------|
| Redis down | `failover` store + `CACHE_STORE=file` مؤقت |
| Index يأخذ وقت | off-peak + try/catch |
| كسر UI | vite build + QA checklist |
| كسر API | لا تغيير signature + tests |

---

## 9. Done When

- [ ] 3 migrations + `down()`
- [ ] 4 configs معدلة
- [ ] 4 services معدلة
- [ ] 3 controllers معدلة
- [ ] `vite.config.js` manualChunks
- [ ] 6 Jobs معدلة
- [ ] `SystemStabilityTest.php` 6 tests
- [ ] `php artisan test` 100%
- [ ] `pint` نظيف
- [ ] `migrate:rollback --step=3` ينجح
- [ ] `COUNT(*)` parity
- [ ] `EXPLAIN` لا `ALL`
- [ ] `Cache::tags()->flush()` يعمل
- [ ] لا تغيير UI

---

*آخر تحديث: 2026-08-27*
