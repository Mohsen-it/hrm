# عقد محرك الحساب (Attendance Calculation Service)

**التاريخ:** 2026-07-16

---

## الواجهة (Interface)

### calculateAttendance(int $employeeId, DateTimeInterface $punchTime): AttendanceSession

**الغرض:** حساب الحضور لبصمة معينة

**التدفق الداخلي:**
1. تحديد الموظف من `users`
2. تحديد الفئة من `att_attemployee` (أو الإسناد التلقائي للافتراضية)
3. تحديد الجدول النشط:
   - البحث في `att_attschedule` (جدول الموظف الفردي)
   - إذا لم يُوجد، البحث في `att_groupschedule` (جدول الفئة)
4. تحديد المناوبة من `att_attshift`
5. تحديد التفاصيل من `att_shiftdetail` (حسب يوم الأسبوع)
6. تحديد الفاصل الزمني من `att_timeinterval`
7. تطبيق القواعد:
   - حساب التاخير (in_above_margin)
   - حساب الخروج المبكر (out_ahead_margin)
   - حساب الإvertime
   - حساب فترات الاستراحة
8. إنشاء/تحديث `AttendanceSession`

**المدخلات:**
- `employeeId`: معرف الموظف
- `punchTime`: وقت البصمة

**المخرجات:**
- `AttendanceSession`: جلسة الحضور مع الحالة المحسوبة

---

### resolveEmployeeGroup(int $employeeId): ?AttendanceGroup

**الغرض:** تحديد فئة الحضور للموظف

**التدفق:**
1. البحث في `att_attemployee` عن الموظف
2. إذا وُجد → إرجاع الفئة المرتبطة
3. إذا لم يُوجد:
   - البحث عن الفئة الافتراضية للشركة
   - إسناد الموظف لتلك الفئة تلقائياً
   - تسجيل الحادثة في السجلات
   - إرجاع الفئة الافتراضية

---

### resolveShiftForEmployee(int $employeeId, string $date): ?AttendanceShift

**الغرض:** تحديد مناوبة الموظف في تاريخ معين

**الأولوية:**
1. `att_attschedule` — جدول الموظف الفردي (overrides)
2. `att_groupschedule` — جدول الفئة

**التدفق:**
1. البحث في `att_attschedule` where employee_id = $employeeId AND start_date <= $date AND end_date >= $date
2. إذا وُجد → إرجاع المناوبة المرتبطة
3. البحث في `att_attemployee` لتحديد الفئة
4. البحث في `att_groupschedule` where group_id = $groupId AND start_date <= $date AND end_date >= $date
5. إذا وُجد → إرجاع المناوبة المرتبطة
6. إذا لم يُوجد شيء → null

---

### resolveTimeInterval(AttendanceShift $shift, string $date): ?TimeInterval

**الغرض:** تحديد الفاصل الزمني من المناوبة

**التدفق:**
1. تحديد يوم الأسبوع من التاريخ
2. البحث في `att_shiftdetail` where shift_id = $shift->id AND day_index = $dayOfWeek
3. إرجاع TimeInterval المرتبط

---

### determineStatus(AttendanceSession $session, TimeInterval $interval): string

**الغرض:** تحديد حالة الحضور

**الحالات الممكنة:**
- `present` — حاضر
- `late` — متأخر
- `early_leave` — خرج مبكراً
- `absent` — غائب
- `holiday` — عطلة
- `vacation` — إجازة
- `rest` — راحة
- `missing_punch` — ناقص بصمة

**التدفق:**
1. التحقق من وجود عطلة/إجازة/راحة
2. إذا لا توجد بصمة → `absent`
3. إذا توجد بصمة واحدة فقط → `missing_punch`
4. حساب التاخير والخروج المبكر
5. تحديد الحالة النهائية

---

### calculateLateMinutes(DateTimeInterface $actual, DateTimeInterface $expected, TimeInterval $interval): int

**الغرض:** حساب دقائق التاخير

**المعادلة:**
```
تأخير = max(0, (actual - expected) - in_above_margin)
```

**الحيز:** `in_above_margin` من TimeInterval

---

### calculateEarlyLeaveMinutes(DateTimeInterface $actual, DateTimeInterface $expected, TimeInterval $interval): int

**الغرض:** حساب دقائق الخروج المبكر

**المعادلة:**
```
خروج مبكر = max(0, (expected - actual) - out_ahead_margin)
```

**الحيز:** `out_ahead_margin` من TimeInterval

---

### calculateOvertimeMinutes(DateTimeInterface $actual, DateTimeInterface $expected, TimeInterval $interval): int

**الغرض:** حساب دقائق الإvertime

**المعادلة:**
```
إvertime = max(0, actual - expected)
```

**الشرط:** `enable_overtime` في TimeInterval يجب أن يكون true

---

### calculateBreakMinutes(AttendanceSession $session): int

**الغرض:** حساب فترات الاستراحة

**التدفق:**
1. جلب TimeInterval من الجلسة
2. جلب فترات الاستراحة المرتبطة
3. حساب المدة الإجمالية لفترات الاستراحة الفعلية

---

## التبعيات

- `Modules\Users\Models\User`
- `Modules\Shifts\Models\Shift` (للتاريخ الحالي فقط)
- `Modules\Attendance\Models\AttendanceSession`
- `Modules\Attendance\Models\RawAttendanceLog`
- `Modules\Attendance\Models\DailyAttendanceSummary`
- الموديلات الجديدة: AttendanceGroup, AttendanceEmployee, GroupSchedule, AttendanceShift, ShiftDetail, TimeInterval, BreakTime

---

*آخر تحديث: 2026-07-16*
