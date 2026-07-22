# 008 — Database Indexing & Query Performance — نموذج البيانات (Data Model)

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-21
**المرجع:** [spec.md](./spec.md) | [research.md](./research.md)

> **⚠️ ملاحظة حاسمة:** هذا الـ feature **لا يُعدّل أي عمود أو صف أو جدول موجود**. كل ما يلي هو **DDL لإضافة indexes فقط** (`CREATE INDEX ... ON ...`). لا `ALTER TABLE ... DROP COLUMN`، لا `TRUNCATE`، لا `DELETE`.

---

## 0. الرموز المستخدمة (Legend)

| الرمز | المعنى |
|-------|--------|
| 🔵 | Index جديد يُضاف (الإجراء) |
| ✅ | Index موجود مسبقاً (لا تغيير) |
| ❌ | لا index ولا حاجة له (الجدول صغير) |
| 🔒 | قيد صارم: ممنوع DROP/TRUNCATE/DELETE |

---

## 1. خريطة شاملة (Comprehensive Index Map)

### 1.1 جدول `users` (المركزي)

**حالة الفهرسة الحالية:**
- ✅ `id` (PK)
- ✅ `email` (UNIQUE من Laravel)
- ✅ `subordination_id` (من migration `2026_07_20_100100`)
- ✅ `*_id` (FK على `company_id`, `branch_id`, `department_id`, `position_id`, `grade_id`, `shift_id`, `manager_id` — كلها auto-indexed)

**Indexes الجديدة (في `database/migrations/2026_07_21_000001_add_users_composite_indexes.php`):**

| 🔵 الاسم | الأعمدة | يُحسّن |
|----------|---------|--------|
| `idx_users_company_status_active` | `(company_id, status, is_active_employee)` | `UserRepository::getAll` مع فلتر `company_id` |
| `idx_users_branch_status` | `(branch_id, status)` | `UserRepository::getByBranch` + filter `branch_id` |
| `idx_users_department_status` | `(department_id, status)` | `UserRepository::getByDepartment` |
| `idx_users_position_status` | `(position_id, status)` | filter `position_id` |
| `idx_users_grade_status` | `(grade_id, status)` | `UserRepository::getByGrade` |
| `idx_users_employment_type` | `(employment_type)` | تقارير التوظيف |
| `idx_users_hire_date` | `(hire_date)` | التقارير الزمنية للتوظيف |

**لا يُضاف:** standalone على `is_active_employee` (booleans → composite فقط per D-13).

### 1.2 جدول `attendance_sessions`

**Indexes الموجودة:** 7 indexes شاملة من `Modules/Attendance/database/migrations/2024_01_01_000010_create_attendance_sessions_table.php` + 2 من `database/migrations/2024_01_01_000090_add_performance_indexes.php`.

**Indexes الجديدة (في `database/migrations/2026_07_21_000002_add_attendance_query_indexes.php`):**

| 🔵 الاسم | الأعمدة | يُحسّن |
|----------|---------|--------|
| `idx_att_sessions_user_date_status` | `(user_id, attendance_date, status)` | تقرير حضور موظف بمدى مع فلتر حالة |
| `idx_att_sessions_date_status_type` | `(attendance_date, status, session_type)` | إحصائيات يومية |
| `idx_att_sessions_created_by` | `(created_by, attendance_date)` | تتبع من أنشأ |
| `idx_att_sessions_checkout` | `(check_out_at, status)` | جلسات بدون check-out (نشطة) |

### 1.3 جدول `daily_attendance_summaries`

**Indexes الجديدة:**

| 🔵 الاسم | الأعمدة | يُحسّن |
|----------|---------|--------|
| `idx_daily_summaries_date_calculated` | `(summary_date, calculated_at)` | تحديث batch + تقارير |
| `idx_daily_summaries_status_date` | `(status, summary_date)` | تصفية (غائب/حاضر) |

### 1.4 جدول `raw_attendance_logs`

**Indexes الجديدة:**

| 🔵 الاسم | الأعمدة | يُحسّن |
|----------|---------|--------|
| `idx_raw_logs_dedup` | `(device_id, device_user_id, punch_time)` | منع التكرار في الاستيراد |
| `idx_raw_logs_user_time` | `(device_user_id, punch_time)` | بحث بـ device-side id |
| `idx_raw_logs_processed_punch` | `(processed, punch_time)` | معالجة السجلات غير المعالجة |

**ملاحظة:** بعض هذه موجودة كـ (user_id, punch_time) و (processed, punch_time) لكن الـ migration الجديد يعيد تأكيدها بأسماء صريحة. `try/catch` سيتجاهل التعارض.

### 1.5 جدول `iclock_transaction`

