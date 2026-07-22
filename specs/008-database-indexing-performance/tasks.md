# 008 — Database Indexing & Query Performance — تقسيم المهام (Tasks)

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-21
**الحالة:** جاهز للتنفيذ
**المواصفة:** [spec.md](./spec.md)
**الخطة:** [plan.md](./plan.md)
**البحث:** [research.md](./research.md)
**نموذج البيانات:** [data-model.md](./data-model.md)
**العقود:** [contracts/](./contracts/)
**الاختبار:** [quickstart.md](./quickstart.md)
**الفرع:** `008-database-indexing-performance`

> **⚠️ ضمانات حرجة (مطبَّقة على كل مهمة):**
> - لا `DROP COLUMN`، لا `TRUNCATE`، لا `DELETE`، لا `migrate:fresh`/`refresh` (BR-13 في spec).
> - كل migration يستخدم `Schema::table()->index()` فقط (BR-14).
> - كل `index()` محاط بـ `try/catch (QueryException)` (D-7 في research).
> - كل `down()` يحذف نفس ما `up()` أضافه.
> - لا تغيير في أي Repository/Service/Controller public signature (backward compat).

---

## ملخص المهام حسب القصة

| القصة | العنوان | الأولوية | عدد المهام | معيار الاختبار المستقل |
|------|---------|---------|-----------|-------------------------|
| Phase 1 | Setup (snapshot + helpers) | — | 4 | `baseline_counts.json` موجود + `safeIndex` helper مُتاح |
| Phase 2 | Foundational (DB indexes على `users` + `attendance`) | — | 4 | `EXPLAIN` يستخدم `idx_users_company_status_active` و `idx_att_sessions_user_date_status` |
| **US1** | Apply Vacation + Subordinations + Holidays indexes | **P1** | 3 | `EXPLAIN` يستخدم `idx_vacation_req_status_start` و `idx_subordinations_status_order` و `idx_holidays_date_active` |
| **US2** | Apply Fingerprint Devices + Audit Logs + Sync Logs indexes | **P1** | 3 | `EXPLAIN` يستخدم `idx_devices_company_branch` و `idx_audit_correlation` |
| **US3** | Apply Shifts + Rotations + Hours Tracking indexes | **P1** | 2 | `EXPLAIN` يستخدم `idx_schedule_entries_date_emp` و `idx_rotation_assign_emp_dates` |
| **US4** | UserRepository query optimization | **P1** | 2 | `getAll` لا يستخدم `latest()` + `getActiveByCompany` موجود |
| **US5** | Feature tests (data preservation + behavior unchanged + indexes exist) | **P1** | 5 | `php artisan test` ينجح 100% (القديمة + الجديدة) |
| **US6** | Final validation pipeline (pint + roundtrip + full quickstart) | **P1** | 5 | كل التحققات الـ 8 من `quickstart.md` تمر |
| Phase 9 | Polish & Cross-cutting | — | 3 | `pint` نظيف + `git status` يعرض فقط الملفات المتوقعة |
| **المجموع** | | | **31 مهمة** | |

---

## ترتيب التنفيذ (Story Completion Order)

```
Phase 1 (Setup)
   │
   ▼
Phase 2 (Foundational: users + attendance indexes)  ◄── الأهم (أكثر جداول استخداماً)
   │
   ├─► US1 (vacation + sub + holidays)  ─┐
   ├─► US2 (devices + audit + sync)     ─┤── متوازية (ملفات مختلفة، لا تبعيات بينية)
   ├─► US3 (shifts + rotations + hours) ─┘
   │
   ▼
US4 (UserRepository: code change)
   │
   ▼
US5 (Feature tests)
   │
   ▼
US6 (Final validation pipeline)
   │
   ▼
Phase 9 (Polish)
```

- **Phase 1 لا يعتمد على شيء** (مستقل).
- **Phase 2 يعتمد على:** Phase 1 (يحتاج snapshot).
- **US1/US2/US3 يعتمدون على:** Phase 2 (يبنون على الفهارس الموجودة كـ try/catch يتجاهل).
- **US4 يعتمد على:** Phase 2 (Repository يستخدم الفهارس الجديدة).
- **US5 يعتمد على:** US1 + US2 + US3 + US4 (يختبر كل شيء).
- **US6 يعتمد على:** US5 (يختبر الـ test pipeline + quickstart).
- **MVP = Phase 1 + Phase 2 + US4 + US5 (الحد الأدنى لإثبات عدم فقدان البيانات + لا تغيير سلوك).**

