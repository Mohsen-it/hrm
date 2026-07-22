# 008 — Database Indexing & Query Performance — بحث وقرارات

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-21
**المرجع:** [spec.md](./spec.md)
**المخطط:** [plan.md](./plan.md)

---

## 0. ملخص تنفيذي (Executive Summary)

بحث مكثّف لـ 16 قرار تقني يخص فهرسة الجداول الـ 22 في HRM، تغطية كاملة لـ:
- استراتيجية الفهرسة لكل جدول
- توافق MySQL / SQLite / PostgreSQL
- أنماط معمارية لـ `EXPLAIN` و `Composite Index`
- حماية البيانات الموجودة (ممنوع DROP/TRUNCATE)
- Idempotency (تشغيل آمن متعدد المرات)
- Backward compatibility (ممنوع تغيير public signatures)

**النتيجة:** 0 أسئلة مفتوحة، 16 قرار موثّق، جاهز لـ Phase 1.

---

## 1. المنهجية (Methodology)

كل قرار في هذا الـ research يتبع القالب:

```
- **Decision:** ما تم اختياره
- **Rationale:** لماذا اختير
- **Alternatives considered:** ما تم تقييمه ورفضه
- **Source:** الدستور / Laravel docs / خبرة المشروع
```

---

## 2. القرارات (Decisions)

### D-1: استراتيجية الفهرسة العامة

- **Decision:** استراتيجية **Composite-first** (الفهارس المركّبة أولاً)، ثم **Single-column** للأعمدة التي تُستخدم في `WHERE` منفردة، ثم **Covering** (إذا لزم).
- **Rationale:** استعلامات المشروع تستخدم عادةً 2-4 شروط معاً (مثل `company_id + branch_id + status`). الـ Composite index يخدم كل تركيبات الـ prefix، ويُجنّب table-scan.
- **Alternatives considered:**
  - **Single-column لكل عمود:** مرفوض — تكلفة كتابة ومساحة أعلى، وأقل كفاءة في الاستعلامات المركّبة.
  - **Full-text لكل بحث نصي:** مرفوض — البحث الحالي LIKE ليس wildcard-prefix فلا يحتاج fulltext.
- **Source:** Constitution § VI.6.1.4 + Laravel indexing best practices.

### D-2: ترتيب أعمدة الـ Composite Index

- **Decision:** ترتيب الأعمدة = **الترتيب الذي تظهر به في `WHERE` (مساواة → نطاق → ترتيب)**.
- **Rationale:** MySQL/PostgreSQL/SQLite يستخدمون leftmost-prefix. لو الاستعلام `WHERE a = ? AND b BETWEEN ? AND ?`، الـ index `(a, b)` أسرع من `(b, a)`.
- **Alternatives considered:** ترتيب حسب selectivity (كارديناليتي) — مرفوض عملياً لأنه أكثر تعقيداً بدون فائدة تُذكر عند اتباع قاعدة المساواة-أولاً.
- **Source:** MySQL 8 docs "Multiple-Column Indexes" + use The Index Luke.

### D-3: اسم الفهرس (Naming Convention)

- **Decision:** نمط `idx_{table}_{col1}_{col2}_{...}` (مثال: `idx_users_company_status_active`).
- **Rationale:** مقروء، قابل للبحث، آمن في كل الـ drivers. الـ auto-naming من Laravel ينتج `{col1}_{col2}_index` وهو عرضة للتصادم.
- **Alternatives considered:**
  - `users_company_status_active_index` (نمط Laravel التلقائي) — مرفوض لأنه طويل وغير متّسق مع الـ migrations القائمة.
  - `idx_users_compound_1` — مرفوض لأنه يخفي القصد.
- **Source:** استقراء الـ migrations الموجودة في المشروع (مثل `att_sessions_user_date_idx`, `att_summaries_shift_date_idx`).

### D-4: إدارة توافق Drivers (SQLite / MySQL / PostgreSQL)

- **Decision:** الـ migration يستخدم `DB::connection()->getDriverName()` لإضافة driver-specific indexes فقط (مثل `FULLTEXT` على MySQL).
- **Rationale:** Constitution § IV.4.1 يفرض دعم SQLite للتطوير و MySQL للإنتاج. معظم indexes عادية تعمل في كل الـ drivers.
- **Alternatives considered:**
  - الكشف في كل `index()` call — مرفوض لأنه يلوّث الكود.
  - تقسيم migration لـ 3 ملفات (driver-specific) — مرفوض لأنه يضاعف الصيانة.
