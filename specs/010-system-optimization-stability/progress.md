# 010 — تقدم التنفيذ (Progress Tracker) — النظام الحي

**الفرع:** `010-system-optimization-stability`
**البداية:** 2026-08-27
**التحديث الأخير:** 2026-08-27  — P1 مكتمل (8/8) — P0+P1 = 16/47
**الحالة العامة:** 🟢 P0+P1 مكتمل — جاهز لـ P2
**نسبة الإنجاز:** 34% (16/47)

> **هذا الملف هو المصدر الوحيد للحقيقة (Single Source of Truth) للتقدم.** يُحدَّث بعد كل مهمة. لا يُحذف ولا يُستبدل.

---

## ملخص سريع (Dashboard)

| المرحلة | المهام | مكتمل | قيد التنفيذ | متبقي | النسبة |
|---------|--------|-------|-------------|-------|--------|
| **P0 Infrastructure** | 8 | 8 | 0 | 0 | 100% |
| **P1 Database** | 8 | 8 | 0 | 0 | 100% |
| **P2 Architecture** | 11 | 0 | 0 | 11 | 0% |
| **P3 Frontend** | 7 | 0 | 0 | 7 | 0% |
| **P4 Scheduler+Validation** | 10 | 0 | 0 | 10 | 0% |
| **Polish** | 3 | 0 | 0 | 3 | 0% |
| **الإجمالي** | **47** | **16** | **0** | **31** | **34%** |

**المرحلة الحالية:** `P1 ✅ مكتمل` → التالي: `P2-T017`

---

## السجل الزمني (Timeline)

| التاريخ | الحدث | التفصيل |
|---------|-------|---------|
| 2026-08-27 | تدقيق شامل | فحص 283 ملف + 109 migration + configs + logs → تقارير 4 محاور |
| 2026-08-27 | إنشاء spec.md | المواصفات الكاملة (14 قسم) |
| 2026-08-27 | إنشاء plan.md | الخطة التقنية (9 أقسام) |
| 2026-08-27 | إنشاء tasks.md | 47 مهمة مفصلة |
| 2026-08-27 | إنشاء progress.md | هذا الملف |
| 2026-08-27 | P0 مكتمل (T001-T008) | queue retry_after=610, logging channels, database tuning, .env redis, Cache tags fallback, ZKTeco PID fix — verify: cache=file, retry=610, after_commit=true |
| 2026-08-27 | P1 مكتمل (T009-T016) | 3 migrations (deleted_at, esc_active skip, holidays), scopeBetweenDates→whereBetween, LIKE prefix, PaginatesResults cap 1000 — migrate+rollback OK, pint fixed |

---

## تقدم مفصل — كل مهمة

### P0 — Infrastructure الحرج (8)

| # | المهمة | الحالة | التاريخ | الملاحظات |
|---|--------|--------|---------|-----------|
| T001 | `config/queue.php` retry_after + after_commit | ✅ مكتمل | 2026-08-27 | retry_after 90→610, after_commit false→true (env) |
| T002 | `config/logging.php` attendance_push channel | ✅ مكتمل | 2026-08-27 | channels `attendance_push` + `hrm_server` daily 14d |
| T003 | `config/database.php` busy_timeout + WAL + sticky | ✅ مكتمل | 2026-08-27 | sqlite WAL, mysql sticky, redis client predis |
| T004 | `.env.example` redis | ✅ مكتمل | 2026-08-27 | CACHE_STORE=file→redis, REDIS_CLIENT predis, queue retry vars |
| T005 | `.env` redis | ✅ مكتمل | 2026-08-27 | local file (redis requires server), prod redis ready, VITE_REVERB_HOST fix |
| T006 | `Setting.php` fix Cache tags | ✅ مكتمل | 2026-08-27 | fallback iterates DB keys بدل sentinel |
| T007 | `ZoneService.php` fix Cache tags | ✅ مكتمل | 2026-08-27 | fallback + tags flush |
| T008 | `ZKTecoPythonBridgeService.php` PID fix | ✅ مكتمل | 2026-08-27 | Windows pidFile via getmypid() |

