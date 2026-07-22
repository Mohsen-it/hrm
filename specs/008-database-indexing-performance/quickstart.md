# 008 — Database Indexing & Query Performance — دليل التحقق السريع

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-21
**المرجع:** [spec.md](./spec.md) | [plan.md](./plan.md) | [data-model.md](./data-model.md) | [contracts/](./contracts/)

> **الهدف:** دليل **قابل للتشغيل** (runnable) يثبت أن الـ feature يعمل end-to-end بدون كسر أي شيء وحفظ 100% من البيانات الموجودة.

---

## 0. المتطلبات (Prerequisites)

- PHP 8.3+
- Composer 2.x
- Node 20+ + npm
- Laravel 13 (مفعّل)
- SQLite (افتراضي) **أو** MySQL 8.0+ (للـ production)
- بيانات حقيقية موجودة في DB (لا تعمل على DB فارغ — لا تختبر شيئاً)

---

## 1. قبل كل شيء: Snapshot للبيانات (Baseline Counts)

> **حرج:** احفظ الأعداد قبل أي migration للتأكد لاحقاً.

```bash
# في PowerShell
php artisan tinker --execute='
$counts = [
    "users" => \Modules\Users\Models\User::count(),
    "attendance_sessions" => \Modules\Attendance\Models\AttendanceSession::count(),
    "daily_attendance_summaries" => \Modules\Attendance\Models\DailyAttendanceSummary::count(),
    "raw_attendance_logs" => \Modules\Attendance\Models\RawAttendanceLog::count(),
    "schedule_entries" => \Modules\Shifts\Models\ScheduleEntry::count(),
    "user_vacation_requests" => \Modules\Vacations\Models\UserVacationRequest::count(),
    "fingerprint_devices" => \Modules\FingerprintDevices\Models\FingerprintDevice::count(),
    "audit_logs" => DB::table("audit_logs")->count(),
];
file_put_contents("baseline_counts.json", json_encode($counts, JSON_PRETTY_PRINT));
echo "Snapshot saved to baseline_counts.json\n";
'
```

**الناتج المتوقع:** ملف `baseline_counts.json` يحوي الأعداد الحالية. **هذا هو الـ ground truth**.

---

## 2. تطبيق الـ Migrations (The Main Action)

```bash
# تشغيل كل الـ migrations الـ 9 الجديدة
php artisan migrate

# (أو ملف ملف لو وُجد خطأ)
# php artisan migrate --path=database/migrations/2026_07_21_000001_add_users_composite_indexes.php
# php artisan migrate --path=database/migrations/2026_07_21_000002_add_attendance_query_indexes.php
# ... إلخ
```

**الناتج المتوقع:**
```
2026_07_21_000001_add_users_composite_indexes ............... DONE
2026_07_21_000002_add_attendance_query_indexes .............. DONE
2026_07_21_000003_add_general_audit_indexes ................. DONE
Modules\Users\database\migrations\2026_07_21_000001_add_vacation_query_indexes.php DONE
Modules\FingerprintDevices\database\migrations\2026_07_21_000002_add_device_query_indexes.php DONE
Modules\AttendanceIntegration\database\migrations\2026_07_21_000005_add_audit_logs_indexes.php DONE
Modules\Shifts\database\migrations\2026_07_21_000001_add_schedule_query_indexes.php DONE
Modules\Holidays\database\migrations\2026_07_21_000001_add_holiday_query_index.php DONE
Modules\Subordinations\database\migrations\2026_07_21_000001_add_subordination_query_index.php DONE
```

**في حالة خطأ:** لا تقلق — الـ `try/catch` يتجاهل `Duplicate key name`. لو ظهر خطأ آخر، أوقف وأبلغ.

---

## 3. التحقق 1: البيانات سليمة (Data Parity)

```bash
php artisan tinker --execute='
$baseline = json_decode(file_get_contents("baseline_counts.json"), true);
$current = [
    "users" => \Modules\Users\Models\User::count(),
    "attendance_sessions" => \Modules\Attendance\Models\AttendanceSession::count(),
    "daily_attendance_summaries" => \Modules\Attendance\Models\DailyAttendanceSummary::count(),
    "raw_attendance_logs" => \Modules\Attendance\Models\RawAttendanceLog::count(),
    "schedule_entries" => \Modules\Shifts\Models\ScheduleEntry::count(),
    "user_vacation_requests" => \Modules\Vacations\Models\UserVacationRequest::count(),
    "fingerprint_devices" => \Modules\FingerprintDevices\Models\FingerprintDevice::count(),
    "audit_logs" => DB::table("audit_logs")->count(),
];

$allMatch = true;
foreach ($baseline as $table => $before) {
    $after = $current[$table] ?? null;
    $match = ($before === $after);
    echo sprintf("%-30s: before=%d  after=%d  %s\n", $table, $before, $after ?? -1, $match ? "✅" : "❌ MISMATCH");
    if (!$match) $allMatch = false;
}
echo $allMatch ? "\n🎉 ALL COUNTS MATCH — Data preserved!\n" : "\n❌ DATA LOSS DETECTED!\n";
'
```