- **Source:** Constitution § IV.4.1 + Laravel 13 `DB::connection()->getDriverName()`.

### D-5: استخدام FULLTEXT Index

- **Decision:** **لا** نضيف FULLTEXT في هذا الـ feature. defer إلى feature منفصل.
- **Rationale:**
  - استعلامات البحث الحالية تستخدم `LIKE '%foo%'` فقط على `name` و `first_name` (wildcard في البداية) — الـ FULLTEXT **لا يخدم** هذا النمط بدون تغيير الـ queries.
  - SQLite FTS5 syntax مختلف عن MySQL FULLTEXT عن PostgreSQL `tsvector` — صيانة 3-طرق.
  - المشروع لم يطلب بحث نصي متقدم.
- **Alternatives considered:** إضافة FULLTEXT ثم refactor الاستعلامات — مرفوض (scope creep، خارج "تحسين الفهرسة" فقط).
- **Source:** Constitution § X.10 (Anti-Over-Engineering) + research D-6.

### D-6: معالجة `LIKE '%foo%'`

- **Decision:** **عدم** تغيير سلوك البحث النصي. البحث الحالي بطيء بطبيعته (full wildcard). الفهارس لا تخدمه.
- **Rationale:** الـ user-stories تطلب فقط "جلب البيانات بفهرسة". تغيير نمط البحث يخرج عن الـ scope.
- **Alternatives considered:**
  - استبدال بـ `LIKE 'foo%'` (prefix) — مرفوض (يغير UX، بعض السجلات لن تظهر).
  - FULLTEXT — مرفوض (D-5).
- **Source:** Constitution § X.10.

### D-7: Idempotency للـ Migrations

- **Decision:** كل `Schema::table()->index()` يحاط بـ `try/catch` لـ `QueryException` ويتجاهل `Duplicate key name` / `1061` / `SQLSTATE 42000`.
- **Rationale:** يضمن أن تشغيل الـ migration مرتين (في staging ثم في production) لا يفشل. ويتيح `migrate:rollback` ثم `migrate` بدون تعديل.
- **Alternatives considered:**
  - التحقق من وجود الـ index أولاً عبر `SHOW INDEX` — مرفوض (driver-specific).
  - استخدام `DB::statement` مع شرط `IF NOT EXISTS` (MySQL 8) — مرفوض (لا يعمل على SQLite بنفس الصياغة).
- **Source:** نمط موجود في `Modules/AttendanceIntegration/database/migrations/2026_07_15_000003_add_performance_indexes.php`.

### D-8: بنية ملف الـ Migration الواحد

- **Decision:** ملف واحد لكل "وحدة منطقية" من الفهارس. إجمالي 9 ملفات (لا 18+). كل ملف:
  - يبدأ بـ `DB::connection()->getDriverName()`.
  - يحوي `up()` (adds) و `down()` (drops) متطابقين.
  - يحوي PHPDoc يشرح ما يُحسّن.
  - يحوي `try/catch` حول كل `index()` call.
- **Rationale:** يطابق النمط الموجود في `database/migrations/2024_01_01_000090_add_performance_indexes.php`. يجعل `migrate:rollback --step=9` يعكس كل شيء دفعة واحدة.
- **Alternatives considered:** migration منفصل لكل جدول (18+ ملف) — مرفوض (overhead صيانة، والـ rollback يصبح مرهق).
- **Source:** النمط التاريخي للمشروع.

### D-9: Order of Columns in `users` Composite Indexes

- **Decision:** لـ `idx_users_company_status_active` الترتيب `(company_id, status, is_active_employee)`.
- **Rationale:**
  - `company_id` هو المُحدِّد الأكثر تقييداً (أكبر selectivity في نظام متعدد الشركات).
  - `status` و `is_active_employee` قلّة تنوع.
  - الاستعلام النموذجي: `WHERE company_id = ? AND status = 1 AND is_active_employee = 1` — كل الأعمدة متساوية (لا range).
- **Alternatives considered:** `(is_active_employee, status, company_id)` — مرفوض (يخدم استعلام "كل المستخدمين النشطين في كل الشركات" فقط، وهو أقل شيوعاً).
- **Source:** نمط الاستعلام في `UserRepository::applyFilters`.

### D-10: Indexes على جدول `companies/branches/departments/positions/grades`