---

## Phase 1: Setup (تهيئة المشروع)

> لا تصنيف قصة. هذه مهام بنية تحتية (snapshot + helpers قبل أي migration).

- [X] T001 [P] Create baseline-counts snapshot script at `scripts/performance/snapshot_counts.php` (PHP script يُشغَّل عبر `php artisan tinker --execute` أو مباشرة) يحفظ `baseline_counts.json` يحوي `COUNT(*)` لكل جدول متأثر (users, attendance_sessions, daily_attendance_summaries, raw_attendance_logs, schedule_entries, user_vacation_requests, fingerprint_devices, audit_logs). المسار: `D:\hrm_alepair\scripts\performance\snapshot_counts.php`.
- [X] T002 [P] Create baseline-snapshot restore script at `scripts/performance/verify_counts.php` يقارن `baseline_counts.json` بالـ counts الحالية ويُرجع exit code 0 لو متطابقة، 1 لو مختلفة. المسار: `D:\hrm_alepair\scripts\performance\verify_counts.php`.
- [X] T003 Run `php scripts/performance/snapshot_counts.php` من جذر المشروع وحفظ `baseline_counts.json` على الجذر (لا commit). المسار: `D:\hrm_alepair\baseline_counts.json`.
- [X] T004 Add `.gitignore` entries: `baseline_counts.json` و `scripts/performance/` (لكن **لا** نضيف .gitignore جديد — نضيف السطرين إلى `D:\hrm_alepair\.gitignore` الموجود).

---

## Phase 2: Foundational (الأساس — users + attendance indexes)

> لا تصنيف قصة. هذه مهام حجب (تخدم كل القصص اللاحقة).
> تنشئ الفهارس على أعلى جدولين استخداماً: `users` (يُستدعى في كل صفحة) و `attendance_*` (الأكبر حجماً).

- [X] T005 Create migration `database/migrations/2026_07_21_000001_add_users_composite_indexes.php` يحوي `protected function safeIndex(Blueprint $table, array $cols, string $name)` و `safeDropIndex(Blueprint $table, string $name)` helpers (try/catch على QueryException يتجاهل `Duplicate key name`/`1061`/`already exists`)، ثم 7 indexes على `users` بالترتيب: `idx_users_company_status_active`, `idx_users_branch_status`, `idx_users_department_status`, `idx_users_position_status`, `idx_users_grade_status`, `idx_users_employment_type`, `idx_users_hire_date` (مرجع: `contracts/ddl-contracts.md § M-1` و `data-model.md § 1.1`). الـ `down()` يحذف نفس الـ 7.
- [X] T006 Create migration `database/migrations/2026_07_21_000002_add_attendance_query_indexes.php` يضيف 4 indexes على `attendance_sessions` (`idx_att_sessions_user_date_status`, `idx_att_sessions_date_status_type`, `idx_att_sessions_created_by`, `idx_att_sessions_checkout`)، 2 على `daily_attendance_summaries` (`idx_daily_summaries_date_calculated`, `idx_daily_summaries_status_date`)، 3 على `raw_attendance_logs` (`idx_raw_logs_dedup`, `idx_raw_logs_user_time`, `idx_raw_logs_processed_punch`)، و 1 على `iclock_transaction` محصور بـ `Schema::hasTable()` (مرجع: `contracts/ddl-contracts.md § M-2` و `data-model.md § 1.2-1.5`). الـ `down()` يحذف نفس الـ 10.
- [X] T007 Run `php artisan migrate` من جذر المشروع، ثم run `php scripts/performance/verify_counts.php` (المتوقع: `🎉 ALL COUNTS MATCH`). المسار: `D:\hrm_alepair\baseline_counts.json` (مقارنة).
- [X] T008 Run `EXPLAIN SELECT * FROM users WHERE company_id = 1 AND status = 1 AND is_active_employee = 1 LIMIT 20` عبر tinker — المتوقع: يستخدم `idx_users_company_status_active` (key=`idx_users_company_status_active` على MySQL، أو query plan يحتوي Index Scan على SQLite).

---

## Phase 3: User Story 1 (P1) — Vacation + Subordinations + Holidays indexes

