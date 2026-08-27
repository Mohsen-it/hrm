# 010 — تحسين وتثبيت النظام — تقسيم المهام (Tasks)

**الإصدار:** 1.0.0
**التاريخ:** 2026-08-27
**الحالة:** جاهز للتنفيذ
**المواصفة:** [spec.md](./spec.md)
**الخطة:** [plan.md](./plan.md)
**التقدم:** [progress.md](./progress.md)
**الفرع:** `010-system-optimization-stability`

> **⚠️ ضمانات حرجة لكل مهمة:**
> - لا `DROP COLUMN` / `TRUNCATE` / `DELETE` / `migrate:fresh`
> - كل migration = `Schema::table()->index()` فقط + `safeIndex()` + `down()`
> - كل config تغيير قابل للـ rollback عبر `.env.bak`
> - لا تغيير public signature إلا بإضافة method جديدة

---

## ملخص المهام حسب المرحلة

| المرحلة | العنوان | الأولوية | المهام | معيار الاختبار المستقل |
|---------|---------|----------|--------|------------------------|
| **P0** | Infrastructure الحرج (Cache/Queue/Logging/Session) | **حرج** | T001-T008 (8) | `Cache::tags()->flush()` يعمل + `retry_after` > `timeout` |
| **P1** | Database (Indexes + Query Fix) | **عالي** | T009-T016 (8) | `EXPLAIN` لا `ALL` + `COUNT(*)` متطابق |
| **P2** | Architecture (N+1 + DI + Caching) | **عالي** | T017-T027 (11) | لا `app()` في Services + لا N+1 في Rotations |
| **P3** | Frontend (Bundle + Components) | **متوسط** | T028-T034 (7) | `vite build` < 200KB initial + Lighthouse >85 |
| **P4** | Scheduler + Jobs + Validation | **حرج** | T035-T044 (10) | `schedule:list` 15 task + `php artisan test` 100% |
| **Polish** | Polish & Cross-cutting | — | T045-T047 (3) | `pint` نظيف + `git status` متوقع |
| **المجموع** | | | **47 مهمة** | |

---

## ترتيب التنفيذ (Dependency Graph)

```
P0 (T001-T008) ──────┐
                     ├──► P1 (T009-T016) ──► P2 (T017-T027) ──┐
                     └──► P3 (T028-T034) ─────────────────────┤
                                                              ▼
                                                         P4 (T035-T044) ──► Polish (T045-T047)
```

- **P0 لا يعتمد على شيء** — يبدأ أولاً
- **P1 يعتمد على P0** (يحتاج redis tags)
- **P2 يعتمد على P0+P1** (يستخدم cache + indexes)
- **P3 مستقل** — يمكن تنفيذه بالتوازي مع P1/P2
- **P4 يعتمد على P2+P3** (يختبر كل شيء)
- **MVP = P0 + P1 + T017-T020 (DI fix) + T035 (test)** — الحد الأدنى لمنع الفشل الفجائي

---

## المرحلة P0: Infrastructure الحرج

> **الهدف:** منع الفشل الفجائي — إصلاح Cache/Queue/Logging التي تسبب اختناقاً فورياً

- [ ] **T001** [P0] Fix `config/queue.php:43-44` — غيّر `retry_after` إلى `610` (أو `env DB_QUEUE_RETRY_AFTER=610`) و `after_commit` إلى `true` للـ `database` و `redis` connections. **الملف:** `D:\hrm\config\queue.php:43-44`. **التعقيد:** بسيط. **التحقق:** `config('queue.connections.database.retry_after') === 610`.

- [ ] **T002** [P0] Fix `config/logging.php` — أضف channel جديد `attendance_push` (daily, path `storage/logs/attendance-push.log`, days 14, level warning) + channel `hrm_server` (daily, path `storage/logs/hrm-laravel-server.log`, days 14). **الملف:** `D:\hrm\config\logging.php:126`. **التعقيد:** بسيط. **التحقق:** `Log::channel('attendance_push')->info('test')` ينشئ الملف.

- [ ] **T003** [P0] Fix `config/database.php:35` — أضف `busy_timeout: 5000, journal_mode: WAL, synchronous: NORMAL` لـ `sqlite` + `sticky: true` لـ `mysql`. **الملف:** `D:\hrm\config\database.php:32-40`. **التعقيد:** بسيط. **التحقق:** `config('database.connections.sqlite.busy_timeout') === 5000`.