**الناتج المتوقع:**
```
users                          : before=154  after=154  ✅
attendance_sessions            : before=4521 after=4521 ✅
daily_attendance_summaries     : before=1820 after=1820 ✅
raw_attendance_logs            : before=23411 after=23411 ✅
schedule_entries               : before=8920 after=8920 ✅
user_vacation_requests         : before=234 after=234 ✅
fingerprint_devices            : before=12 after=12 ✅
audit_logs                     : before=1823 after=1823 ✅

🎉 ALL COUNTS MATCH — Data preserved!
```

**❌ في حالة الفشل:** أوقف فوراً. هذا يعني فقدان بيانات. لا تكمّل. تحقق من migration logs.

---

## 4. التحقق 2: الفهارس موجودة (Index Existence)

### 4.1 على MySQL:

```sql
-- في MySQL CLI
SHOW INDEX FROM users;
SHOW INDEX FROM attendance_sessions;
-- إلخ
```

**ابحث عن:**
- `idx_users_company_status_active`
- `idx_att_sessions_user_date_status`
- `idx_raw_logs_dedup`
- `idx_vacation_req_status_start`
- `idx_devices_company_branch`
- `idx_schedule_entries_date_emp`
- ... إلخ (35 index جديد متوقع)

### 4.2 على SQLite:

```bash
php artisan tinker --execute='
$indexes = DB::select("SELECT name, tbl_name FROM sqlite_master WHERE type = ? AND name LIKE ?", ["index", "idx_%"]);
foreach ($indexes as $idx) {
    echo $idx->tbl_name . " -> " . $idx->name . "\n";
}
'
```

### 4.3 اختبار برمجي:

```php
// في tests/Feature/IndexingTest.php
test('users composite indexes exist', function () {
    $indexes = collect(DB::select("SHOW INDEX FROM users"))->pluck('Key_name')->unique();
    expect($indexes->toArray())->toContain('idx_users_company_status_active');
});
```

**الناتج المتوقع:** كل الـ 35 index موجود. **في حالة النقص:** راجع الـ contracts/ddl-contracts.md وابحث عن اسم الـ index الغائب.

---

## 5. التحقق 3: EXPLAIN يستخدم Index (Index Usage)

### 5.1 على MySQL:

```sql
EXPLAIN SELECT * FROM users WHERE company_id = 1 AND status = 1 AND is_active_employee = 1 LIMIT 20;
```

**المتوقع في `type`:** `ref` أو `range` (وليس `ALL`).

**المتوقع في `key`:** `idx_users_company_status_active`.

### 5.2 على SQLite:

```bash
php artisan tinker --execute='
DB::enableQueryLog();
$users = \Modules\Users\Models\User::where("company_id", 1)
    ->where("status", 1)
    ->where("is_active_employee", true)
    ->limit(20)
    ->get();
echo "Query: " . DB::getQueryLog()[0]["query"] . "\n";
echo "Rows: " . $users->count() . "\n";
'
```

**المتوقع:** الاستعلام يُنفّذ < 50ms على 10,000+ سجل.

### 5.3 اختبارات EXPLAIN برمجية:

```php
// في tests/Feature/IndexingTest.php
test('user query uses index, not table scan', function () {
    if (DB::getDriverName() === 'mysql') {
        $explain = DB::select('
            EXPLAIN SELECT * FROM users
            WHERE company_id = 1
              AND status = 1
              AND is_active_employee = 1
            LIMIT 20
        ');
        expect($explain[0]->type)->not->toBe('ALL', 'Query should use index, not table scan');
        expect($explain[0]->key)->toBe('idx_users_company_status_active');
    } else {
        $this->markTestSkipped('MySQL-specific test');
    }
});
```

---

## 6. التحقق 4: Rollback آمن (Safe Rollback)

```bash
# الـ rollback الكامل (9 migrations)
php artisan migrate:rollback --step=9
```

**الناتج المتوقع:** كل الـ 9 migrations تُرجع بدون أخطاء.

**ثم:**

```bash
# إعادة تطبيق
php artisan migrate
```

**ثم أعد الخطوة 3** (تحقق الـ counts لا تزال متطابقة).

**الناتج المتوقع:** `🎉 ALL COUNTS MATCH — Data preserved!`

**✅ تأكيد:** حتى بعد rollback + re-apply، لا فقدان.

---

## 7. التحقق 5: الاختبارات الموجودة (Existing Tests)