> **القصة:** كمدير HR، أستعلم الإجازات المعلقة وأعياد الشركة بسرعة، والقائمة المنسدلة للتبعية تحمّل فوراً.
> **معيار الاختبار المستقل:** `EXPLAIN` على `user_vacation_requests WHERE status = 'pending' ORDER BY start_date` يستخدم `idx_vacation_req_status_start`. `EXPLAIN` على `subordinations WHERE status = 1 ORDER BY sort_order` يستخدم `idx_subordinations_status_order`.

- [X] T009 [P] [US1] Create migration `Modules/Users/database/migrations/2026_07_21_000001_add_vacation_query_indexes.php` يضيف 3 indexes على `user_vacation_requests` (`idx_vacation_req_status_start`, `idx_vacation_req_user_dates`, `idx_vacation_req_decided`) و 1 على `user_vacation_balance_transactions` (`idx_vacation_bal_tx_date`) — كله عبر `safeIndex()` و try/catch (مرجع: `data-model.md § 1.7-1.8` و `contracts/ddl-contracts.md § M-3`). الـ `down()` يحذف نفس الـ 4.
- [X] T010 [P] [US1] Create migration `Modules/Holidays/database/migrations/2026_07_21_000001_add_holiday_query_index.php` يضيف 1 index على `holidays` (`idx_holidays_date_active` على `(date, is_active)`) عبر `safeIndex()` (مرجع: `data-model.md § 1.13` و `contracts/ddl-contracts.md § M-7`). الـ `down()` يحذف نفس الـ 1.
- [X] T011 [P] [US1] Create migration `Modules/Subordinations/database/migrations/2026_07_21_000001_add_subordination_query_index.php` يضيف 1 index على `subordinations` (`idx_subordinations_status_order` على `(status, sort_order)`) عبر `safeIndex()` (مرجع: `data-model.md § 1.14` و `contracts/ddl-contracts.md § M-8`). الـ `down()` يحذف نفس الـ 1.
- [X] T012 [US1] Run `php artisan migrate`، ثم `php scripts/performance/verify_counts.php` (المتوقع: لا تغيير في الـ counts). ثم `EXPLAIN SELECT * FROM user_vacation_requests WHERE status = 'pending' AND start_date BETWEEN '2026-01-01' AND '2026-12-31'` يجب أن يستخدم `idx_vacation_req_status_start`.

---

## Phase 4: User Story 2 (P1) — Fingerprint Devices + Audit Logs + Sync Logs indexes

> **القصة:** كمدير نظام، أستعلم "أي جهاز لم تتم مزامنته منذ ساعة؟" بسرعة. وكمراقب، أستعلم audit log حسب correlation_id أو actor_id بسرعة.
> **معيار الاختبار المستقل:** `EXPLAIN` على `fingerprint_devices WHERE company_id = 1 AND branch_id = 2 AND status = 'online'` يستخدم `idx_devices_company_branch`. `EXPLAIN` على `attendance_integration_audit_logs WHERE correlation_id = '...'` يستخدم `idx_audit_correlation`.

- [X] T013 [P] [US2] Create migration `Modules/FingerprintDevices/database/migrations/2026_07_21_000002_add_device_query_indexes.php` يضيف 2 indexes على `fingerprint_devices` (`idx_devices_company_branch` على `(company_id, branch_id, status)`، `idx_devices_last_pushed` على `(last_pushed_at, status)`) و 2 على `device_sync_logs` (`idx_sync_logs_device_date`، `idx_sync_logs_status_date`) عبر `safeIndex()` (مرجع: `data-model.md § 1.9-1.10` و `contracts/ddl-contracts.md § M-4`). الـ `down()` يحذف نفس الـ 4.
- [X] T014 [P] [US2] Create migration `Modules/AttendanceIntegration/database/migrations/2026_07_21_000005_add_audit_logs_indexes.php` يضيف 2 indexes على `attendance_integration_audit_logs` (`idx_audit_correlation` على `(correlation_id, occurred_at)`، `idx_audit_actor` على `(actor_id, occurred_at)`) عبر `safeIndex()` (مرجع: `data-model.md § 1.11` و `contracts/ddl-contracts.md § M-5`). الـ `down()` يحذف نفس الـ 2.
- [X] T015 [P] [US2] Create migration `database/migrations/2026_07_21_000003_add_general_audit_indexes.php` يضيف 1 index على `audit_logs` (في Shifts) باسم `idx_audit_action_date` على `(action, created_at)` عبر `safeIndex()` (مرجع: `data-model.md § 1.12` و `contracts/ddl-contracts.md § M-9`). الـ `down()` يحذف نفس الـ 1.
- [X] T016 [US2] Run `php artisan migrate`، ثم `php scripts/performance/verify_counts.php` (المتوقع: لا تغيير). ثم `EXPLAIN SELECT * FROM fingerprint_devices WHERE status = 'online' AND last_pushed_at < NOW() - INTERVAL 1 HOUR` يجب أن يستخدم `idx_devices_last_pushed`.