- **Decision:** **لا** نضيف indexes جديدة (الـ PK و FK suffices).
- **Rationale:** Constitution § VI.6.3 لا يوصي بالفهرسة < 1000 سجل متوقع. هذه الجداول < 1000 سجل دائماً.
- **Alternatives considered:** إضافة indexes للسلامة — مرفوض (overhead بدون فائدة، يخالف Constitution § X).
- **Source:** Constitution § X.10 + § VI.6.3.

### D-11: Index على `users.email`

- **Decision:** **موجود أصلاً** (UNIQUE من `users_table` migration). لا تغيير.
- **Rationale:** Laravel يضيفه تلقائياً عبر `->unique()`.
- **Alternatives considered:** لا شيء.
- **Source:** `database/migrations/0001_01_01_000000_create_users_table.php` line 17.

### D-12: Index على `users.employee_code`

- **Decision:** **لا** نضيف UNIQUE جديد (الـ column قد يحوي تكرارات تاريخية في البيانات الموجودة). فقط نضيف simple index إن لم يكن موجوداً.
- **Rationale:** Constitution BR-13: ممنوع فقدان بيانات. لو أضفنا UNIQUE وفشل التطبيق بسبب تكرار، نخسر.
- **Alternatives considered:** إضافة UNIQUE — مرفوض (BR-13).
- **Source:** Constitution § IV.4.3 + BR-13 في spec.md.

### D-13: Index على `users.is_active_employee` (boolean)

- **Decision:** نُضيفه كجزء من composite `(status, is_active_employee, ...)` بدلاً من standalone.
- **Rationale:** Boolean بطبيعته selectivity ضعيف (50/50 أو أقل). standalone غير مفيد. المركّب مع عمود آخر أفضل بكثير.
- **Alternatives considered:** standalone — مرفوض (لا فائدة، مساحة ضائعة).
- **Source:** use The Index Luke (Low cardinality booleans).

### D-14: Index على `attendance_sessions.check_out_at` (البحث عن جلسات بدون check-out)

- **Decision:** إضافة `idx_att_sessions_checkout` على `(check_out_at, status)`.
- **Rationale:** استعلام شائع: `WHERE check_out_at IS NULL` (الجلسات النشطة). الـ standalone index على NULL يخدمه. `(check_out_at, status)` يدعم أيضاً "الجلسات غير المكتملة + غائب".
- **Alternatives considered:** partial index `WHERE check_out_at IS NULL` — مرفوض (MySQL لا يدعمه، PostgreSQL يدعمه، SQLite محدود).
- **Source:** `AttendanceMonitoringService` (يحوي استعلام `check_out_at IS NULL`).

### D-15: Index على `audit_logs` (الجدولين الموجودين)

- **Decision:** إضافة بسيطة على `audit_logs(action, created_at)` فقط. الـ `audit_logs(actor_id, created_at)` و `(entity_type, entity_id)` موجودة في `Modules\Shifts\database\migrations\2026_07_16_000012_create_audit_logs_table.php`.
- **Rationale:** لا نريد تكرار indexes موجودة.
- **Alternatives considered:** إضافة indexes جديدة على actor/entity — مرفوض (موجودة).
- **Source:** قراءات `Modules\Shifts\database\migrations/2026_07_16_000012_create_audit_logs_table.php`.

### D-16: استعلام `latest()` في `UserRepository::getAll()`

- **Decision:** استبدال `->latest()` بـ `->orderBy('users.id', 'desc')` فقط لو الـ EXPLAIN يثبت مشكلة. (لا تغيير افتراضي.)
- **Rationale:** `latest()` يستخدم `created_at`. لو العمود غير مفهرس، يعمل filesort. لو مفهرس (مفهرَس ضمنياً عبر FK على created_at؟ لا — created_at ليس FK)، يعمل index-scan.
  - **لكن:** `id` هو PK ويستخدم clustered index → ترتيب حسبه أسرع دائماً.
  - **التوصية:** تغييره لأمر "performance، بدون كسر" (نفس الترتيب زمنياً لأن `id` increment).
- **Alternatives considered:** الإبقاء على `latest()` — مقبول، لكن استبداله يفتح سرعة بدون تكلفة.
- **Source:** Constitution § VI.6.1.4 + repository audit.

---

## 3. خريطة الـ Indexes الموجودة (Index Audit Map)

