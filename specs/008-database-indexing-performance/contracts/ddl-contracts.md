# عقد الـ DDL (Database Indexes DDL Contract)

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-21

> **العقد** هنا ليس API contract — بل **DDL contract** يحدد بالضبط ما الذي ستُنشئه migrations الـ 9 (الـ CREATE INDEX statements) وكيف ستبدو في كل driver.

---

## 1. العقد الإلزامي (Mandatory Contract)

### 1.1 القواعد الصارمة

| # | القاعدة | المبرر |
|---|---------|--------|
| 1 | كل migration يحوي `up()` و `down()` متطابقين عكسياً | Constitution § X + BR-9 |
| 2 | كل `up()` يستخدم `Schema::table()` فقط (لا `Schema::create`) | لا إنشاء جداول — BR-13 |
| 3 | كل `index()` يحاط بـ `try/catch (QueryException)` | Idempotency per D-7 |
| 4 | كل migration يبدأ بـ PHPDoc | Constitution § IX |
| 5 | الـ down() يحذف indexes فقط (عبر `dropIndex(name)`) | BR-13 + BR-14 |
| 6 | لا `DB::statement` مع DDL مُدمّر | BR-13 |
| 7 | لا `DB::raw()` على `DROP TABLE` / `TRUNCATE` | BR-13 |
| 8 | كل اسم index يتبع `idx_{table}_{col1}_{col2}_...` | D-3 |
| 9 | الـ orderBy في الـ composite = مساواة أولاً ثم نطاق | D-2 |
| 10 | كل ملف له timestamp `2026_07_21_*` | الترتيب الزمني |

### 1.2 القواعد الخاصة بكل Driver

```php
$driver = DB::connection()->getDriverName();
// 'mysql', 'sqlite', 'pgsql'

// FULLTEXT فقط على MySQL
if ($driver === 'mysql') {
    DB::statement('ALTER TABLE ... ADD FULLTEXT INDEX ...');
}
```

---

## 2. الـ 9 Migrations (DDL Contracts)

### Contract M-1: `database/migrations/2026_07_21_000001_add_users_composite_indexes.php`

**الـ up() — DDL على `users`:**

| Index | Columns | Type |
|-------|---------|------|
| `idx_users_company_status_active` | `(company_id, status, is_active_employee)` | composite |
| `idx_users_branch_status` | `(branch_id, status)` | composite |
| `idx_users_department_status` | `(department_id, status)` | composite |
| `idx_users_position_status` | `(position_id, status)` | composite |
| `idx_users_grade_status` | `(grade_id, status)` | composite |
| `idx_users_employment_type` | `(employment_type)` | simple |
| `idx_users_hire_date` | `(hire_date)` | simple |

**الـ down() — نفس الـ 7 indexes، `dropIndex` لكل واحد.**

### Contract M-2: `database/migrations/2026_07_21_000002_add_attendance_query_indexes.php`

**الـ up() — DDL على 3 جداول:**

على `attendance_sessions`:

| Index | Columns |
|-------|---------|
| `idx_att_sessions_user_date_status` | `(user_id, attendance_date, status)` |
| `idx_att_sessions_date_status_type` | `(attendance_date, status, session_type)` |
| `idx_att_sessions_created_by` | `(created_by, attendance_date)` |
| `idx_att_sessions_checkout` | `(check_out_at, status)` |

على `daily_attendance_summaries`:

| Index | Columns |
|-------|---------|
| `idx_daily_summaries_date_calculated` | `(summary_date, calculated_at)` |
| `idx_daily_summaries_status_date` | `(status, summary_date)` |

على `raw_attendance_logs`:

| Index | Columns |
|-------|---------|
| `idx_raw_logs_dedup` | `(device_id, device_user_id, punch_time)` |
| `idx_raw_logs_user_time` | `(device_user_id, punch_time)` |
| `idx_raw_logs_processed_punch` | `(processed, punch_time)` |

على `iclock_transaction` (إن وُجد):

| Index | Columns |
|-------|---------|
| `idx_iclock_punch_time` | `(punch_time)` |

**الـ down() — dropIndex لكل واحد.**

### Contract M-3: `Modules/Users/database/migrations/2026_07_21_000001_add_vacation_query_indexes.php`

على `user_vacation_requests`:

| Index | Columns |
|-------|---------|
| `idx_vacation_req_status_start` | `(status, start_date, end_date)` |
| `idx_vacation_req_user_dates` | `(user_id, start_date)` |
| `idx_vacation_req_decided` | `(decided_at, status)` |

على `user_vacation_balance_transactions`:

| Index | Columns |
|-------|---------|
| `idx_vacation_bal_tx_date` | `(user_id, vacation_type_id, created_at)` |

### Contract M-4: `Modules/FingerprintDevices/database/migrations/2026_07_21_000002_add_device_query_indexes.php`

على `fingerprint_devices`:

| Index | Columns |
|-------|---------|
| `idx_devices_company_branch` | `(company_id, branch_id, status)` |
| `idx_devices_last_pushed` | `(last_pushed_at, status)` |