---

## Phase 5: User Story 3 (P1) — Shifts + Rotations + Hours Tracking indexes

> **القصة:** كمدير عمليات، أستعلم "كم موظف مناوب يوم الإثنين الأسبوع القادم؟" والتقويم الشهري يحمّل بسرعة. وكمدير HR، أستعلم ساعات العمل الشهرية لكل موظف بسرعة.
> **معيار الاختبار المستقل:** `EXPLAIN` على `schedule_entries WHERE schedule_period_id = X AND date BETWEEN '2026-07-01' AND '2026-07-31'` يستخدم `idx_schedule_entries_date_emp`. `EXPLAIN` على `att_rotation_assignments WHERE employee_id = X` يستخدم `idx_rotation_assign_emp_dates`.

- [X] T017 [P] [US3] Create migration `Modules/Shifts/database/migrations/2026_07_21_000001_add_schedule_query_indexes.php` يضيف 5 indexes: 2 على `schedule_entries` (`idx_schedule_entries_date_emp`، `idx_schedule_entries_period_status`)، 2 على `att_hours_tracking` (`idx_hours_user_date`، `idx_hours_employee_category`)، 1 على `att_rotation_assignments` (`idx_rotation_assign_emp_dates`)، 1 على `att_employee_shift_categories` (`idx_esc_active`) — كله عبر `safeIndex()` (مرجع: `data-model.md § 1.6, 1.15-1.17` و `contracts/ddl-contracts.md § M-6`). الـ `down()` يحذف نفس الـ 6.
- [X] T018 [US3] Run `php artisan migrate`، ثم `php scripts/performance/verify_counts.php` (المتوقع: لا تغيير). ثم `EXPLAIN SELECT * FROM schedule_entries WHERE date BETWEEN '2026-07-01' AND '2026-07-31' AND day_status = 'WORK'` يجب أن يستخدم `idx_schedule_entries_date_emp`.

---

## Phase 6: User Story 4 (P1) — UserRepository query optimization

> **القصة:** كمدير نظام، أرى أن `/users` (قائمة الموظفين) تحمّل أسرع لأن الترتيب يستخدم PK index بدلاً من `created_at` filesort.
> **معيار الاختبار المستقل:** `php artisan tinker --execute='User::first()->id'` يبقى 1، و `getAll()` يُرجع نفس النتيجة بالضبط (نفس IDs، نفس الترتيب).

- [X] T019 [US4] Modify `Modules/Users/app/Repositories/UserRepository.php`: استبدال `->latest()` بـ `->orderBy('users.id', 'desc')` في method `getAll()` (السطر ~53). مرجع: `contracts/query-audit.md § 2.1` و `research.md § D-16`.
- [X] T020 [P] [US4] Add new method `getActiveByCompany(int $companyId): Collection` إلى `Modules/Users/app/Repositories/UserRepository.php` (بعد method `getActive()` الموجود ~السطر 184). يحوي: `select([8 columns])`، `where('company_id', $companyId)`، `where('status', 1)`، `where('is_active_employee', true)`، `orderBy('first_name')`، `get()`. مرجع: `contracts/query-audit.md § 2.2` و `data-model.md § 1.1` (يستخدم `idx_users_company_status_active`).

---

## Phase 7: User Story 5 (P1) — Feature tests (5 اختبارات)

> **القصة:** كمهندس QA، أؤكد أن الـ feature يحقق وعوده: لا فقدان بيانات، rollback آمن، EXPLAIN يستخدم index، output متطابق، الفهارس موجودة فعلياً.
> **معيار الاختبار المستقل:** `php artisan test` ينجح 100% — 5 اختبارات جديدة + كل الاختبارات الموجودة.

