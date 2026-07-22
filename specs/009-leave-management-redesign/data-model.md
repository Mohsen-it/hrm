# نموذج البيانات - إعادة تصميم وحدة إدارة الإجازات

**التاريخ:** 2026-07-22
**الحالة:** Phase 1 Complete

---

## 1. الكيانات الرئيسية (Core Entities)

### 1.1 leave_requests (طلبات الإجازة)

الجدول الرئيسي لحفظ طلبات الإجازة.

| الحقل | النوع | الوصف | القواعد |
|-------|-------|-------|---------|
| `id` | BIGINT UNSIGNED | المفتاح الرئيسي | PK, AUTO_INCREMENT |
| `user_id` | BIGINT UNSIGNED | مقدم الطلب | FK → users.id, NOT NULL |
| `leave_type_id` | BIGINT UNSIGNED | نوع الإجازة | FK → leave_types.id, NOT NULL |
| `request_number` | VARCHAR(20) | رقم الطلب الفريد | UNIQUE, NOT NULL |
| `start_date` | DATE | تاريخ البداية | NOT NULL |
| `end_date` | DATE | تاريخ النهاية | NOT NULL, >= start_date |
| `days_count` | DECIMAL(5,2) | عدد أيام العمل | NOT NULL, > 0 |
| `reason` | TEXT | سبب الإجازة | NOT NULL |
| `notes` | TEXT NULL | ملاحظات إضافية | NULLABLE |
| `contact_phone_during_leave` | VARCHAR(20) NULL | رقم الجوال أثناء الإجازة | NULLABLE |
| `status` | ENUM | حالة الطلب | NOT NULL, DEFAULT 'draft' |
| `current_step` | INT | الخطوة الحالية في الموافقات | DEFAULT 1 |
| `rejected_reason` | TEXT NULL | سبب الرفض | NULLABLE |
| `is_cancellation` | BOOLEAN | هل هو طلب إلغاء | DEFAULT false |
| `created_at` | TIMESTAMP | تاريخ الإنشاء | AUTO |
| `updated_at` | TIMESTAMP | آخر تحديث | AUTO |

**الحالات الممكنة (status):**
```
draft, submitted, manager_approved, hr_approved, gm_approved,
rejected, returned_for_correction, cancelled, pending_cancellation
```

**الفهارس:**
- `idx_leave_requests_user_id` ON (user_id)
- `idx_leave_requests_status` ON (status)
- `idx_leave_requests_start_date` ON (start_date)
- `idx_leave_requests_end_date` ON (end_date)
- `idx_leave_requests_request_number` ON (request_number) UNIQUE

---

### 1.2 leave_types (أنواع الإجازات)

تعريف أنواع الإجازات المتاحة.

| الحقل | النوع | الوصف | القواعد |
|-------|-------|-------|---------|
| `id` | BIGINT UNSIGNED | المفتاح الرئيسي | PK, AUTO_INCREMENT |
| `name` | VARCHAR(100) | اسم نوع الإجازة | NOT NULL, UNIQUE |
| `name_en` | VARCHAR(100) | الاسم بالإنجليزية | NULLABLE |
| `code` | VARCHAR(20) | رمز نوع الإجازة | UNIQUE, NOT NULL |
| `description` | TEXT NULL | الوصف | NULLABLE |
| `is_paid` | BOOLEAN | هل هي مؤجرة | DEFAULT true |
| `is_active` | BOOLEAN | هل هي نشطة | DEFAULT true |
| `max_days_per_request` | INT NULL | الحد الأقصى لكل طلب | NULLABLE |
| `gender_restriction` | ENUM NULL | قيود الجنس | NULLABLE ('male', 'female', null) |
| `created_at` | TIMESTAMP | تاريخ الإنشاء | AUTO |
| `updated_at` | TIMESTAMP | آخر تحديث | AUTO |

**الأنواع الافتراضية:**
- Annual Leave (إجازة سنوية) - مُؤجرة
- Sick Leave (إجازة مرضية) - مُؤجرة
- Emergency Leave (إجازة طارئة) - مُؤجرة
- Maternity Leave (إجازة وضع) - مُؤجرة - إناث فقط
- Unpaid Leave (إجازة غير مؤجرة) - غير مُؤجرة

---

### 1.3 leave_balances (أرصدة الإجازات)

رصيد الإجازات لكل موظف لكل نوع إجازة لكل سنة.