- [ ] **T004** [P0] Update `.env.example` — غيّر `CACHE_STORE=database` → `redis`, أضف `REDIS_CLIENT=predis`, `DB_QUEUE_RETRY_AFTER=610`, `VITE_REVERB_HOST=${REVERB_HOST}`. **الملف:** `D:\hrm\.env.example:23,38,40`. **التعقيد:** بسيط.

- [ ] **T005** [P0] Update `.env` (dev) — نفس تغييرات T004 لكن على `.env` الفعلي. **الملف:** `D:\hrm\.env:39-46,76`. **التعقيد:** بسيط. **التحقق:** `php artisan tinker --execute="echo config('cache.default')"` يطبع `redis`.

- [ ] **T006** [P0] Fix `Modules/Settings/Models/Setting.php:120-126` — إصلاح `forgetGroup()` ليعمل مع redis tags بشكل صحيح (إزالة fallback `forget('settings:group:')` عند توفر tags, أو جعله يمسح كل مفاتيح المجموعة عبر `Cache::tags(['settings'])->flush()`). **الملف:** `D:\hrm\Modules\Settings\app\Models\Setting.php:120`. **التعقيد:** متوسط. **التحقق:** `Cache::tags(['settings'])->put('a','v'); flush(); assert missing`.

- [ ] **T007** [P0] Fix `Modules/Zones/Services/ZoneService.php` — نفس إصلاح Cache tags. **الملف:** `D:\hrm\Modules\Zones\app\Services\ZoneService.php`. **التعقيد:** متوسط.

- [ ] **T008** [P0] Fix `app/Services/ZKTecoPythonBridgeService.php:395-419` — إصلاح كتابة PID على Windows (`file_put_contents($pidFile, getmypid())` بعد `start` بدل `echo $! > pidFile` الذي لا يعمل على Windows). **الملف:** `D:\hrm\app\Services\ZKTecoPythonBridgeService.php:395`. **التعقيد:** متوسط. **التحقق:** `startServiceProcess()` ينشئ `storage/app/zkteco-service.pid` على Windows.

---

## المرحلة P1: Database (Indexes + Query Fix)

> **الهدف:** إصلاح الفهارس المفقودة والاستعلامات البطيئة

- [ ] **T009** [P1] Create migration `database/migrations/2026_08_27_000001_add_missing_deleted_at_indexes.php` — إضافة `index(['deleted_at'])` على `attendance_sessions`, `raw_attendance_logs`, `shifts`, `pay_codes`, `att_codes` عبر `safeIndex()` helper (try/catch QueryException). **التعقيد:** بسيط. **المرجع:** spec § 6.1.

- [ ] **T010** [P1] Create migration `database/migrations/2026_08_27_000002_add_esc_active_index.php` — إضافة `idx_esc_active (is_active, employee_id)` على `att_employee_shift_categories`. **التعقيد:** بسيط. **المرجع:** spec § 6.1.

- [ ] **T011** [P1] Create migration `database/migrations/2026_08_27_000003_fix_holidays_index.php` — تعديل `idx_holidays_date_active` ليشمل `end_date` (حذف القديم وإضافة `(date, end_date, is_active)` أو إضافة فهرس جديد). **التعقيد:** بسيط. **المرجع:** تدقيق DB § 1.b.3.

- [ ] **T012** [P1] Fix `Modules/Attendance/app/Models/AttendanceSession.php:144` — تغيير `scopeBetweenDates` من `whereDate('attendance_date', ...)` إلى `whereBetween('attendance_date', [$from, $to])` ليستخدم `idx_att_sessions_user_date_status`. **التعقيد:** بسيط. **التحقق:** `EXPLAIN` يظهر `range` بدل `ALL`.

- [ ] **T013** [P1] Fix `Modules/Users/app/Repositories/UserRepository.php:291` — استبدال `where('name','like',"%$search%")` بـ `whereFullText` (MySQL) أو على الأقل `where('name','like',"$search%")` مع fallback. أو إضافة `FULLTEXT` index على `name,employee_code`. **التعقيد:** متوسط. **التحقق:** `EXPLAIN` لا `ALL`.