- [X] T021 [P] [US5] Create test file `tests/Feature/IndexingTest.php` مع `use RefreshDatabase;` و 4 use statements (`Tests\TestCase`, `Modules\Users\Models\User`, `Modules\Attendance\Models\AttendanceSession`, `Illuminate\Support\Facades\Artisan` و `DB`). يحتوي test واحد فقط: `test_indexes_are_created` (يستخدم `DB::select("SHOW INDEX FROM users")` على MySQL، أو `sqlite_master` على SQLite، ويتأكد من وجود `idx_users_company_status_active`).
- [X] T022 [P] [US5] في نفس `tests/Feature/IndexingTest.php`، أضف `test_adds_indexes_without_losing_data`: snapshot `User::count()` و `AttendanceSession::count()` و `RawAttendanceLog::count()` و `ScheduleEntry::count()`، ثم `Artisan::call('migrate', ['--force' => true])`، ثم `expect()->toBe()` لكل واحد. مرجع: `spec.md § 9.1`.
- [X] T023 [P] [US5] في نفس `tests/Feature/IndexingTest.php`، أضف `test_rollback_does_not_delete_data`: snapshot `User::count()`، ثم `Artisan::call('migrate:rollback', ['--step' => 9, '--force' => true])`، ثم `expect(User::count())->toBe($before)`، ثم `Artisan::call('migrate', ['--force' => true])` لإعادة. مرجع: `spec.md § 9.3`.
- [X] T024 [P] [US5] في نفس `tests/Feature/IndexingTest.php`، أضف `test_repository_output_unchanged_after_optimization`: snapshot `User::with($defaultWith)->where('company_id', 1)->paginate(20)->toArray()`، ثم `Artisan::call('migrate', ['--force' => true])`، ثم نفس الـ query، ثم `expect($after)->toEqual($before)`. مرجع: `spec.md § 9.4` و `contracts/query-audit.md § 5`.
- [X] T025 [P] [US5] في نفس `tests/Feature/IndexingTest.php`، أضف `test_user_query_uses_index` (MySQL-only): `DB::select('EXPLAIN SELECT * FROM users WHERE company_id = 1 AND status = 1 AND is_active_employee = 1 LIMIT 20')`، ثم `expect($explain[0]->type)->not->toBe('ALL')` و `expect($explain[0]->key)->toBe('idx_users_company_status_active')`. للـ SQLite: `$this->markTestSkipped('MySQL-specific')`. مرجع: `spec.md § 9.2`.

---

## Phase 8: User Story 6 (P1) — Final validation pipeline

> **القصة:** كمدير تسليم، أتأكد أن الـ feature جاهز للنشر: pint يمر، الـ round-trip rollback+re-migrate آمن، كل الاختبارات تنجح، quickstart كامل ينجح.
> **معيار الاختبار المستقل:** كل من T026-T030 يمر بدون خطأ.

- [X] T026 [US6] Run `php artisan pint` على كل الملفات المُعدَّلة (المرجع: `quickstart.md § 13`). المتوقع: لا تغييرات (الكود نظيف أصلاً، أو pint يُصلحها تلقائياً). لو ظهر diff، أوقف وأبلغ.
- [X] T027 [US6] Run `php artisan migrate:rollback --step=9 --force` ثم `php artisan migrate` ثم `php scripts/performance/verify_counts.php`. المتوقع: `🎉 ALL COUNTS MATCH` بعد كل من rollback و re-migrate. مرجع: `quickstart.md § 6`.
- [X] T028 [US6] Run `php artisan test` (كل الاختبارات). المتوقع: كل الاختبارات القديمة + الـ 5 الجديدة تنجح. لو فشل أي اختبار، أوقف وأبلغ. مرجع: `quickstart.md § 7`.
- [X] T029 [US6] Run `php artisan tinker --execute="echo count(\Modules\Users\Models\User::all())" > before.txt`، ثم `php artisan migrate --force`، ثم نفس الـ command إلى `after.txt`، ثم `fc before.txt after.txt` (Windows) أو `diff` (Linux). المتوقع: لا فرق (متطابقان). مرجع: `quickstart.md § 3` (نسخة مبسطة).
- [X] T030 [US6] Run `php scripts/performance/snapshot_counts.php` ثم `php scripts/performance/verify_counts.php` للحالة النهائية. المتوقع: `🎉 ALL COUNTS MATCH`. هذا الـ final check قبل النشر. مرجع: `quickstart.md § 11`.

---

## Phase 9: Polish & Cross-Cutting Concerns

> لا تصنيف قصة. هذه مهام إنهاء.

- [X] T031 [P] Run `git status` وعرض القائمة للمطوّر للمراجعة (تأكد أن الملفات المُعدَّلة هي فقط: 9 migrations جديدة + `UserRepository.php` + `tests/Feature/IndexingTest.php` + `scripts/performance/*.php` + `.gitignore` سطرين). أي ملف غير متوقع = أوقف وأبلغ.

