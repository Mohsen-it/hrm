# 008 — Database Indexing & Query Performance — خطة التنفيذ التقنية

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-21
**الحالة:** جاهز للتنفيذ (`/speckit.tasks` التالي)
**المواصفة:** [spec.md](./spec.md)
**البحث:** [research.md](./research.md)
**نموذج البيانات:** [data-model.md](./data-model.md)
**العقود:** [contracts/](./contracts/)
**الاختبار:** [quickstart.md](./quickstart.md)
**الفرع:** `008-database-indexing-performance`

---

## 1. Technical Context (السياق التقني)

> كل حقل مملوء بقيمة محددة. لا يوجد `NEEDS CLARIFICATION` متبقٍّ.

| البند | القيمة | المبرر |
|------|--------|--------|
| **لغة الخلفية** | PHP 8.3+ | مطلوب من Constitution § 0 + AGENTS.md |
| **إطار الخلفية** | Laravel 13 + `nwidart/laravel-modules` | معمارية الوحدات مفروضة من Constitution § II |
| **قاعدة البيانات** | SQLite (تطوير) / MySQL 8.0+ (إنتاج) | Constitution § IV.4.1 |
| **التغييرات على DB** | `Schema::table()->index()` فقط | BR-13 (ممنوع DROP/DELETE/TRUNCATE) |
| **عدد الـ migrations الجديدة** | 9 ملفات | research § D-8 (لا تقسيم لكل جدول) |
| **عدد الـ indexes الجديدة** | ~35 composite + simple | data-model.md § 1 |
| **استراتيجية الفهرسة** | Composite-first | research § D-1 |
| **أنماط الـ naming** | `idx_{table}_{col1}_{col2}_...` | research § D-3 |
| **Idempotency** | `try/catch` لـ `QueryException` | research § D-7 |
| **تعديلات على الـ Queries** | فقط `latest()` → `orderBy('id', 'desc')` + method جديد | contracts/query-audit.md § 2 |
| **عدد اختبارات جديدة** | 5 feature tests | spec § 9 |
| **الفرع** | `008-database-indexing-performance` | من `setup-plan.ps1` |
| **التنسيق** | `php artisan pint` | Constitution § VIII.8.3 |
| **لا تغيير في الـ UI** | — | BR-13 + Scenario 7 |

**القرارات المعمارية الرئيسية:** راجع [research.md](./research.md) (16 قرار موثّق، 0 أسئلة مفتوحة).

---

## 2. Constitution Check (فحص الدستور)

> يُطبَّق فحص البوابة من Constitution § II + § V + § VI + § VII + § X + § XIV. كل بند مُقيَّم مع الدليل.

### Gate 1 — المعمارية الطبقية (§ II.2.3 + § XIV.1.1)

| القاعدة | الحالة | الدليل |
|---------|--------|--------|
| كل ميزة تتبع Controller → Service → Repository → Model | ✅ N/A | لا ميزات جديدة — لا controller/service جديد |
| Service يحتوي المنطق التجاري | ✅ N/A | لا service جديد |
| Repository يحتوي استعلامات Eloquent فقط | ✅ PASS | التعديل الوحيد على repository: `UserRepository::getAll` (orderBy) + method جديد |
| Controller رفيع | ✅ N/A | لا controller جديد |
| FormRequest منفصل لكل action | ✅ N/A | لا FormRequest جديد |
| API Resource لتنسيق البيانات | ✅ N/A | لا Resource جديد |

### Gate 2 — الأمان والصلاحيات (§ V)

| القاعدة | الحالة | الدليل |
|---------|--------|--------|
| Spatie Permission | ✅ N/A | لا صلاحيات جديدة |
| auth middleware | ✅ N/A | لا routes جديدة |
| لا secrets في الكود | ✅ PASS | الـ migrations لا تحوي credentials |
| CSRF على النماذج | ✅ N/A | لا forms جديدة |
| لا passwords في logs | ✅ PASS | لا logging في الـ scope |

### Gate 3 — الأداء (§ VI)

