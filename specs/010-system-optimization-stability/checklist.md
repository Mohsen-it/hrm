# 010 — قائمة التحقق (Checklist) — قبل/أثناء/بعد كل مرحلة

**المرجع:** `spec.md § 14` + `plan.md § 6`

---

## قبل البدء (Pre-Flight)

- [ ] `git status` نظيف (لا تغييرات غير مُلتزم بها)
- [ ] `git checkout -b 010-system-optimization-stability` (أو الفرع المتفق عليه)
- [ ] `php artisan test` يمر (baseline)
- [ ] `php artisan pint --test` يمر (baseline)
- [ ] نسخة احتياطية: `cp .env .env.bak.010` + `mysqldump > backup_pre_010.sql` (لو MySQL)
- [ ] `redis-cli ping` → `PONG` (أو `predis` جاهز)
- [ ] `baseline_counts.json` محفوظ (COUNT(*) لكل جدول)

---

## بعد كل مهمة (Per-Task)

- [ ] الكود يتبع `constitution.md` (Controller نحيف, Service يحوي المنطق, DI)
- [ ] لا `app()` / `resolve()` في Services
- [ ] لا `Model::` مباشر في Controllers (إلا type-hint)
- [ ] `php artisan pint` على الملف المعدل — 0 diff
- [ ] `progress.md` مُحدَّث (الحالة + التاريخ + النسبة)

---

## بعد كل مرحلة (Per-Phase)

### بعد P0
- [ ] `php artisan config:clear && php artisan cache:clear` ينجح
- [ ] `Cache::tags(['settings'])->put('k','v'); Cache::tags(['settings'])->flush(); assert missing` ينجح
- [ ] `config('queue.connections.database.retry_after') === 610`
- [ ] `Log::channel('attendance_push')->info('test')` ينشئ الملف

### بعد P1
- [ ] `php artisan migrate` ينجح
- [ ] `COUNT(*)` متطابق قبل/بعد (verify_counts)
- [ ] `EXPLAIN` لا يظهر `ALL` على الاستعلامات الحرجة
- [ ] `php artisan migrate:rollback --step=3 && php artisan migrate` round-trip آمن

### بعد P2
- [ ] لا `app()` في `DevicePushService` / `HolidayIntegrationService`
- [ ] لا N+1 في `RotationsController` (تحقق يدوي + query log)
- [ ] `Holiday::active()->get()` يُستدعى مرة واحدة فقط (cached)
- [ ] `grep -r "app(" Modules/*/app/Services` → 0 نتائج (إلا Exports/Jobs المسموحة)

### بعد P3
- [ ] `npm run build` ينجح + chunks منفصلة ظاهرة
- [ ] `app-*.css` < 100KB (كان 137KB)
- [ ] `fa-*` < 50KB إجمالي (كان 250KB)
- [ ] Lighthouse Performance > 85
- [ ] لا `<table>` خام خارج `DataTable.vue` (عدا الاستثناءات الموثقة)

### بعد P4
- [ ] `php artisan schedule:list` يظهر 15 task
- [ ] كل Job يحوي `tries` + `backoff`
- [ ] `php artisan test` 100% (قديمة + جديدة)
- [ ] `php artisan pint` نظيف

---

## قبل النشر (Pre-Deploy) — نهائي

- [ ] كل 47 مهمة `✅ مكتمل` في `progress.md`
- [ ] `php artisan test` ينجح 100%
- [ ] `php artisan pint` نظيف
- [ ] `php artisan migrate:status` يظهر 3 migrations جديدة
- [ ] `npm run build` ينجح
- [ ] `COUNT(*)` parity 100%
- [ ] `EXPLAIN` يستخدم index (لا ALL)
- [ ] `Cache::tags()->flush()` يعمل
- [ ] لا تغيير UI (manual QA على 10 صفحات: `/users`, `/attendance/sessions`, `/shifts/rotations`, `/shifts/calendar`, `/vacations/requests`, `/holidays`, `/dashboard`, `/zones`, `/branches`, `/departments`)
- [ ] `git status` يظهر فقط الملفات المتوقعة:
  - `database/migrations/2026_08_27_*.php` (3)
  - `config/queue.php`, `config/logging.php`, `config/database.php` (3)
  - `Modules/*/app/Services/*.php` (4)
  - `Modules/*/app/Http/Controllers/*.php` (3)
  - `Modules/*/app/Http/Requests/*.php` (2)
  - `vite.config.js`, `resources/js/app.js`, `resources/js/Components/dashboard/DashboardChart.vue`, `resources/css/app.css` (4)
  - `tests/Feature/SystemStabilityTest.php` (1)
  - `specs/010-system-optimization-stability/*` (4 docs)
  - `.env.example` (1)
- [ ] `progress.md` نسبة 100% + مقاييس "بعد" مملوءة
- [ ] PR description يذكر كل P0-P4 + المخاطر + Rollback plan

---

## بعد النشر (Post-Deploy) — مراقبة 24h

- [ ] `redis-cli ping` → `PONG` في الإنتاج
- [ ] `php artisan queue:work` يعمل عبر Supervisor (`supervisorctl status hrm-queue:*` → RUNNING)
- [ ] `tail -f storage/logs/laravel-*.log` — لا أخطاء جديدة
- [ ] `tail -f storage/logs/attendance-push-*.log` — يكتب بشكل طبيعي
- [ ] `SHOW PROCESSLIST` على MySQL — لا `slow_query` > 1s
- [ ] `storage/logs` إجمالي < 50MB
- [ ] `schedule:run` يعمل كل دقيقة (تحقق من `hrm-scheduler.log`)

---

*آخر تحديث: 2026-08-27*