---

## استراتيجية التنفيذ (Implementation Strategy)

### MVP أولاً (MVP First)

**الحد الأدنى القابل للنشر (MVP) = Phase 1 + Phase 2 + US4 + US5:**
- 2 migrations (users + attendance)
- 1 تعديل سطر في UserRepository
- 1 method جديدة
- 5 feature tests

**هذا يحقق:**
- ✅ 7 indexes جديدة (الأكثر تأثيراً)
- ✅ لا فقدان بيانات (مُختبر)
- ✅ Rollback آمن (مُختبر)
- ✅ Output متطابق (مُختبر)
- ❌ لا يغطي: vacation, devices, shifts indexes (لكن هذه additive، لا تضر لو أُضيفت لاحقاً)

### التسليم التدريجي (Incremental Delivery)

```
Iteration 1 (MVP):   Phase 1 + Phase 2 + US4 + US5  → 2 migrations + 1 code change + 5 tests
Iteration 2:          + US1                              → 3 migrations
Iteration 3:          + US2                              → 3 migrations
Iteration 4:          + US3                              → 1 migration
Iteration 5:          + US6 + Phase 9                    → validation + polish
```

**كل iteration ينشر للإنتاج (لا حاجة للانتظار).** كل واحد يضيف indexes أكثر.

---

## مخطط التبعيات (Dependency Graph)

```
T001 ─┐
T002 ─┤
T003 ─┤
T004 ─┘
       │
       ▼
T005 ─┐
T006 ─┤
T007 ─┤  (verify counts after Phase 2)
T008 ─┘
       │
       ├─► T009 ─┐
       ├─► T010 ─┤
       ├─► T011 ─┤
       ├─► T012 ─┘ (verify counts after US1)
       │
       ├─► T013 ─┐
       ├─► T014 ─┤
       ├─► T015 ─┤
       ├─► T016 ─┘ (verify counts after US2)
       │
       ├─► T017 ─┐
       ├─► T018 ─┘ (verify counts after US3)
       │
       ├─► T019 ─┐
       ├─► T020 ─┘
       │
       ├─► T021 ─┐
       ├─► T022 ─┤
       ├─► T023 ─┤  (5 tests in same file)
       ├─► T024 ─┤
       ├─► T025 ─┘
       │
       ├─► T026 ─┐
       ├─► T027 ─┤
       ├─► T028 ─┤  (validation pipeline)
       ├─► T029 ─┤
       ├─► T030 ─┘
       │
       ▼
T031 (git status review)
```

---

## الفرص المتوازية (Parallel Opportunities)

### داخل Phase 2 (T005, T006)
- T005 (`users` migration) و T006 (`attendance` migration) **مستقلان** — يمكن كتابتهما بالتوازي.

### داخل US1 (T009, T010, T011)
- T009 (vacation) و T010 (holidays) و T011 (subordinations) **مستقلون** — يمكن كتابتهم بالتوازي.
- T012 يعتمد على الثلاثة.

### داخل US2 (T013, T014, T015)
- T013 (devices) و T014 (audit) و T015 (general audit) **مستقلون** — يمكن كتابتهم بالتوازي.
- T016 يعتمد على الثلاثة.

### داخل US5 (T021-T025)
- كل الاختبارات الخمسة **مستقلون** (يكتبون في نفس الملف لكن لا تبعيات بينها).

---

## ملاحظات حرجة (Critical Reminders)

> **هذه القواعد لا تُكسر في أي مهمة:**

1. **BR-13:** ممنوع `DROP COLUMN` / `TRUNCATE` / `DELETE` / `migrate:fresh` / `migrate:refresh` / `Model::truncate()`.
2. **BR-14:** كل migration = `Schema::table()->index()` فقط.
3. **BR-15:** بعد كل migration، يجب `verify_counts.php` ينجح قبل المتابعة.
4. **D-7:** كل `index()` محاط بـ `try/catch` يتجاهل `Duplicate key name` فقط.
5. **D-3:** اسم index = `idx_{table}_{col1}_{col2}_...` (مثال: `idx_users_company_status_active`).
6. **Backward compat:** لا تغيير في Repository public signature (ما عدا method جديد).
7. **Test 9.4:** `User::with()->where()->paginate()` قبل وبعد migration يعطي نفس النتيجة.

---

*عدد المهام: 31*
*آخر تحديث: 2026-07-21*
*الإصدار: 1.0.0*