- [ ] **T014** [P1] Fix `Modules/Attendance/app/Repositories/AttendanceSessionRepository.php:226` — نفس إصلاح LIKE. **التعقيد:** متوسط.

- [ ] **T015** [P1] Fix `app/Traits/PaginatesResults.php:18` — منع `per_page=all` على الجداول الكبيرة (`attendance_sessions`, `raw_attendance_logs`): إذا `perPage === 'all'` و `count > 10000` → استخدم `cursorPaginate` أو أرجع خطأ `400`. **التعقيد:** متوسط. **التحقق:** `GET /attendance/sessions?per_page=all` على 100k سجل لا يسبب OOM.

- [ ] **T016** [P1] Run `php artisan migrate` + `verify_counts` — تشغيل كل migrations P1 والتحقق من `COUNT(*)` متطابق + `EXPLAIN` يستخدم indexes. **التعقيد:** بسيط. **التبعية:** T009-T015.

---

## المرحلة P2: Architecture (N+1 + DI + Caching)

> **الهدف:** إصلاح مخالفات الطبقات والأداء في الكود

- [ ] **T017** [P2] Fix `Modules/FingerprintDevices/app/Services/DevicePushService.php:41` — حقن `DeviceCommandService` عبر `__construct` بدل `app()`. **التعقيد:** بسيط.

- [ ] **T018** [P2] Fix `Modules/Holidays/app/Services/HolidayIntegrationService.php:87` — حقن `HolidayService` عبر `__construct` بدل `app()`. **التعقيد:** بسيط.

- [ ] **T019** [P2] Fix `Modules/Shifts/app/Http/Controllers/ScheduleCalendarController.php:65` — حقن `AbsenceCalculationService` عبر `__construct` بدل `app()`. **التعقيد:** بسيط.

- [ ] **T020** [P2] Fix `Modules/Shifts/app/Http/Controllers/RotationsController.php:210,416,566,651,793,897,914` — نقل كل `Model::find/query` مباشر إلى `RotationService` / `RotationRepository`. **التعقيد:** معقد. **التحقق:** لا `use Modules\Shifts\Models\RotationGroup` في Controller إلا للـ type-hint.

- [ ] **T021** [P2] Fix `Modules/Shifts/app/Http/Controllers/RotationsController.php:860` — استبدال `count()` داخل `map()` بـ `withCount('assignments')` أو `loadCount`. **التعقيد:** بسيط. **التحقق:** عدد queries = 1 بدل N.

- [ ] **T022** [P2] Fix `Modules/Shifts/app/Http/Controllers/RotationsController.php:792-802` — استبدال `find()` داخل حلقة `bulkTransfer` بـ `whereIn()->get()->keyBy('id')`. **التعقيد:** متوسط.

- [ ] **T023** [P2] Fix `Modules/Shifts/app/Services/AbsenceCalculationService.php:221,406,560,636,833` — إضافة `Cache::tags(['holidays'])->remember('holidays:active',3600, fn()=>Holiday::active()->get())` بدل 5× `get()`. **التعقيد:** متوسط. **التحقق:** query واحد بدل 5.

- [ ] **T024** [P2] Fix `Modules/Shifts/app/Services/AbsenceCalculationService.php:762-928` — تحويل `getMonthlyAbsenceReport` من `get()` + `groupBy` في PHP إلى تجميع SQL (`selectRaw COUNT/SUM`) + `chunk(5000)`. **التعقيد:** معقد. **التحقق:** memory < 50MB على 500k punch.

- [ ] **T025** [P2] Fix `Modules/Shifts/app/Services/RotationService.php:415` + `ShiftReportsService.php:53` — نقل `DB::raw` إلى Repository. **التعقيد:** متوسط.

- [ ] **T026** [P2] Create `Modules/Shifts/app/Http/Requests/StoreRotationGroupRequest.php` + `TransferRotationRequest.php` — نقل validation من `RotationsController:185,310,782` إلى FormRequests. **التعقيد:** متوسط.

- [ ] **T027** [P2] Fix `Modules/Attendance/app/Services/DailyAttendanceSummaryService.php:339` — نفس cache لـ holidays. **التعقيد:** بسيط.