على `device_sync_logs`:

| Index | Columns |
|-------|---------|
| `idx_sync_logs_device_date` | `(device_id, started_at)` |
| `idx_sync_logs_status_date` | `(status, started_at)` |

### Contract M-5: `Modules/AttendanceIntegration/database/migrations/2026_07_21_000005_add_audit_logs_indexes.php`

على `attendance_integration_audit_logs`:

| Index | Columns |
|-------|---------|
| `idx_audit_correlation` | `(correlation_id, occurred_at)` |
| `idx_audit_actor` | `(actor_id, occurred_at)` |

### Contract M-6: `Modules/Shifts/database/migrations/2026_07_21_000001_add_schedule_query_indexes.php`

على `schedule_entries`:

| Index | Columns |
|-------|---------|
| `idx_schedule_entries_date_emp` | `(date, employee_id, day_status)` |
| `idx_schedule_entries_period_status` | `(schedule_period_id, day_status)` |

على `att_hours_tracking`:

| Index | Columns |
|-------|---------|
| `idx_hours_user_date` | `(user_id, period_start, period_end)` |
| `idx_hours_employee_category` | `(employee_id, shift_category_id, period_start)` |

على `att_rotation_assignments`:

| Index | Columns |
|-------|---------|
| `idx_rotation_assign_emp_dates` | `(employee_id, start_date, end_date)` |

على `att_employee_shift_categories`:

| Index | Columns |
|-------|---------|
| `idx_esc_active` | `(is_active, employee_id)` |

### Contract M-7: `Modules/Holidays/database/migrations/2026_07_21_000001_add_holiday_query_index.php`

على `holidays`:

| Index | Columns |
|-------|---------|
| `idx_holidays_date_active` | `(date, is_active)` |

### Contract M-8: `Modules/Subordinations/database/migrations/2026_07_21_000001_add_subordination_query_index.php`

على `subordinations`:

| Index | Columns |
|-------|---------|
| `idx_subordinations_status_order` | `(status, sort_order)` |

### Contract M-9: `database/migrations/2026_07_21_000003_add_general_audit_indexes.php`

على `audit_logs` (في Shifts):

| Index | Columns |
|-------|---------|
| `idx_audit_action_date` | `(action, created_at)` |

---

## 3. Helper Contract: `safeIndex()` Pattern

كل migration يستخدم هذا الـ helper:

```php
/**
 * Add an index while tolerating "already exists" errors from prior runs.
 *
 * @throws QueryException on any non-duplicate-key error.
 */
protected function safeIndex(Blueprint $table, array $columns, string $name): void
{
    try {
        $table->index($columns, $name);
    } catch (QueryException $e) {
        $message = $e->getMessage();
        $isDuplicate = str_contains($message, 'Duplicate key name')
            || str_contains($message, '1061')                  // MySQL
            || str_contains($message, 'already exists')         // PostgreSQL
            || str_contains($message, 'index already exists');  // SQLite

        if (! $isDuplicate) {
            throw $e;
        }
    }
}
```

**العقد:** الـ helper لا يبتلع أي خطأ غير المتوقع. لو ظهر `QueryException` لسبب آخر (مثلاً connection lost)، يُعاد.

---

## 4. العقد الممنوع (Forbidden Patterns)

❌ **ممنوع تماماً** في أي migration من هذا الـ feature:

```php
// ❌ DROP
Schema::dropIfExists('users');
$table->dropColumn('name');
$table->dropForeign('user_id');
DB::statement('DROP INDEX ...');  // (إلا داخل dropIndex الذي يحذف نفس ما أضافه up)

// ❌ DESTRUCTIVE
DB::table('users')->truncate();
User::truncate();
DB::statement('DELETE FROM users');

// ❌ MIGRATION DESTRUCTIVE
Artisan::call('migrate:fresh');
Artisan::call('migrate:refresh');

// ❌ SCHEMA MUTATION
$table->string('new_column');     // إضافة عمود
$table->renameColumn('a', 'b');   // تسمية
$table->unique('email');          // UNIQUE جديد على عمود فيه بيانات (قد يفشل)
```

✅ **مسموح** (فقط):

```php
$table->index(['col1', 'col2'], 'idx_name');
$table->dropIndex('idx_name');
DB::statement('SHOW INDEX ...');  // للتحقق فقط
```

---

## 5. عقد الـ Helper Class (اختياري)

> لا يُطلب. الـ migrations قد تكون self-contained لكل ملف. لكن لو رغب المطوّر، يمكن إنشاء:
> `app/Support/Migrations/SafeIndexMigration.php` (abstract) يُورث منه.

---

## 6. عقد التحقق (Verification Contract)

بعد كل migration، يجب أن يمر هذا الـ test (من spec § 9):

```php
test('count is unchanged after index migration', function () {
    $before = User::count();
    Artisan::call('migrate', ['--path' => 'database/migrations/2026_07_21_*']);
    expect(User::count())->toBe($before);
});
```

---

*آخر تحديث: 2026-07-21*
*الإصدار: 1.0.0*