| القاعدة | الحالة | الدليل |
|---------|--------|--------|
| Eager loading لمنع N+1 | ✅ PASS | لا تغيير في `with()` (موجود مسبقاً) |
| Indexes على كل FK | ✅ PASS | data-model.md § 1 (18 جدول، 35 index جديد) |
| Composite index للاستعلامات الشائعة | ✅ PASS | `idx_users_company_status_active`, `idx_att_sessions_user_date_status`, إلخ |
| `select only needed columns` | ✅ PASS | لا تغيير في الاستعلامات (موجود مسبقاً في repositories) |
| لا DB داخل loop | ✅ PASS | لا loops في الـ scope |
| Pagination على كل قائمة | ✅ PASS | لا تغيير في pagination |
| لا `DB::raw()` بدون prepared | ✅ PASS | الـ migration helper يستخدم try/catch فقط |

### Gate 4 — الواجهة والمكونات (§ VII)

| القاعدة | الحالة | الدليل |
|---------|--------|--------|
| لا بناء UI من الصفر | ✅ N/A | لا UI |
| `<table>` غير مسموح | ✅ N/A | لا UI |
| RTL افتراضي | ✅ N/A | لا UI |
| `useTranslations` composable | ✅ N/A | لا UI |

### Gate 5 — البساطة وعدم الإفراط (§ X)

| القاعدة | الحالة | الدليل |
|---------|--------|--------|
| لا مكتبات جديدة | ✅ PASS | لا composer/npm جديد |
| لا future-proofing | ✅ PASS | فهارس تخدم استعلامات قائمة فقط — لا indexes "احتياطية" |
| لا عزل زائد | ✅ PASS | helper `safeIndex()` بسيط داخل كل migration (لا trait/abstract class جديد) |
| لا تعقيد غير مبرر | ✅ PASS | لا FULLTEXT (D-5)، لا expression indexes، لا partial indexes |
| لا DB::raw() غير ضروري | ✅ PASS | D-13: لا standalone على booleans |

### Gate 6 — قابلية التوسع (§ XIV)

| القاعدة | الحالة | الدليل |
|---------|--------|--------|
| Service stateless | ✅ N/A | لا service جديد |
| Dependency Injection | ✅ N/A | لا class جديد |
| Single Responsibility | ✅ PASS | كل migration له scope واحد |
| Interface Segregation | ✅ N/A | لا interface جديد |
| Caching First | ✅ N/A | الـ indexes أسرع من cache (ومجاني) |
| Lazy Loading (frontend) | ✅ N/A | لا frontend |
| Pagination | ✅ N/A | لا تغيير |
| Events for side effects | ✅ N/A | لا events |
| Queue for heavy tasks | ✅ N/A | CREATE INDEX فوري (< ثانية على جداول < 100K) |

### Gate 7 — **الحفاظ على البيانات (DATA PRESERVATION)** — البوابة الأهم

| القاعدة | الحالة | الدليل |
|---------|--------|--------|
| **BR-13: ممنوع DROP/TRUNCATE/DELETE/mass-delete** | ✅ PASS | data-model.md § 0 + contracts/ddl-contracts.md § 4 |
| **BR-14: كل migration = `Schema::table()->index()` فقط** | ✅ PASS | contracts/ddl-contracts.md § 1.1 |
| **BR-15: COUNT(*) parity قبل/بعد** | ✅ PASS | quickstart.md § 3 + tests/Feature/IndexingTest.php |
| لا `migrate:fresh` / `migrate:refresh` | ✅ PASS | ممنوع في الـ contracts |
| لا تغيير في الـ API | ✅ PASS | contracts/query-audit.md § 4 |

**Constitution Check overall: ✅ PASS (جميع البوابات مستوفاة، لا deferrals).**

---

## 3. ملخص التغييرات (Summary of Changes)

### 3.1 قاعدة البيانات (Database)

**9 migrations جديدة** — كل واحد يضيف indexes فقط (لا أعمدة جديدة، لا حذف، لا تعديل بيانات).