---

## المرحلة P3: Frontend (Bundle + Components)

> **الهدف:** تقليل حجم التحميل وتحسين تجربة المستخدم

- [ ] **T028** [P3] Fix `vite.config.js` — إضافة `build.rollupOptions.output.manualChunks: {vendor:['vue','axios','@inertiajs/vue3'], charts:['chart.js','vue-chartjs'], pusher:['pusher-js','laravel-echo']}` + `chunkSizeWarningLimit: 500`. **التعقيد:** بسيط. **التحقق:** `npm run build` يظهر chunks منفصلة.

- [ ] **T029** [P3] Fix `resources/js/app.js:2` — استبدال `import '@fortawesome/fontawesome-free/css/all.min.css'` بـ `import { library } from '@fortawesome/fontawesome-svg-core'` + أيقونات محددة فقط. أو استخدام subset. **التعقيد:** متوسط. **التحقق:** حجم `fa-*` يقل من 250KB إلى < 30KB.

- [ ] **T030** [P3] Fix `resources/js/Components/dashboard/DashboardChart.vue:3` — تغيير `Chart.register(...registerables)` إلى `import { Chart, CategoryScale, LinearScale, BarElement } from 'chart.js'` (subset) + `defineAsyncComponent(() => import('./DashboardChart.vue'))` في `Dashboard.vue`. **التعقيد:** متوسط. **التحقق:** `DashboardChart-*.js` منفصل lazy.

- [ ] **T031** [P3] Extract `resources/js/Pages/Attendance/DailySummaries/Partials/ViolationTable.vue` — استخراج 3 جداول مكررة في `Index.vue:386,469,552` إلى component واحد. **التعقيد:** متوسط. **التبعية:** لا.

- [ ] **T032** [P3] Extract composable `resources/js/composables/useViolationFetch.js` — توحيد 3 دوال `fetchLateCheckIns/fetchMissingCheckOuts/fetchLateForVacation` المكررة. **التعقيد:** بسيط.

- [ ] **T033** [P3] Fix `resources/js/Pages/Attendance/DailySummaries/Index.vue` — تطبيق `T031+T032` + نقل `only: ['summaries','stats']` صحيح. **التعقيد:** متوسط. **التبعية:** T031,T032.

- [ ] **T034** [P3] Fix `resources/css/app.css:274-410` — حذف CSS الميت (`.card, .btn, .form-input` deprecated) بعد `grep` للتأكد من عدم الاستخدام. + إضافة `content-visibility: auto` للجداول الكبيرة. **التعقيد:** بسيط. **التحقق:** `app-*.css` يقل ~30% (137KB → ~95KB).

---

## المرحلة P4: Scheduler + Jobs + Validation

> **الهدف:** ضمان التشغيل المستمر والتحقق الشامل

- [ ] **T035** [P4] Fix Jobs — إضافة `public int $tries = 3; public int $backoff = 10; public function failed(Throwable $e): void` لـ 6 Jobs ناقصة: `ProcessRawLogsChunk.php`, `RecalculateDailySummariesChunk.php`, `RecalculateDateRangeChunk.php`, `SyncHolidaysToAttendance.php`, `RecalculateVacationBalances.php`, `ImportAllFingerprintTemplatesJob.php`. **التعقيد:** بسيط.

- [ ] **T036** [P4] Fix `Modules/FingerprintDevices/app/Jobs/SyncUserToDeviceViaBridgeJob.php:18` — تغيير `tries=1` → `tries=3` مع `backoff=10`. **التعقيد:** بسيط.

- [ ] **T037** [P4] Fix `Modules/AttendanceIntegration/app/Jobs/AttendanceIngestionJob.php:28` — تغيير `timeout` من 600 → 80 أو إبقاء 600 مع `retry_after=610`. **التعقيد:** بسيط.

- [ ] **T038** [P4] Fix `routes/console.php` — نقل كل Schedules من `AttendanceServiceProvider.php:90` و `FingerprintDevicesServiceProvider.php:70` إلى `routes/console.php` (Laravel 11 pattern) + إضافة `Schedule::command('queue:prune-failed --hours=72')->daily();` + `cache:prune-stale-tags` + `adms:vacuum` weekly. **التعقيد:** متوسط.