| الحقل | النوع | الوصف | القواعد |
|-------|-------|-------|---------|
| `id` | BIGINT UNSIGNED | المفتاح الرئيسي | PK, AUTO_INCREMENT |
| `user_id` | BIGINT UNSIGNED | الموظف | FK → users.id, NOT NULL |
| `leave_type_id` | BIGINT UNSIGNED | نوع الإجازة | FK → leave_types.id, NOT NULL |
| `year` | YEAR | السنة | NOT NULL |
| `total_days` | DECIMAL(5,2) | إجمالي الأيام المستحقة | NOT NULL, >= 0 |
| `used_days` | DECIMAL(5,2) | الأيام المستخدمة | DEFAULT 0, >= 0 |
| `remaining_days` | DECIMAL(5,2) | الأيام المتبقية | GENERATED: total_days - used_days |
| `created_at` | TIMESTAMP | تاريخ الإنشاء | AUTO |
| `updated_at` | TIMESTAMP | آخر تحديث | AUTO |

**القيود:**
- UNIQUE constraint ON (user_id, leave_type_id, year)
- CHECK (used_days <= total_days)
- CHECK (used_days >= 0)

**الفهارس:**
- `idx_leave_balances_user_year` ON (user_id, year)
- `idx_leave_balances_lookup` ON (user_id, leave_type_id, year) UNIQUE

---

### 1.4 leave_approvals (سلسلة الموافقات)

خطوات الموافقة لكل طلب إجازة.

| الحقل | النوع | الوصف | القواعد |
|-------|-------|-------|---------|
| `id` | BIGINT UNSIGNED | المفتاح الرئيسي | PK, AUTO_INCREMENT |
| `leave_request_id` | BIGINT UNSIGNED | طلب الإجازة | FK → leave_requests.id, NOT NULL |
| `approver_id` | BIGINT UNSIGNED | المعتمد | FK → users.id, NOT NULL |
| `step` | INT | رقم الخطوة | NOT NULL |
| `status` | ENUM | حالة الخطوة | NOT NULL, DEFAULT 'pending' |
| `notes` | TEXT NULL | ملاحظات المعتمد | NULLABLE |
| `processed_at` | TIMESTAMP NULL | تاريخ المعالجة | NULLABLE |
| `created_at` | TIMESTAMP | تاريخ الإنشاء | AUTO |
| `updated_at` | TIMESTAMP | آخر تحديث | AUTO |

**حالات الخطوة (status):**
```
pending, approved, rejected, returned, skipped
```

**الفهارس:**
- `idx_leave_approvals_request` ON (leave_request_id)
- `idx_leave_approvals_approver` ON (approver_id)
- `idx_leave_approvals_pending` ON (approver_id, status) WHERE status = 'pending'

---

### 1.5 leave_attachments (المرفقات)

الملفات المرفقة بطلبات الإجازة.

| الحقل | النوع | الوصف | القواعد |
|-------|-------|-------|---------|
| `id` | BIGINT UNSIGNED | المفتاح الرئيسي | PK, AUTO_INCREMENT |
| `leave_request_id` | BIGINT UNSIGNED | طلب الإجازة | FK → leave_requests.id, NOT NULL |
| `user_id` | BIGINT UNSIGNED | رافع الملف | FK → users.id, NOT NULL |
| `file_name` | VARCHAR(255) | اسم الملف الأصلي | NOT NULL |
| `file_path` | VARCHAR(500) | مسار الملف | NOT NULL |
| `file_type` | VARCHAR(50) | نوع MIME | NOT NULL |
| `file_size` | INT UNSIGNED | حجم الملف بالبايت | NOT NULL |
| `created_at` | TIMESTAMP | تاريخ الإنشاء | AUTO |