**Indexes الجديدة (في نفس ملف `2026_07_21_000002`):**

| 🔵 الاسم | الأعمدة | يُحسّن |
|----------|---------|--------|
| `idx_iclock_emp_punch` | `(emp_id, punch_time)` | sync (موجود مسبقاً في `2026_07_16_000003_optimize_shift_indexes` — try/catch يتجاهل) |
| `idx_iclock_punch_time` | `(punch_time)` | تقارير زمنية |

### 1.6 جدول `schedule_entries`

**Indexes الجديدة (في `Modules/Shifts/database/migrations/2026_07_21_000001_add_schedule_query_indexes.php`):**

| 🔵 الاسم | الأعمدة | يُحسّن |
|----------|---------|--------|
| `idx_schedule_entries_date_emp` | `(date, employee_id, day_status)` | استعلام التقويم |
| `idx_schedule_entries_period_status` | `(schedule_period_id, day_status)` | إحصائيات فترة |

### 1.7 جدول `user_vacation_requests`

**Indexes الجديدة (في `Modules/Users/database/migrations/2026_07_21_000001_add_vacation_query_indexes.php`):**

| 🔵 الاسم | الأعمدة | يُحسّن |
|----------|---------|--------|
| `idx_vacation_req_status_start` | `(status, start_date, end_date)` | "الطلبات المعلقة الآن" |
| `idx_vacation_req_user_dates` | `(user_id, start_date)` | تاريخ إجازات موظف |
| `idx_vacation_req_decided` | `(decided_at, status)` | تتبع من اتخذ القرار |

### 1.8 جدول `user_vacation_balance_transactions`

**Indexes الجديدة:**

| 🔵 الاسم | الأعمدة | يُحسّن |
|----------|---------|--------|
| `idx_vacation_bal_tx_date` | `(user_id, vacation_type_id, created_at)` | سجل رصيد |

### 1.9 جدول `fingerprint_devices`

**Indexes الجديدة (في `Modules/FingerprintDevices/database/migrations/2026_07_21_000002_add_device_query_indexes.php`):**

| 🔵 الاسم | الأعمدة | يُحسّن |
|----------|---------|--------|
| `idx_devices_company_branch` | `(company_id, branch_id, status)` | قائمة الأجهزة |
| `idx_devices_last_pushed` | `(last_pushed_at, status)` | "أجهزة لم تتزامن" |

### 1.10 جدول `device_sync_logs`

**Indexes الجديدة:**

| 🔵 الاسم | الأعمدة | يُحسّن |
|----------|---------|--------|
| `idx_sync_logs_device_date` | `(device_id, started_at)` | تاريخ المزامنة |
| `idx_sync_logs_status_date` | `(status, started_at)` | نجاح/فشل |

### 1.11 جدول `attendance_integration_audit_logs`

**Indexes الجديدة (في `Modules/AttendanceIntegration/database/migrations/2026_07_21_000005_add_audit_logs_indexes.php`):**

| 🔵 الاسم | الأعمدة | يُحسّن |
|----------|---------|--------|
| `idx_audit_correlation` | `(correlation_id, occurred_at)` | تتبع التدفق |
| `idx_audit_actor` | `(actor_id, occurred_at)` | من فعل ماذا |

### 1.12 جدول `audit_logs` (Shifts)

**Indexes الجديدة (في `database/migrations/2026_07_21_000003_add_general_audit_indexes.php`):**

| 🔵 الاسم | الأعمدة | يُحسّن |
|----------|---------|--------|
| `idx_audit_action_date` | `(action, created_at)` | بحث حسب نوع الإجراء |

### 1.13 جدول `holidays`

**Indexes الجديدة (في `Modules/Holidays/database/migrations/2026_07_21_000001_add_holiday_query_index.php`):**

| 🔵 الاسم | الأعمدة | يُحسّن |
|----------|---------|--------|
| `idx_holidays_date_active` | `(start_date, end_date, is_active)` | تقويم العطلات |

> ملاحظة: `date` و `is_recurring` و `category` مفهرسة مسبقاً. الـ new index يدعم البحث بنطاق زمني.

### 1.14 جدول `subordinations`

**Indexes الجديدة (في `Modules/Subordinations/database/migrations/2026_07_21_000001_add_subordination_query_index.php`):**

| 🔵 الاسم | الأعمدة | يُحسّن |
|----------|---------|--------|
| `idx_subordinations_status_order` | `(status, sort_order)` | القائمة المنسدلة (ordered+active) |

### 1.15 جدول `att_hours_tracking`

**Indexes الجديدة:**