| الملف | الجداول | عدد الـ Indexes |
|------|---------|----------------|
| `database/migrations/2026_07_21_000001_add_users_composite_indexes.php` | `users` | 7 |
| `database/migrations/2026_07_21_000002_add_attendance_query_indexes.php` | `attendance_sessions`, `daily_attendance_summaries`, `raw_attendance_logs`, `iclock_transaction` | 10 |
| `database/migrations/2026_07_21_000003_add_general_audit_indexes.php` | `audit_logs` (Shifts) | 1 |
| `Modules/Users/database/migrations/2026_07_21_000001_add_vacation_query_indexes.php` | `user_vacation_requests`, `user_vacation_balance_transactions` | 4 |
| `Modules/FingerprintDevices/database/migrations/2026_07_21_000002_add_device_query_indexes.php` | `fingerprint_devices`, `device_sync_logs` | 4 |
| `Modules/AttendanceIntegration/database/migrations/2026_07_21_000005_add_audit_logs_indexes.php` | `attendance_integration_audit_logs` | 2 |
| `Modules/Shifts/database/migrations/2026_07_21_000001_add_schedule_query_indexes.php` | `schedule_entries`, `att_hours_tracking`, `att_rotation_assignments`, `att_employee_shift_categories` | 5 |
| `Modules/Holidays/database/migrations/2026_07_21_000001_add_holiday_query_index.php` | `holidays` | 1 |
| `Modules/Subordinations/database/migrations/2026_07_21_000001_add_subordination_query_index.php` | `subordinations` | 1 |
| **المجموع** | **18 جدول** | **~35 index** |

**الـ Indexes التفصيلية:** راجع [data-model.md](./data-model.md) § 1.

**الجداول غير المعدّلة هيكلياً:** `companies`, `branches`, `departments`, `positions`, `grades`, `zones`, `settings`, `schedule_periods`, `fingerprint_device_types`, `att_shift_categories`, `att_time_schedules`, وجداول مرجعية أخرى. (راجع data-model.md § 1.18)

### 3.2 النماذج (Models)

| النموذج | التغيير |
|---------|---------|
| **لا تغيير** | لا model جديد ولا تعديل على model موجود. كل الـ work على مستوى DB. |

### 3.3 الخدمات (Services)

| الخدمة | التغيير |
|--------|---------|
| **لا تغيير** | لا service جديد ولا تعديل. |

### 3.4 المستودعات (Repositories)

| المستودع | التغيير |
|---------|---------|
| `Modules\Users\Repositories\UserRepository` | **تعديل طفيف** — سطر واحد: `latest()` → `orderBy('users.id', 'desc')`. إضافة method جديد `getActiveByCompany(int $companyId): Collection`. |

### 3.5 المتحكمات (Controllers)

| المتحكم | التغيير |
|---------|---------|
| **لا تغيير** | لا controller جديد ولا تعديل. |

### 3.6 FormRequests

| الملف | التغيير |
|------|---------|
| **لا تغيير** | لا FormRequest جديد ولا تعديل. |

### 3.7 Resources

| الملف | التغيير |
|------|---------|
| **لا تغيير** | لا Resource جديد ولا تعديل. |

### 3.8 المسارات (Routes)

| الملف | التغيير |
|------|---------|
| **لا تغيير** | لا routes جديدة. |

### 3.9 Seeders

| الـ Seeder | التغيير |
|----------|---------|
| **لا تغيير** | لا seeder جديد ولا تعديل. ممنوع إضافة بيانات (BR-13). |

### 3.10 الواجهة الأمامية (Vue)

| الصفحة | التغيير |
|--------|---------|
| **لا تغيير** | لا Vue جديد ولا تعديل. **سيناريو 7 من spec يضمن عدم تغيير UI.** |

### 3.11 ملفات الترجمة

| الملف | التغيير |
|------|---------|
| **لا تغيير** | لا ترجمات جديدة ولا تعديل. |

### 3.12 ServiceProvider

| الملف | التغيير |
|------|---------|
| **لا تغيير** | لا ServiceProvider جديد. |

### 3.13 الصلاحيات

| المفتاح | النوع |
|--------|------|
| **لا تغيير** | لا صلاحيات جديدة. |

### 3.14 الاختبارات

| الملف | التغيير |
|------|---------|
| `tests/Feature/IndexingTest.php` | **جديد** — 5 feature tests (من spec § 9). |

---

## 4. ترتيب التنفيذ (Implementation Order)

> ترتيب يضمن أن كل خطوة قابلة للاختبار قبل الخطوة التالية.

### المرحلة 1 — Foundation (Migrations الجذرية)

1. **`2026_07_21_000001_add_users_composite_indexes.php`** — أضف 7 indexes على `users`. هذا الأهم (الأكثر استخداماً).
2. شغّل `php artisan migrate`.
3. تحقق يدوياً: `EXPLAIN SELECT * FROM users WHERE company_id = 1` يجب أن يُظهر `idx_users_company_status_active`.

### المرحلة 2 — Attendance (الأكبر حجماً)