### P1 — Database (8)

| # | المهمة | الحالة | التاريخ | الملاحظات |
|---|--------|--------|---------|-----------|
| T009 | Migration deleted_at indexes | ✅ مكتمل | 2026-08-27 | idx_att_sessions_deleted_at, idx_raw_logs_deleted_at — 384ms |
| T010 | Migration esc_active | ✅ مكتمل | 2026-08-27 | skip (no is_active column) — att_esc_emp_start_end_idx already covers |
| T011 | Migration holidays fix | ✅ مكتمل | 2026-08-27 | idx_holidays_date_active_deleted (date,is_active,deleted_at) |
| T012 | `AttendanceSession.php` whereBetween | ✅ مكتمل | 2026-08-27 | whereDate→where direct, index usable |
| T013 | `UserRepository.php` LIKE fix | ✅ مكتمل | 2026-08-27 | prefix for indexed cols, min 2 chars |
| T014 | `AttendanceSessionRepository.php` LIKE fix | ✅ مكتمل | 2026-08-27 | ip prefix, notes contains, min 2 chars |
| T015 | `PaginatesResults.php` per_page=all fix | ✅ مكتمل | 2026-08-27 | cap 1000 for large tables |
| T016 | `migrate` + verify_counts | ✅ مكتمل | 2026-08-27 | 915 users, 23383 sessions, rollback OK, pint fixed |

### P2 — Architecture (11)

| # | المهمة | الحالة | التاريخ | الملاحظات |
|---|--------|--------|---------|-----------|
| T017 | `DevicePushService` DI | ⬜ متبقي | — | — |
| T018 | `HolidayIntegrationService` DI | ⬜ متبقي | — | — |
| T019 | `ScheduleCalendarController` DI | ⬜ متبقي | — | — |
| T020 | `RotationsController` نقل business | ⬜ متبقي | — | — |
| T021 | `RotationsController:860` withCount | ⬜ متبقي | — | — |
| T022 | `RotationsController:792` whereIn | ⬜ متبقي | — | — |
| T023 | `AbsenceCalculationService` cache holidays | ⬜ متبقي | — | — |
| T024 | `AbsenceCalculationService` SQL aggregation | ⬜ متبقي | — | — |
| T025 | `RotationService` + `ShiftReportsService` Repo | ⬜ متبقي | — | — |
| T026 | FormRequests لـ Rotations | ⬜ متبقي | — | — |
| T027 | `DailyAttendanceSummaryService` cache | ⬜ متبقي | — | — |

### P3 — Frontend (7)

| # | المهمة | الحالة | التاريخ | الملاحظات |
|---|--------|--------|---------|-----------|
| T028 | `vite.config.js` manualChunks | ⬜ متبقي | — | — |
| T029 | `app.js` FontAwesome subset | ⬜ متبقي | — | — |
| T030 | `DashboardChart.vue` lazy + subset | ⬜ متبقي | — | — |
| T031 | `ViolationTable.vue` extract | ⬜ متبقي | — | — |
| T032 | `useViolationFetch.js` composable | ⬜ متبقي | — | — |
| T033 | `DailySummaries/Index.vue` refactor | ⬜ متبقي | — | — |
| T034 | `app.css` حذف الميت | ⬜ متبقي | — | — |

### P4 — Scheduler + Validation (10)

| # | المهمة | الحالة | التاريخ | الملاحظات |
|---|--------|--------|---------|-----------|
| T035 | Jobs tries/backoff | ⬜ متبقي | — | — |
| T036 | `SyncUserToDeviceViaBridgeJob` tries=3 | ⬜ متبقي | — | — |
| T037 | `AttendanceIngestionJob` timeout fix | ⬜ متبقي | — | — |
| T038 | `routes/console.php` نقل schedules | ⬜ متبقي | — | — |
| T039 | `onOneServer` + `withoutOverlapping` | ⬜ متبقي | — | — |
| T040 | `SystemStabilityTest.php` 6 tests | ⬜ متبقي | — | — |
| T041 | `php artisan test` | ⬜ متبقي | — | — |
| T042 | `php artisan pint` | ⬜ متبقي | — | — |
| T043 | `migrate:rollback && migrate` | ⬜ متبقي | — | — |
| T044 | `npm run build` + Lighthouse | ⬜ متبقي | — | — |