- [ ] **T039** [P4] Add `onOneServer()` + `withoutOverlapping(10)` لكل Schedules المتزاحمة (كل 5 دقائق). **التعقيد:** بسيط. **التبعية:** T038.

- [ ] **T040** [P4] Create `tests/Feature/SystemStabilityTest.php` — 6 tests: `cache_tags_flush`, `queue_retry_after_gt_timeout`, `deleted_at_indexes_exist`, `scopeBetweenDates_uses_whereBetween`, `no_app_in_services`, `attendance_push_channel_exists`. **التعقيد:** متوسط.

- [ ] **T041** [P4] Run `php artisan test` — كل tests (قديمة + جديدة) تمر. **التعقيد:** بسيط. **التبعية:** T040.

- [ ] **T042** [P4] Run `php artisan pint` — تنسيق كل الملفات المعدلة. **التعقيد:** بسيط. **التبعية:** T041.

- [ ] **T043** [P4] Run `php artisan migrate:rollback --step=3 && php artisan migrate` + `verify_counts` — round-trip آمن. **التعقيد:** بسيط. **التبعية:** T016.

- [ ] **T044** [P4] Run `npm run build` + `Lighthouse` — حجم bundles + performance score. **التعقيد:** بسيط. **التبعية:** T028-T034.

---

## Polish & Cross-cutting

- [ ] **T045** [Polish] Update `docs/OPTIMIZATION_ROADMAP.md` — توثيق نهائي لما تم. **التعقيد:** بسيط.

- [ ] **T046** [Polish] Create `scripts/performance/benchmark.php` — script يقيس `EXPLAIN` + `Cache::tags` + `queue` latency قبل/بعد. **التعقيد:** متوسط.

- [ ] **T047** [Polish] Run `git status` — عرض القائمة المتوقعة: 3 migrations + 10 ملفات معدلة + 2 requests + 1 test + 1 script. أي ملف غير متوقع = إيقاف. **التعقيد:** بسيط.

---

## استراتيجية التنفيذ

### MVP أولاً (الحد الأدنى لمنع الفشل الفجائي)

```
P0 (T001-T008) + T009-T012 (indexes حرجة) + T017-T019 (DI) + T035-T037 (Jobs)
= 15 مهمة — يوم واحد — يمنع 80% من الفشل الفجائي
```

### التسليم التدريجي

```
Iteration 1 (يوم 1): P0 + T009-T012                    → Infrastructure + DB حرج
Iteration 2 (يوم 2): T013-T016 + T017-T027             → Database + Architecture
Iteration 3 (يوم 3): T028-T034                         → Frontend
Iteration 4 (يوم 4): T035-T044                         → Scheduler + Validation
Iteration 5 (يوم 5): T045-T047                         → Polish
```

**كل iteration قابلة للنشر مستقلة.**

---

## مخطط التبعيات (Dependency Graph)

```
T001-T008 (P0)
  │
  ├─► T009-T012 (P1 critical) ─► T013-T016 (P1 rest) ─┐
  │                                                    │
  ├─► T028-T034 (P3 frontend) ─────────────────────────┤
  │                                                    ▼
  └─► T017-T027 (P2 architecture) ──────────────────► T035-T044 (P4 validation) ─► T045-T047
```

**الفرص المتوازية:**
- T001-T003 مستقلة (config)
- T009-T011 مستقلة (3 migrations مختلفة)
- T017-T019 مستقلة (3 services مختلفة)
- T028-T030 مستقلة (vite + app.js + chart)
- T031+T032 يمكن تنفيذهما بالتوازي

---

## ملاحظات حرجة

1. **BR-13:** ممنوع أي `DROP` / `TRUNCATE` / `migrate:fresh`
2. **BR-14:** كل migration = `Schema::table()->index()` فقط
3. **BR-15:** بعد كل migration يجب `verify_counts` ينجح
4. **D-7:** كل `index()` محاط بـ `try/catch` يتجاهل `Duplicate key name`
5. **Backward compat:** لا تغيير public signature إلا method جديد
6. **Test 9.4:** `User::with()->paginate()` قبل/بعد يعطي نفس النتيجة

---

*عدد المهام: 47*
*آخر تحديث: 2026-08-27*
*الإصدار: 1.0.0*