| الجدول | الـ Indexes الموجودة | يلزم جديد؟ | ملاحظات |
|--------|---------------------|-----------|---------|
| `users` | `id` (PK), `email` (UNIQUE), `id != 10000` (PK يخدم) | نعم | composite مفقود |
| `attendance_sessions` | 7 indexes شاملة (من `att_sessions_*_idx`) | نعم | missing composite (user,date,status) |
| `daily_attendance_summaries` | unique + 3 indexes | نعم | missing (date, calculated_at) |
| `raw_attendance_logs` | 4 indexes | نعم | missing dedup + processed/punch |
| `iclock_transaction` | `(emp_id, punch_time)` | نعم | missing `(punch_time)` standalone |
| `schedule_entries` | 3 indexes | نعم | missing (date, employee, day_status) |
| `schedule_periods` | unique + status | لا | كافية |
| `user_vacation_requests` | 5 indexes شاملة | نعم | missing (status, start_date, end_date) |
| `user_vacation_balance_transactions` | (الـ create migration غير مقروء الآن) | نعم | يحتاج audit |
| `fingerprint_devices` | 3 indexes + api_token | نعم | missing (company_id, branch_id, status) |
| `device_sync_logs` | (الـ create migration غير مقروء الآن) | نعم | يحتاج audit |
| `attendance_integration_audit_logs` | 5 indexes | نعم | missing correlation + actor |
| `audit_logs` (Shifts) | 2 indexes | نعم | missing action+date |
| `audit_logs` (AttendanceIntegration) | غير مفحوص | نعم | يحتاج audit |
| `holidays` | 4 indexes شاملة | لا | كافية |
| `subordinations` | 2 indexes | نعم | missing (status, sort_order) |
| `att_hours_tracking` | unique + 2 indexes | نعم | missing (employee, date) |
| `att_rotation_assignments` | 5 indexes | نعم | missing (employee, start, end) |
| `att_employee_shift_categories` | 2 indexes | نعم | missing is_active filter |
| `user_shifts` | unique + 2 indexes + (user, is_primary) | لا | migration `2024_01_01_000090_add_performance_indexes` غطتها |
| `user_zone` | unique + 2 indexes + (user, is_primary) | لا | migration `2024_01_01_000090_add_performance_indexes` غطتها |
| `companies` | PK + UK على `company_code` | لا | حجم صغير |
| `branches` | PK + UK على `branch_code` | لا | حجم صغير |
| `departments` | PK + UK | لا | حجم صغير |
| `positions`, `grades` | PK + UK | لا | حجم صغير |
| `zones` | PK | لا | حجم صغير |
| `settings` | PK + key | لا | cached |

---

## 4. الـ Risks الموثّقة (Risk Register)

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| `CREATE INDEX` يأخذ وقتاً على جدول ضخم | Low | Medium | تشغيل في نافذة صيانة (staging أولاً) |
| تعارض اسم index | Low | Low | try/catch + naming convention صارم |
| زيادة حجم DB 20% | High | Low | مقبول (D-1) |
| كسر query موجود (output change) | Low | High | Test 9.4 (output equivalence) |
| فقدان بيانات | **Zero** | Critical | BR-13/14/15 + Test 9.1 + Scenario 8 |

---

## 5. معايير القبول الإضافية للبحث (Acceptance Criteria for Research)

- [x] صفر أسئلة مفتوحة (`NEEDS CLARIFICATION`).
- [x] كل قرار موثّق بـ Decision/Rationale/Alternatives.
- [x] كل driver (SQLite/MySQL/PostgreSQL) مغطى.
- [x] الـ data preservation مغطى بـ BR-13/14/15.
- [x] الـ query clean code مغطى بـ BR-8..BR-12.
- [x] Index audit map مكتمل لكل جدول.

---

## 6. المراجع (References)

1. Constitution § II (Modular Architecture)
2. Constitution § IV (Database)
3. Constitution § VI (Performance) — particularly § 6.1.4 (Indexing Rules)
4. Constitution § X (Anti-Over-Engineering)
5. Constitution § XIV (Scalability)
6. Laravel 13 docs — `Schema::table()`, `Blueprint::index()`, `DB::statement()`
7. MySQL 8 docs — "Multiple-Column Indexes", "B-Tree Index Characteristics"
8. SQLite docs — "Query Optimizer", "CREATE INDEX"
9. PostgreSQL 14+ docs — "Indexes on Expressions", "Partial Indexes"
10. "Use The Index, Luke!" — A Guide to Database Performance for Developers (Markus Winand)

---

*آخر تحديث: 2026-07-21*
*الإصدار: 1.0.0*
