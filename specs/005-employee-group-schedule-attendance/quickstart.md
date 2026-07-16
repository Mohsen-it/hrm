# الدليل السريع - Employee Group Schedule Attendance

**التاريخ:** 2026-07-16

---

## المتطلبات المسبقة

1. Laravel 13 مثبت
2. قاعدة البيانات SQLite (dev) أو PostgreSQL (prod)
3. الموديلات الأساسية موجودة (Users, Companies, Departments)
4. `php artisan migrate` تم تشغيله

---

## الخطوة 1: إعداد قاعدة البيانات

```bash
# تشغيل الترحيلات الجديدة
php artisan migrate

# تشغيل Seeders للبيانات الافتراضية
php artisan db:seed --class=AttendanceGroupSeeder
php artisan db:seed --class=PayCodeSeeder
php artisan db:seed --class=AttCodeSeeder
```

---

## الخطوة 2: اختبار فئات الحضور

### إنشاء فئة
```bash
php artisan tinker
```

```php
use Modules\Attendance\Models\AttendanceGroup;

$group = AttendanceGroup::create([
    'code' => 'SHIFT-AM',
    'name' => 'مناوبة صباحية',
    'company_id' => 1,
    'status' => 1,
]);

echo "تم إنشاء الفئة: {$group->name}";
```

### تعيين موظف للفئة
```php
use Modules\Attendance\Models\AttendanceEmployee;

$employee = AttendanceEmployee::create([
    'emp_id' => 101,
    'group_id' => $group->id,
    'enable_attendance' => true,
    'enable_schedule' => true,
    'enable_overtime' => false,
    'enable_holiday' => true,
    'enable_compensatory' => false,
    'status' => 1,
]);

echo "تم تعيين الموظف للفئة";
```

---

## الخطوة 3: اختبار المناوبات

### إنشاء مناوبة
```php
use Modules\Attendance\Models\AttendanceShift;
use Modules\Attendance\Models\ShiftDetail;
use Modules\Attendance\Models\TimeInterval;

// إنشاء فاصل زمني
$timeInterval = TimeInterval::create([
    'alias' => 'فترة صباحية',
    'in_time' => '06:00',
    'duration' => 480,
    'in_ahead_margin' => 15,
    'in_above_margin' => 30,
    'out_ahead_margin' => 0,
    'out_above_margin' => 15,
    'day_change' => '00:00',
    'enable_overtime' => true,
    'company_id' => 1,
]);

// إنشاء مناوبة
$shift = AttendanceShift::create([
    'alias' => 'صباحية',
    'cycle_unit' => 1,
    'shift_cycle' => 1,
    'work_weekend' => false,
    'weekend_type' => 0,
    'work_day_off' => false,
    'day_off_type' => 0,
    'auto_shift' => 0,
    'enable_ot_rule' => false,
    'frequency' => 1,
    'company_id' => 1,
]);

// إنشاء تفاصيل المناوبة (7 أيام)
foreach (range(0, 6) as $dayIndex) {
    ShiftDetail::create([
        'shift_id' => $shift->id,
        'time_interval_id' => $timeInterval->id,
        'day_index' => $dayIndex,
        'in_time' => '06:00',
        'out_time' => '14:00',
    ]);
}

echo "تم إنشاء المناوبة مع التفاصيل";
```

---

## الخطوة 4: اختبار جداول الفئات

### إنشاء جدول للفئة
```php
use Modules\Attendance\Models\GroupSchedule;

$schedule = GroupSchedule::create([
    'group_id' => $group->id,
    'shift_id' => $shift->id,
    'start_date' => '2026-07-01',
    'end_date' => '2026-12-31',
    'status' => 1,
]);

echo "تم إنشاء الجدول للفئة";
```

---

## الخطوة 5: اختبار محرك الحساب

### محاكاة بصمة
```php
use Modules\Attendance\Services\AttendanceCalculationService;

$calculationService = app(AttendanceCalculationService::class);

// محاكاة بصمة دخول في 06:10 (10 دقائق تاخير)
$punchTime = now()->setTime(6, 10, 0);
$session = $calculationService->calculateAttendance(101, $punchTime);

echo "حالة الحضور: {$session->status}";
echo "دقائق التاخير: {$session->late_minutes}";
```

### التحقق من النتيجة المتوقعة
- الحالة: `late` (لأن الوقت الفعلي 06:10 والمتوقع 06:00 + حيز 30 دقيقة)
- التاخير: 0 (لأن الفرق 10 دقائق وأقصى حيز 30 دقيقة)

### محاكاة بصمة خروج
```php
$checkOutTime = now()->setTime(14, 5, 0);
$session = $calculationService->checkOut(101, $checkOutTime);

echo "ساعات العمل: {$session->work_minutes} دقيقة";
echo "خروج مبكر: {$session->early_leave_minutes} دقيقة";
```

---

## الخطوة 6: اختبار الواجهة

```bash
# تشغيل الخادم المحلي
php artisan serve

# فتح المتصفح
# http://localhost:8000/attendance/groups
# http://localhost:8000/attendance/shifts
# http://localhost:8000/attendance/group-schedules
# http://localhost:8000/attendance/time-intervals
```

---

## سيناريوهات الاختبار

### السيناريو 1: حضور طبيعي
1. الموظف معيّن للفئة الافتراضية
2. الفئة لها جدول مناوبة صباحية (06:00 - 14:00)
3. البصمة تصل في 05:55
4. **النتيجة:** حاضر، بدون تاخير

### السيناريو 2: تأخير
1. البصمة تصل في 06:40
2. الحيز: 30 دقيقة
3. **النتيجة:** متأخر، 10 دقائق تأخير

### السيناريو 3: خروج مبكر
1. البصمة تصل في 13:50
2. الحيز: 15 دقيقة
3. **النتيجة:** خرج مبكراً، 5 دقائق خروج مبكر

### السيناريو 4: بدون فئة
1. الموظف ليس له فئة حضور
2. البصمة تصل
3. **النتيجة:** إسناد تلقائي للفئة الافتراضية + احتساب عادي

### السيناريو 5: مناوبة مؤقتة
1. الموظف في فئة صباحية
2. اليوم له مناوبة مؤقتة مسائية
3. **النتيجة:** يتم استخدام المنوبة المؤقتة (أولوية أعلى)

---

## أوامر مفيدة

```bash
# مسح الكاش
php artisan cache:clear

# إعادة تشغيل الترحيلات
php artisan migrate:refresh

# تشغيل الاختبارات
php artisan test --filter=AttendanceGroup

# تنظيف الكود
php artisan pint
```

---

*آخر تحديث: 2026-07-16*