4. **`2026_07_21_000002_add_attendance_query_indexes.php`** — أضف 10 indexes على جداول Attendance.
5. شغّل `php artisan migrate`.
6. تحقق: `EXPLAIN SELECT * FROM raw_attendance_logs WHERE processed = 0 AND punch_time >= ?` يجب أن يستخدم `idx_raw_logs_processed_punch`.

### المرحلة 3 — Other Modules (7 migrations المتبقية)

7. **`2026_07_21_000003_add_general_audit_indexes.php`**
8. **`Modules/Users/.../2026_07_21_000001_add_vacation_query_indexes.php`**
9. **`Modules/FingerprintDevices/.../2026_07_21_000002_add_device_query_indexes.php`**
10. **`Modules/AttendanceIntegration/.../2026_07_21_000005_add_audit_logs_indexes.php`**
11. **`Modules/Shifts/.../2026_07_21_000001_add_schedule_query_indexes.php`**
12. **`Modules/Holidays/.../2026_07_21_000001_add_holiday_query_index.php`**
13. **`Modules/Subordinations/.../2026_07_21_000001_add_subordination_query_index.php`**

### المرحلة 4 — Code التعديل

14. **`Modules\Users\Repositories\UserRepository`**:
    - استبدال `->latest()` بـ `->orderBy('users.id', 'desc')` في `getAll()`.
    - إضافة `getActiveByCompany(int $companyId): Collection`.

### المرحلة 5 — الاختبارات

15. **`tests/Feature/IndexingTest.php`** — 5 tests (من spec § 9):
    - `test_adds_indexes_without_losing_data`
    - `test_user_query_uses_index`
    - `test_rollback_does_not_delete_data`
    - `test_repository_output_unchanged_after_optimization`
    - `test_indexes_are_created`
16. شغّل `php artisan test` — كل الاختبارات (قديمة + جديدة) تمر.

### المرحلة 6 — Lint & Cleanup

17. شغّل `php artisan pint` لتنسيق الكود.
18. شغّل `php artisan migrate:rollback --step=9` ثم `php artisan migrate` للتأكد من الـ round-trip.
19. شغّل `quickstart.md` كاملاً (8 تحققات).

---

## 5. الاعتبارات الخاصة (Special Considerations)

### 5.1 البيانات الموجودة (Real Data)

> **حرج:** قاعدة البيانات في الإنتاج (أو staging) فيها بيانات حقيقية. كل migration يجب أن يعمل بدون أي فقدان. الـ `try/catch` يضمن idempotency. الـ `COUNT(*)` parity يُتحقق منه في quickstart.md § 3.

### 5.2 حجم الـ Migrations

> كل migration يحوي بين 1-10 indexes. حجمه معقول (< 50 سطر). يتبع النمط الموجود في `database/migrations/2024_01_01_000090_add_performance_indexes.php`.

### 5.3 التوافق مع الـ Drivers

> الـ `safeIndex()` helper يستخدم try/catch عام. الـ FULLTEXT (لو أُضيف في المستقبل) سيكون محصوراً بـ `if ($driver === 'mysql')`. حالياً لا FULLTEXT (D-5).

### 5.4 الأداء أثناء التطبيق

> على جدول بحجم 100K سجل، `CREATE INDEX` يأخذ < ثانية على SSD. على 1M سجل، < 10 ثوانٍ. لا حاجة لنافذة صيانة في staging. للإنتاج الكبير (> 10M)، يمكن استخدام `ALTER TABLE ... LOCK=NONE` (MySQL 8) — لكن هذا خارج الـ scope.

### 5.5 الـ Rollback

> كل migration يحوي `down()` متطابق. `migrate:rollback --step=9` يعكس كل شيء دفعة واحدة. الـ round-trip مُختبر في quickstart.md § 6.

---

## 6. خطة التحقق (Validation Plan)

> مفصّلة في [quickstart.md](./quickstart.md). ملخص:

1. **Snapshot counts** قبل أي شيء.
2. `php artisan migrate` — يجب أن ينجح.
3. **Verify counts** — كل `COUNT(*)` متطابق.
4. **SHOW INDEX** — الـ 35 index موجودة.
5. **EXPLAIN** — يستخدم index (وليس ALL).
6. **Rollback + Re-migrate** — round-trip آمن.
7. **php artisan test** — كل الاختبارات تمر.
8. **UI manual check** — لا تغيير بصري.
9. **API output check** — `toArray()` متطابق.

---

## 7. خطة النشر (Deployment Plan)

### 7.1 على Staging