**القيود:**
- file_size <= 10485760 (10MB)
- file_type IN ('application/pdf', 'image/png', 'image/jpeg', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')

---

### 1.6 leave_balance_adjustments (تعديلات الرصيد)

سجل التعديلات اليدوية لأرصدة الإجازات.

| الحقل | النوع | الوصف | القواعد |
|-------|-------|-------|---------|
| `id` | BIGINT UNSIGNED | المفتاح الرئيسي | PK, AUTO_INCREMENT |
| `leave_balance_id` | BIGINT UNSIGNED | سجل الرصيد | FK → leave_balances.id, NOT NULL |
| `user_id` | BIGINT UNSIGNED | الموظف | FK → users.id, NOT NULL |
| `admin_id` | BIGINT UNSIGNED | المدير الذي قام بالتعديل | FK → users.id, NOT NULL |
| `old_total_days` | DECIMAL(5,2) | الإجمالي القديم | NOT NULL |
| `new_total_days` | DECIMAL(5,2) | الإجمالي الجديد | NOT NULL |
| `old_used_days` | DECIMAL(5,2) | الاستهلاك القديم | NOT NULL |
| `new_used_days` | DECIMAL(5,2) | الاستهلاك الجديد | NOT NULL |
| `reason` | TEXT | سبب التعديل | NOT NULL |
| `created_at` | TIMESTAMP | تاريخ الإنشاء | AUTO |

---

### 1.7 tenure_leave_config (إعدادات سنوات الخdama)

القاعدة المرجعية لحساب الرصيد حسب سنوات الخدمة.

| الحقل | النوع | الوصف | القواعد |
|-------|-------|-------|---------|
| `id` | BIGINT UNSIGNED | المفتاح الرئيسي | PK, AUTO_INCREMENT |
| `min_years` | INT | الحد الأدنى للسنوات | NOT NULL |
| `max_years` | INT NULL | الحد الأقصى للسنوات | NULLABLE (null = لا حد) |
| `days` | INT | عدد الأيام المستحقة | NOT NULL |
| `created_at` | TIMESTAMP | تاريخ الإنشاء | AUTO |
| `updated_at` | TIMESTAMP | آخر تحديث | AUTO |

**القيم الافتراضية:**
```sql
INSERT INTO tenure_leave_config (min_years, max_years, days) VALUES
(1, 5, 15),
(6, 10, 21),
(11, 15, 26),
(16, NULL, 30);
```

---

## 2. الكيانات المرتبطة (Related Entities)

### 2.1 users (المستخدمون)

الجدول الموجود يحتوي على بيانات الموظفين.

| الحقل المستخدم | الوصف |
|----------------|-------|
| `id` | المفتاح الرئيسي |
| `name` | اسم الموظف |
| `email` | البريد الإلكتروني |
| `role` | الدور (employee, department_manager, development_manager, general_manager, admin) |
| `department_id` | القسم |
| `hire_date` | تاريخ التعيين |
| `gender` | الجنس (m/f) |

### 2.2 departments (الأقسام)

| الحقل المستخدم | الوصف |
|----------------|-------|
| `id` | المفتاح الرئيسي |
| `name` | اسم القسم |
| `manager_id` | مدير القسم |

---

## 3. العلاقات (Relationships)

```
User (1) ──┬── (N) LeaveRequest
            ├── (N) LeaveBalance
            ├── (N) LeaveApproval (as approver)
            └── (N) LeaveBalanceAdjustment

LeaveType (1) ──┬── (N) LeaveRequest
                 └── (N) LeaveBalance

LeaveRequest (1) ──┬── (N) LeaveApproval
                    └── (N) LeaveAttachment

LeaveBalance (1) ── (N) LeaveBalanceAdjustment
```

---

## 4. قواعد الأعمال في نموذج البيانات (Data Business Rules)

### 4.1 حساب أيام العمل

```php
// حساب أيام العمل باستثناء الجمعة والسبت
function calculateWorkingDays($startDate, $endDate) {
    $days = 0;
    $current = $startDate->copy();
    
    while ($current->lte($endDate)) {
        if (!$current->isFriday() && !$current->isSaturday()) {
            $days++;
        }
        $current->addDay();
    }
    
    return $days;
}
```

### 4.2 توليد رقم الطلب

```php
// صيغة: LR{YYYYMMDD}-{XXXX}
function generateRequestNumber() {
    $date = now()->format('Ymd');
    $random = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    return "LR{$date}-{$random}";
}
```

### 4.3 التحقق من التداخل

```php
// التحقق من عدم تداخل طلب جديد مع طلبات موجودة
function checkOverlap($userId, $startDate, $endDate, $excludeId = null) {
    return LeaveRequest::where('user_id', $userId)
        ->whereNotIn('status', ['rejected', 'cancelled'])
        ->where(function ($query) use ($startDate, $endDate) {
            $query->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q) use ($startDate, $endDate) {
                      $q->where('start_date', '<=', $startDate)
                        ->where('end_date', '>=', $endDate);
                  });
        })
        ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
        ->exists();
}
```

---

## 5. Migrations المطلوبة (Required Migrations)

### 5.1 جداول جديدة

1. `create_leave_requests_table`
2. `create_leave_types_table`
3. `create_leave_balances_table`
4. `create_leave_approvals_table`
5. `create_leave_attachments_table`
6. `create_leave_balance_adjustments_table`
7. `create_tenure_leave_config_table`

### 5.2 جداول موجودة (تعديل)

1. `users` - إضافة أعمدة مطلوبة إن وجدت
2. `departments` - التأكد من وجود manager_id

---

*آخر تحديث: 2026-07-22*