```bash
php artisan test
```

**الناتج المتوقع:** كل الاختبارات الموجودة تمر بدون أخطاء. لا يجب إضافة ولا تعديل أي اختبار موجود.

---

## 8. التحقق 6: لا تغيير في الواجهة (UI Regression)

```bash
# شغّل الـ dev server
composer dev
```

ثم يدوياً (في المتصفح):

1. افتح `/users` — يجب أن يظهر نفس الشكل، نفس الفلاتر.
2. افتح `/attendance` — نفس التقويم، نفس الإحصائيات.
3. افتح `/shifts/calendar` — نفس التقويم.
4. افتح `/vacations/requests` — نفس القائمة.

**لا يجب** أن يظهر:
- ❌ Console errors في DevTools
- ❌ Vue warnings جديدة
- ❌ صفحات 500
- ❌ بيانات فارغة (إلا لو كانت فارغة قبل الـ migration أيضاً)

---

## 9. التحقق 7: لا تغيير في الـ API (API Compatibility)

```bash
# التقاط استجابة قبل (للمقارنة)
php artisan tinker --execute='
$response = \Modules\Users\Models\User::with("company", "branch", "department", "subordination", "shift")
    ->where("company_id", 1)
    ->where("status", 1)
    ->paginate(20);
file_put_contents("api_before.json", json_encode($response->toArray()));
'

# بعد migration، قارن
php artisan tinker --execute='
$response = \Modules\Users\Models\User::with("company", "branch", "department", "subordination", "shift")
    ->where("company_id", 1)
    ->where("status", 1)
    ->paginate(20);
$current = json_encode($response->toArray());
$before = file_get_contents("api_before.json");
echo $current === $before ? "✅ API OUTPUT IDENTICAL\n" : "❌ API OUTPUT CHANGED\n";
'
```

**المتوقع:** `✅ API OUTPUT IDENTICAL`.

---

## 10. التحقق 8: الأداء (Performance)

> هذه المقارنة **اختيارية** لكنها تظهر القيمة.

```bash
# قياس الزمن قبل/بعد (في MySQL CLI)
SET profiling = 1;
SELECT * FROM users WHERE company_id = 1 AND status = 1 AND is_active_employee = 1 LIMIT 20;
SHOW PROFILES;
```

**المتوقع:** < 50ms على 10,000+ سجل.

---

## 11. معايير النجاح (Pass/Fail)

| المعيار | الوزن | الحالة |
|---------|------|--------|
| التحقق 1: counts متطابقة | **Critical** | ✅ مطلوب |
| التحقق 2: 35 index موجودة | High | ✅ مطلوب |
| التحقق 3: EXPLAIN يستخدم index | High | ✅ مطلوب |
| التحقق 4: rollback آمن | High | ✅ مطلوب |
| التحقق 5: php artisan test ينجح | Critical | ✅ مطلوب |
| التحقق 6: لا UI changes | Medium | ✅ مطلوب |
| التحقق 7: API identical | High | ✅ مطلوب |
| التحقق 8: < 100ms queries | Medium | ⚠️ تحسين |

**فشل أي Critical → الـ feature مرفوض.**

---

## 12. استكشاف الأخطاء (Troubleshooting)

### 12.1 "Duplicate key name"

**السبب:** الـ migration شُغّل مرتين أو الـ index موجود.
**الحل:** الـ `try/catch` يتجاهل. لو ظهرت، تجاهلها.

### 12.2 "Cannot add index on a non-existing column"

**السبب:** الـ migration مُدد على جدول غير موجود (نادر).
**الحل:** تحقق من `Schema::hasTable()` قبل `$table->index()`.

### 12.3 "Data integrity violation"

**السبب:** فقدان بيانات (لا يجب أن يحدث).
**الحل:** **أوقف فوراً**، أبلغ، استعد من backup.

### 12.4 "Rollback failed"

**السبب:** الـ down() حاول حذف index غير موجود.
**الحل:** wrap `dropIndex` في `try/catch` كذلك (نفس النمط).

---

## 13. ملخص الأوامر (Command Cheat Sheet)

```bash
# 1. Snapshot
php artisan tinker --execute='...baseline_counts.json...'

# 2. Migrate
php artisan migrate

# 3. Verify counts
php artisan tinker --execute='...allMatch...'

# 4. Show indexes (MySQL)
mysql -u root -p -e "SHOW INDEX FROM users;" hrm_alepair

# 5. Test
php artisan test

# 6. Rollback (آمن)
php artisan migrate:rollback --step=9
php artisan migrate

# 7. Lint
php artisan pint

# 8. Logs
tail -f storage/logs/laravel.log
```

---

*آخر تحديث: 2026-07-21*
*الإصدار: 1.0.0*