| 🔵 الاسم | الأعمدة | يُحسّن |
|----------|---------|--------|
| `idx_hours_user_date` | `(user_id, period_start, period_end)` | حساب ساعات |
| `idx_hours_employee_category` | `(employee_id, shift_category_id, period_start)` | تفصيل مناوبة |

### 1.16 جدول `att_rotation_assignments`

**Indexes الجديدة:**

| 🔵 الاسم | الأعمدة | يُحسّن |
|----------|---------|--------|
| `idx_rotation_assign_emp_dates` | `(employee_id, start_date, end_date)` | "مجموعة موظف في تاريخ" |

### 1.17 جدول `att_employee_shift_categories`

**Indexes الجديدة:**

| 🔵 الاسم | الأعمدة | يُحسّن |
|----------|---------|--------|
| `idx_esc_active` | `(is_active, employee_id)` | الفلتر الافتراضي |

### 1.18 جداول بدون تغيير (No Change)

| الجدول | السبب |
|--------|-------|
| `companies`, `branches`, `departments`, `positions`, `grades`, `zones` | حجم صغير (< 1000 سجل) |
| `settings` | يُمستدعى عبر cache |
| `schedule_periods` | indexes كافية (unique + status) |
| `fingerprint_device_types` | جدول مرجعي صغير |
| `att_shift_categories` | مرجعي |
| `att_time_schedules` | مرجعي |
| `attendance_codes`، `pay_codes`، `break_times`، `time_intervals`، `time_interval_break_time`، `shift_details`، `attendance_groups`, `attendance_employees`, `group_schedules`, `group_policies`, `department_policies`, `department_schedules`, `employee_schedules`, `temporary_schedules`, `user_fingerprints`, `user_vacation_balances`, `vacation_types` | مرجعية / أحجام صغيرة |

---

## 2. DDL Contracts (مختصر)

### 2.1 مثال: `2026_07_21_000001_add_users_composite_indexes.php`

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $this->safeIndex($table, ['company_id', 'status', 'is_active_employee'], 'idx_users_company_status_active');
        $this->safeIndex($table, ['branch_id', 'status'], 'idx_users_branch_status');
        $this->safeIndex($table, ['department_id', 'status'], 'idx_users_department_status');
        $this->safeIndex($table, ['position_id', 'status'], 'idx_users_position_status');
        $this->safeIndex($table, ['grade_id', 'status'], 'idx_users_grade_status');
        $this->safeIndex($table, ['employment_type'], 'idx_users_employment_type');
        $this->safeIndex($table, ['hire_date'], 'idx_users_hire_date');
    });
}

protected function safeIndex(Blueprint $table, array $columns, string $name): void
{
    try {
        $table->index($columns, $name);
    } catch (QueryException $e) {
        // Duplicate key name → safe to ignore
        if (str_contains($e->getMessage(), 'Duplicate key name')
            || str_contains($e->getMessage(), '1061')
            || str_contains($e->getMessage(), 'already exists')) {
            return;
        }
        throw $e;
    }
}
```

### 2.2 مثال على `down()` (نفس الفكرة لكل migration)

```php
public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $this->safeDropIndex($table, 'idx_users_company_status_active');
        // ... كل الباقي
    });
}
```

> **🔒 ضمانة حرجة:** `down()` يحذف الـ indexes فقط. لا يحذف أعمدة أو سجلات. لو شغّل المستخدم `migrate:rollback` ثم `migrate`، تعود الفهارس — والبيانات سليمة 100%.

---

## 3. State Transitions

لا يوجد. هذا الـ feature لا يُدخل حالات جديدة (لا enum جديد، لا status جديد). هو تحسين أداء فقط.

---

## 4. Validation Rules (المرتبطة)

لا validation rules جديدة — لا نماذج جديدة ولا FormRequests جديدة ولا endpoints جديدة. كل التغييرات على مستوى DBO.

---

## 5. ملخص الأثر على الـ Schema

| النوع | العدد |
|-------|-------|
| جداول يتغيّر شيء فيها (إضافة index فقط) | **18 جدول** |
| جداول لا تتغيّر | **~50 جدول** |
| أعمدة جديدة | **0** (ممنوع BR-13) |
| أعمدة محذوفة | **0** (ممنوع BR-13) |
| indexes جديدة | **~35 index** |
| indexes محذوفة | **0** (down() يحذف نفس ما أضافه up()) |
| سجلات جديدة (في seed) | **0** |
| سجلات محذوفة | **0** (ممنوع BR-13) |
| Foreign keys جديدة | **0** |
| Foreign keys محذوفة | **0** |

**مجموع الأثر:** schema يكبر بـ ~10-25% (مساحة indexes)، البيانات ثابتة 100%، الـ API ثابت 100%.

---

*آخر تحديث: 2026-07-21*
*الإصدار: 1.0.0*