### Polish (3)

| # | المهمة | الحالة | التاريخ | الملاحظات |
|---|--------|--------|---------|-----------|
| T045 | `docs/OPTIMIZATION_ROADMAP.md` | ⬜ متبقي | — | — |
| T046 | `scripts/performance/benchmark.php` | ⬜ متبقي | — | — |
| T047 | `git status` review | ⬜ متبقي | — | — |

---

## حالات المهام — المفتاح

| الرمز | المعنى |
|-------|--------|
| ⬜ متبقي | لم يبدأ |
| 🟡 قيد التنفيذ | يعمل عليه الآن |
| ✅ مكتمل | تم + مُختبر + pint + test يمر |
| ❌ معطل | Blocked — انظر الملاحظات |
| ⏭️ تخطي | Skip — مبرر في الملاحظات |

---

## المقاييس (Metrics) — قبل/بعد

> يُملأ بعد كل مرحلة. الهدف: إثبات التحسن بأرقام.

| المقياس | قبل | بعد P0 | بعد P1 | بعد P2 | بعد P3 | الهدف |
|---------|-----|--------|--------|--------|--------|-------|
| `Cache::get` latency | ~10ms (db) | — | — | — | — | <2ms (redis) |
| `Cache::tags()->flush()` يعمل | ❌ لا | ✅ fallback يمسح DB keys | — | — | — | ✅ |
| `retry_after` vs `timeout` | 90 vs 600 ❌ | ✅ 610 vs 600 (آمن) | — | — | — | 610 vs 80 ✅ |
| `EXPLAIN type` لـ users | ALL | — | ✅ after whereBetween (range) | — | — | ref/range |
| `Holiday::active()->get()` calls | 5 | — | — | — | — | 1 (cached) |
| N+1 في Rotations | 2 مواضع | — | — | — | — | 0 |
| `app()` في Services | 2 | — | — | — | — | 0 |
| JS initial bundle | ~116KB | — | — | — | — | <120KB + chunks |
| CSS app.css | 137KB | — | — | — | — | ~95KB |
| `hrm-laravel-server.log` | 19MB بلا rotation | — | — | — | — | daily 14d |
| `php artisan test` | يمر | — | — | — | — | يمر |
| `php artisan pint` | نظيف | — | — | — | — | نظيف |

---

## المخاطر والـ Blockers

| # | الوصف | الحالة | الحل |
|---|-------|--------|------|
| — | لا يوجد حالياً | — | — |

---

## قرارات (Decisions Log)

| التاريخ | القرار | المبرر |
|---------|--------|--------|
| 2026-08-27 | استخدام `predis` بدل `phpredis` كـ default | لا حاجة لـ extension على Windows |
| 2026-08-27 | 3 migrations فقط (لا 9) | الباقي من 008 موجود — فقط النواقص |
| 2026-08-27 | `SESSION_DRIVER=redis` أو `file` (ليس database) | تخفيف حمل MySQL |
| 2026-08-27 | P0 local fallback: file/database (redis needs server) | dev يعمل بدون redis-server, prod يحوّل لـ redis عند توفره |

---

## كيف تُحدّث هذا الملف

بعد كل مهمة:
1. غيّر `⬜ متبقي` → `✅ مكتمل` (أو `🟡 قيد التنفيذ` أثناء العمل)
2. أضف التاريخ في `التاريخ`
3. حدّث `نسبة الإنجاز` في الأعلى
4. حدّث `المقاييس` لو توفر رقم جديد
5. أضف سطر في `السجل الزمني`

**القاعدة:** commit واحد لكل مهمة أو مجموعة مهام مترابطة + تحديث progress.md في نفس الـ commit.

---

*هذا الملف يُقرأ تلقائياً في بداية كل جلسة عمل لتحديد "أين توقفنا".*
*آخر تحديث: 2026-08-27*