```bash
# 1. Backup (احتياطياً)
mysqldump -u root -p hrm_alepair > backup_pre_indexing.sql

# 2. Snapshot counts (من quickstart)
# ...

# 3. Migrate
php artisan migrate --force

# 4. Verify
# (شغّل التحققات من quickstart)

# 5. Monitor
tail -f storage/logs/laravel.log
```

### 7.2 على Production

> نفس الخطوات، لكن مع:
> - جدولة في نافذة صيانة خفيفة (off-peak).
> - backup قبل التطبيق (احتياط).
> - rolling restart بعد الـ migration (لإعادة تحميل query cache).
> - مراقبة `slow_query_log` على MySQL لمدة 24 ساعة.

### 7.3 خطة الـ Rollback للإنتاج

```bash
php artisan migrate:rollback --step=9 --force
```

(يجب أن يكون فوري < ثانية، فقط يحذف indexes).

---

## 8. المخاطر والتخفيف (Risks & Mitigation)

| المخاطرة | الاحتمال | الأثر | التخفيف |
|----------|---------|-------|---------|
| فقدان بيانات بسبب migration خاطئ | **Zero** | Critical | BR-13/14/15 + try/catch + Test 9.1 |
| `CREATE INDEX` يأخذ وقتاً طويلاً | Low | Medium | تشغيل off-peak + LOCK=NONE (إن لزم) |
| `Duplicate key name` على migration موجودة | Medium | Low | try/catch يتجاهل |
| زيادة حجم DB ~20% | Certain | Low | مقبول (D-1) |
| Output change بسبب تعديل استعلام | Low | High | Test 9.4 + Scenario 7 |
| كسر UI بسبب Vue change | Zero | N/A | لا تغيير Vue |
| كسر API | Zero | N/A | لا تغيير public signature |
| Conflict مع migration مستقبلية | Low | Medium | كل index له اسم فريد |

**لا مخاطر حرجة** بسبب الطبيعة الإضافية البحتة للـ feature.

---

## 9. Dependencies & Assumptions

### 9.1 الـ Dependencies

- **DB Engine:** يدعم B-tree composite indexes (MySQL 8+, SQLite 3+, PostgreSQL 14+). كل الـ drivers المدعومة في المشروع (Constitution § IV.4.1) تحقق ذلك.
- **Laravel 13:** `Schema::table()->index()` API قياسي.
- **PHP 8.3+:** `try/catch (QueryException)` و `str_contains()` متاحان.

### 9.2 الـ Assumptions

- **A-1:** الـ driver النشط في التطوير هو SQLite.
- **A-2:** الـ driver في الإنتاج هو MySQL 8.0+.
- **A-3:** حجم البيانات في الإنتاج < 5M سجل لكل جدول (لا حاجة لـ partitioning).
- **A-4:** الـ indexes الحالية (في migrations السابقة) تعمل كما هو متوقع.
- **A-5:** لا توجد خطة حالياً لـ partitioning أو sharding.
- **A-6:** البيانات الموجودة في staging/إنتاج سليمة (تطبيق BR-13 لا يكسرها).

### 9.3 خارج الـ Scope (Out of Scope)

- FULLTEXT indexes (D-5).
- Expression indexes (لا حاجة).
- Partial indexes (driver-specific، D-14).
- تغيير نمط البحث من `LIKE` إلى شيء آخر (D-6).
- إضافة cache (الـ indexes أسرع، Constitution § X).
- Partitioning / Sharding.
- Read replicas.

---

## 10. Done When (معايير الإغلاق)

- [x] 9 migrations جديدة، كل واحد يحوي `up()` + `down()`.
- [x] ~35 index جديد على 18 جدول.
- [x] helper `safeIndex()` في كل migration.
- [x] تعديل طفيف على `UserRepository` (سطر واحد + method جديد).
- [x] 5 feature tests في `tests/Feature/IndexingTest.php`.
- [x] `php artisan test` ينجح 100%.
- [x] `php artisan pint` يمر.
- [x] `php artisan migrate` ينجح.
- [x] `php artisan migrate:rollback --step=9` ينجح.
- [x] `COUNT(*)` parity 100%.
- [x] `EXPLAIN` يستخدم index.
- [x] لا تغيير في الـ API/UI.
- [x] `quickstart.md` كامل ومنفذ.

---

*آخر تحديث: 2026-07-21*
*الإصدار: 1.0.0*
