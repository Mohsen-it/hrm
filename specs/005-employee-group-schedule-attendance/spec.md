# تسجيل الموظفين و斐نادهم لفئات الحضور ثم斐ناد الفئات لجداول - المواصفات

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-16
**الحالة:** مسودة

---

## نظرة عامة

هذا النظام يغطي three محاور رئيسية:
1. **تسجيل الموظفين و斐نادهم لفئات الحضور** — إنشاء فئات حضور وربط الموظفين بها مع تحديد الصلاحيات (حضور، جدول، إvertime، عطل)
2. **斐ناد الفئات لجداول المناوبة** — ربط كل فئة بجدول مناوبة محدد مع تحديد الفترة الزمنية
3. **نظام الحساب** — كيف يتم احتساب الحضور والغياب والتاخير والخروج المبكر بناءً على البصمات والفئات والجداول

---

## الفروض (Assumptions)

1. النظام الحالي (`Modules/Attendance`) يعمل بنموذج مبسط: `AttendanceSession` + `DailyAttendanceSummary` + `RawAttendanceLog` — سنحافظ على هذا النموذج ونوسّعه بدلاً من حذفه
2. جداول `att_*` الأصلية من SQL dump تمثل النظام الكامل — سنقوم بإنشاء موديلات Laravel جديدة تمثل هذه الجداول
3. جدول `users` هو سجل الموظفين المركزي (لا يوجد module منفصل للموظفين)
4. الصلاحيات تستخدم Spatie Permission
5. اللغة العربية هي اللغة الأساسية

---

## التوضيحات (Clarifications)

### Session 2026-07-16
- Q: ماذا يحدث عند وصول بصمة لموظف بدون فئة حضور؟ → A: الإسناد التلقائي للفئة الافتراضية للشركة ثم احتساب الحضور بشكل طبيعي
- Q: كيف يتم الاحتساب — فوري لكل بصمة أم يومي أم两者 معاً؟ → A: احتساب فوري عند كل بصمة + مراجعة يومية شاملة للتصحيح
- Q: متى يتم تعيين الموظف لفئة الحضور؟ → A: عند الإنشاء (اختياري) + عند التعديل (يمكن التغيير مع تحديد التاريخ)
- Q: ما هي العلاقة بين RawAttendanceLogService و AttendanceCalculationService و DailyAttendanceSummaryService؟ → A: مكملة — كل خدمة مسؤولة عن جزء مختلف (تسجيل → حساب → تلخيص)
- Q: أي مصدر يُستخدم أولاً لتحديد المناوبة — جدول الفئة أم جدول الموظف الفردي؟ → A: جدول الفئة أولاً، ثم جدول الموظف الفردي كـ override

---

## قصص المستخدمين

### كـ مسؤول إداري (Admin):
- [ ] أستطيع إنشاء فئة حضور جديدة (مثل: "مناوبة صباحية"، "مناوبة مسائية"، "مناوبة ليلية")
- [ ] أستطيع تعريف خصائص الفئة: أيام العمل، أيام الراحة، عدد ساعات العمل المطلوبة، نوع الدورة (أسبوعية/دورية/ساعية)
- [ ] أستطيع ربط الفئة بجدول مناوبة معين مع تحديد الفترة الزمنية (من تاريخ - إلى تاريخ)
- [ ] أستطيع تعديل فئة موجودة أو حذفها (إذا لم تكن مرتبطة بموظفين نشطين)
- [ ] أستطيع عرض جميع الفئات مع عدد الموظفين المرتبطين بكل فئة

### كـ مسؤول الموظفين (HR):
- [ ] أستطيع تعيين موظف لفئة حضور عند إنشائه أو تعديله
- [ ] أستطيع تحديد صلاحيات الموظف داخل الفئة (تفعيل الحضور، الجدول، الإvertime، العطل)
- [ ] أستطيع نقل موظف من فئة إلى فئة أخرى مع تحديد التاريخ
- [ ] أستطيع عرض جميع الموظفين في فئة معينة
- [ ] أستطيع عرض الفئة الحالية لأي موظف

### كـ مشرف الحضور (Attendance Supervisor):
- [ ] أستطيع تحديد جدول المناوبة لكل فئة مع تحديد الفترة الزمنية
- [ ] أستطيع تغيير جدول فئة معين لفترة محددة
- [ ] أستطيع عرض جداول الفئات مع التقويم

### كـ نظام الحضور (System - حساب تلقائي):
- [ ] يتم احتساب الحضور تلقائياً عند وصول بصمة من جهاز البصمة
- [ ] يتم تحديد حالة الموظف (حاضر، متأخر، غائب، خرج مبكراً) بناءً على جدوله وفئته
- [ ] يتم احتساب ساعات العمل الفعلية والساعات الإضافية
- [ ] يتم احتساب فترات الاستراحة
- [ ] يتم احتساب أيام العطل والأجازات

---

## المتطلبات الوظيفية

### Business Rules

#### BR1: إدارة فئات الحضور
- كل فئة تتبع لشركة واحدة فقط
- كل فئة لها كود فريد داخل الشركة
- الفئة تحمل: كود، اسم، نوع الدورة (أسبوعية/دورية/ساعية)، طول الدورة، أيام العمل، أيام الراحة، هل تعمل في العطل، هل تعمل في عطل نهاية الأسبوع، ساعات العمل المطلوبة
- الفئة يمكن أن لها سياسة حضور خاصة (att_grouppolicy)

#### BR2: تعيين الموظفين للفئات
- كل موظف يمكن أن يكون في فئة حضور واحدة فقط في أي وقت
- عند تعيين موظف لفئة، يتم تحديد: هل الحضور مفعل، هل الجدول مفعل، هل الإvertime مفعل، هل العطل مفعل، هل التعويض مفعل
- نقل الموظف من فئة إلى أخرى يتم مع تحديد التاريخ (لا يحذف القديم، يسجل جديد)
- لا يمكن تعيين موظف لنفس الفئة مرتين

#### BR3: ربط الفئات بالجداول
- كل فئة يمكن أن يكون لها جدول مناوبة واحد لكل فترة زمنية
- الجدول يحدد: الفئة، المناوبة (shift)، تاريخ البداية، تاريخ النهاية
- يمكن تغيير جدول الفئة لفترة محددة (بدون حذف الجدول القديم)
- الجدول القديم يبقى في السجل للرجوع إليه

#### BR4: نظام الحساب (Attendance Calculation)
- عند وصول بصمة:
  1. تحديد الموظف من كود الموظف في البصمة
  2. تحديد فئة الحضور للموظف (أو الإسناد التلقائي للفئة الافتراضية — انظر BR7)
  3. تحديد جدول المناوبة للموظف في هذا التاريخ (الأولوية: جدول الموظف الفردي `att_attschedule` ← ثم جدول الفئة `att_groupschedule`)
  4. تحديد المناوبة (shift) من الجدول
  5. تحديد التفاصيل (shift_detail) من المناوبة حسب يوم الأسبوع
  6. تحديد الفاصل الزمني (time_interval) من التفاصيل
  7. تطبيق قواعد الحضور من السياسة (margin التاخير، margin الخروج المبكر، قواعد الإvertime)
  8. تسجيل البصمة وحساب الحالة فوراً
- يتم احتساب الحالة فوراً عند كل بصمة (احتساب فوري)
- يتم مراجعة يومية شاملة للتأكد من صحة جميع الحسابات وتصحيح أي أخطاء (مثل فواتر الشبكة أو التأخير في وصول البصمات)

#### BR5: الفئات الافتراضية
- عند إنشاء شركة جديدة، يتم إنشاء فئة افتراضية واحدة ("افتراضي") مع جدول مناوبة أساسي
- الموظفون الجدد يُعيَّنون للفئة الافتراضية تلقائياً
- عند وصول بصمة لموظف ليس له فئة حضور، يتم إسناده تلقائياً للفئة الافتراضية للشركة ثم احتساب الحضور بشكل طبيعي

#### BR6: لا حذف dữيات مرتبطة
- لا يمكن حذف فئة إذا كان بها موظفون نشطون
- لا يمكن حذف جدول إذا كان مرتبطاً بفترة حالية أو مستقبلية
- لا يمكن حذف مناوبة إذا كانت مستخدمة في جداول نشطة

#### BR7: الإسناد التلقائي عند عدم وجود فئة
- عند وصول بصمة لموظف غير معيّن لأي فئة حضور، يتم:
  1. البحث عن الفئة الافتراضية للشركة
  2. إسناد الموظف لتلك الفئة تلقائياً مع تفعيل جميع الصلاحيات
  3. احتساب الحضور بشكل طبيعي
- يتم تسجيل الحادثة في السجلات (logs) للرجوع إليها

### Validation Rules

#### VR1: فئة الحضور
- الكود: مطلوب، 50 حرف كحد أقصى، فريد داخل الشركة
- الاسم: مطلوب، 100 حرف كحد أقصى
- نوع الدورة: مطلوب، من: cyclic, weekly, hours
- طول الدورة: مطلوب، عدد صحيح موجب
- أيام العمل: مطلوب، عدد صحيح موجب
- أيام الراحة: مطلوب، عدد صحيح موجب

#### VR2: تعيين الموظف للفئة
- الموظف: مطلوب، فريد (لا يتكرر في نفس الفئة)
- الفئة: مطلوب، موجودة ونشطة
-至少 يجب تفعيل صلاحية واحدة على الأقل (attendance أو schedule أو overtime أو holiday)

#### VR3: جدول الفئة
- الفئة: مطلوب، موجودة
- المناوبة: مطلوب، موجودة ونشطة
- تاريخ البداية: مطلوب، قبل أو يساوي تاريخ النهاية
- تاريخ النهاية: مطلوب، بعد أو يساوي تاريخ البداية
- لا تداخل مع جداول أخرى لنفس الفئة في نفس الفترة

---

## المتطلبات التقنية

### قاعدة البيانات

#### 1. جدول فئات الحضور (att_attgroup)
```
att_attgroup
├── id (PK, auto-increment)
├── create_time (timestamp, nullable)
├── create_user (varchar 150, nullable)
├── change_time (timestamp, nullable)
├── change_user (varchar 150, nullable)
├── status (smallint, default: 1)
├── code (varchar 50, NOT NULL)
├── name (varchar 100, NOT NULL)
├── company_id (FK → personnel_company.id, NOT NULL)
├── created_at (timestamp)
├── updated_at (timestamp)
├── deleted_at (timestamp, nullable)
├── UNIQUE (company_id, code)
└── INDEX (company_id)
```

#### 2. جدول تعيين الموظفين للفئات (att_attemployee)
```
att_attemployee
├── id (PK, auto-increment)
├── create_time (timestamp, nullable)
├── create_user (varchar 150, nullable)
├── change_time (timestamp, nullable)
├── change_user (varchar 150, nullable)
├── status (smallint, default: 1)
├── enable_attendance (boolean, NOT NULL, default: true)
├── enable_schedule (boolean, NOT NULL, default: true)
├── enable_overtime (boolean, NOT NULL, default: false)
├── enable_holiday (boolean, NOT NULL, default: true)
├── enable_compensatory (boolean, NOT NULL, default: false)
├── emp_id (FK → users.id, NOT NULL, UNIQUE)
├── group_id (FK → att_attgroup.id, nullable)
├── ip_address (inet, nullable)
├── created_at (timestamp)
├── updated_at (timestamp)
├── deleted_at (timestamp, nullable)
├── UNIQUE (emp_id)
└── INDEX (group_id)
```

#### 3. جدول جداول الفئات (att_groupschedule)
```
att_groupschedule
├── id (PK, auto-increment)
├── create_time (timestamp, nullable)
├── create_user (varchar 150, nullable)
├── change_time (timestamp, nullable)
├── change_user (varchar 150, nullable)
├── status (smallint, default: 1)
├── start_date (date, NOT NULL)
├── end_date (date, NOT NULL)
├── group_id (FK → att_attgroup.id, NOT NULL)
├── shift_id (FK → att_attshift.id, nullable)
├── created_at (timestamp)
├── updated_at (timestamp)
├── deleted_at (timestamp, nullable)
└── INDEX (group_id, start_date, end_date)
```

#### 4. جدول المناوبات (att_attshift)
```
att_attshift
├── id (PK, auto-increment)
├── alias (varchar 50, NOT NULL)
├── cycle_unit (smallint, NOT NULL, default: 1) // 1=daily, 2=weekly, 3=monthly
├── shift_cycle (integer, NOT NULL, default: 1)
├── work_weekend (boolean, NOT NULL, default: false)
├── weekend_type (smallint, NOT NULL, default: 0)
├── work_day_off (boolean, NOT NULL, default: false)
├── day_off_type (smallint, NOT NULL, default: 0)
├── auto_shift (smallint, NOT NULL, default: 0)
├── enable_ot_rule (boolean, NOT NULL, default: false)
├── ot_rule (uuid, nullable)
├── frequency (smallint, NOT NULL, default: 1)
├── company_id (FK → personnel_company.id, NOT NULL)
├── created_at (timestamp)
├── updated_at (timestamp)
├── deleted_at (timestamp, nullable)
└── INDEX (company_id)
```

#### 5. جدول تفاصيل المناوبة (att_shiftdetail)
```
att_shiftdetail
├── id (PK, auto-increment)
├── in_time (time, NOT NULL)
├── out_time (time, NOT NULL)
├── day_index (integer, NOT NULL) // 0=الأحد, 1=الإثنين, ..., 6=السبت
├── shift_id (FK → att_attshift.id, NOT NULL)
├── time_interval_id (FK → att_timeinterval.id, NOT NULL)
├── created_at (timestamp)
├── updated_at (timestamp)
└── INDEX (shift_id, day_index)
```

#### 6. جدول الفواصل الزمنية (att_timeinterval)
```
att_timeinterval
├── id (PK, auto-increment)
├── alias (varchar 50, NOT NULL, UNIQUE)
├── use_mode (smallint, NOT NULL, default: 0)
├── in_time (time, NOT NULL)
├── in_ahead_margin (integer, NOT NULL, default: 0) // دقائق قبل موعد الدخول
├── in_above_margin (integer, NOT NULL, default: 0) // دقائق بعد موعد الدخول (تolerance)
├── out_ahead_margin (integer, NOT NULL, default: 0) // دقائق قبل موعد الخروج
├── out_above_margin (integer, NOT NULL, default: 0) // دقائق بعد موعد الخروج
├── duration (integer, NOT NULL, default: 0) // مدة العمل بالدقائق
├── in_required (smallint, NOT NULL, default: 1) // هل الدخول إلزامي
├── out_required (smallint, NOT NULL, default: 1) // هل الخروج إلزامي
├── allow_late (integer, NOT NULL, default: 0) // السماح بالتأخير بالدقائق
├── allow_leave_early (integer, NOT NULL, default: 0) // السماح بالخروج المبكر بالدقائق
├── work_day (double, NOT NULL, default: 1.0) // أيام العمل المحسبوبة
├── early_in (smallint, NOT NULL, default: 0)
├── min_early_in (integer, NOT NULL, default: 0)
├── late_out (smallint, NOT NULL, default: 0)
├── min_late_out (integer, NOT NULL, default: 0)
├── overtime_lv (smallint, NOT NULL, default: 0)
├── overtime_lv1 (smallint, NOT NULL, default: 0)
├── overtime_lv2 (smallint, NOT NULL, default: 0)
├── overtime_lv3 (smallint, NOT NULL, default: 0)
├── multiple_punch (smallint, NOT NULL, default: 0)
├── available_interval_type (smallint, NOT NULL, default: 0)
├── available_interval (integer, NOT NULL, default: 0)
├── work_time_duration (integer, NOT NULL, default: 0)
├── func_key (smallint, NOT NULL, default: 0)
├── work_type (smallint, NOT NULL, default: 0)
├── day_change (time, NOT NULL) // وقت تغيير اليوم
├── enable_early_in (boolean, NOT NULL, default: false)
├── enable_late_out (boolean, NOT NULL, default: false)
├── enable_overtime (boolean, NOT NULL, default: false)
├── ot_rule (uuid, nullable)
├── color_setting (varchar 30, nullable)
├── enable_max_ot_limit (boolean, NOT NULL, default: false)
├── max_ot_limit (integer, NOT NULL, default: 0)
├── count_early_in_interval (boolean, NOT NULL, default: false)
├── count_late_out_interval (boolean, NOT NULL, default: false)
├── ot_pay_code_id (FK → att_paycode.id, nullable)
├── overtime_policy (smallint, NOT NULL, default: 0)
├── company_id (FK → personnel_company.id, NOT NULL)
├── created_at (timestamp)
├── updated_at (timestamp)
├── deleted_at (timestamp, nullable)
└── INDEX (company_id)
```

#### 7. جدول أكواد الرواتب (att_paycode)
```
att_paycode
├── id (PK, auto-increment)
├── create_time (timestamp, nullable)
├── create_user (varchar 150, nullable)
├── change_time (timestamp, nullable)
├── change_user (varchar 150, nullable)
├── status (smallint, NOT NULL, default: 1)
├── code (varchar 20, NOT NULL, UNIQUE)
├── name (varchar 50, NOT NULL, UNIQUE)
├── code_type (smallint, NOT NULL, default: 0) // 0=work, 1=leave, 2=overtime, 3=other
├── tag (smallint, nullable)
├── fixed_code (smallint, nullable)
├── is_work (boolean, NOT NULL, default: false)
├── fixed_hours (numeric(8,2), NOT NULL, default: 0)
├── is_paid (boolean, NOT NULL, default: false)
├── is_benefit (boolean, NOT NULL, default: false)
├── round_off (smallint, NOT NULL, default: 0)
├── min_val (numeric(4,1), NOT NULL, default: 0)
├── display_format (smallint, NOT NULL, default: 0)
├── symbol (varchar 20, nullable)
├── display_order (smallint, NOT NULL, default: 0)
├── "desc" (text, nullable)
├── is_display (boolean, NOT NULL, default: true)
├── is_default (boolean, NOT NULL, default: false)
├── color_setting (varchar 30, nullable)
├── created_at (timestamp)
├── updated_at (timestamp)
└── deleted_at (timestamp, nullable)
```

#### 8. جدول أكواد الحضور (att_attcode)
```
att_attcode
├── id (PK, auto-increment)
├── code (varchar 20, NOT NULL, UNIQUE)
├── alias (varchar 50, NOT NULL, UNIQUE)
├── display_format (smallint, NOT NULL, default: 0)
├── symbol (varchar 20, NOT NULL)
├── round_off (smallint, NOT NULL, default: 0)
├── min_val (numeric(4,1), NOT NULL, default: 0)
├── symbol_only (boolean, NOT NULL, default: false)
├── "order" (smallint, NOT NULL, default: 0)
├── color_setting (varchar 30, nullable)
├── created_at (timestamp)
├── updated_at (timestamp)
└── deleted_at (timestamp, nullable)
```

#### 9. جدول سياسة الفئات (att_grouppolicy)
```
att_grouppolicy
├── id (PK, auto-increment)
├── create_time (timestamp, nullable)
├── create_user (varchar 150, nullable)
├── change_time (timestamp, nullable)
├── change_user (varchar 150, nullable)
├── status (smallint, NOT NULL, default: 1)
├── use_ot (smallint, NOT NULL, default: 0)
├── weekend1 (smallint, NOT NULL, default: 6) // يوم عطلة 1
├── weekend2 (smallint, NOT NULL, default: 0) // يوم عطلة 2
├── start_of_week (smallint, NOT NULL, default: 0)
├── max_hrs (numeric(4,1), NOT NULL, default: 8)
├── day_change (time, NOT NULL)
├── paring_rule (smallint, NOT NULL, default: 0)
├── punch_period (smallint, NOT NULL, default: 0)
├── daily_ot (boolean, NOT NULL, default: false)
├── daily_ot_rule (uuid, nullable)
├── weekly_ot (boolean, NOT NULL, default: false)
├── weekly_ot_rule (uuid, nullable)
├── weekend_ot (boolean, NOT NULL, default: false)
├── weekend_ot_rule (uuid, nullable)
├── holiday_ot (boolean, NOT NULL, default: false)
├── holiday_ot_rule (uuid, nullable)
├── late_in2absence (integer, NOT NULL, default: 0)
├── early_out2absence (integer, NOT NULL, default: 0)
├── miss_in (smallint, NOT NULL, default: 0)
├── late_in_hrs (integer, NOT NULL, default: 0)
├── miss_out (smallint, NOT NULL, default: 0)
├── early_out_hrs (integer, NOT NULL, default: 0)
├── require_capture (boolean, NOT NULL, default: false)
├── require_work_code (boolean, NOT NULL, default: false)
├── require_punch_state (boolean, NOT NULL, default: false)
├── group_id (FK → att_attgroup.id, NOT NULL)
├── email_send_time (time, NOT NULL)
├── group_frequency (smallint, NOT NULL, default: 0)
├── group_send_day (smallint, NOT NULL, default: 0)
├── max_absent (integer, NOT NULL, default: 0)
├── max_early_out (integer, NOT NULL, default: 0)
├── max_late_in (integer, NOT NULL, default: 0)
├── sending_day (smallint, NOT NULL, default: 0)
├── weekend1_color_setting (varchar 30, nullable)
├── weekend2_color_setting (varchar 30, nullable)
├── ot_pay_code_id (FK → att_paycode.id, nullable)
├── overtime_policy (smallint, NOT NULL, default: 0)
├── enable_compensatory (boolean, NOT NULL, default: false)
├── bot_uid (varchar 100, nullable)
├── enable_workcode_calculation (boolean, NOT NULL, default: false)
├── enable_workcode_punch_state (smallint, NOT NULL, default: 0)
├── created_at (timestamp)
├── updated_at (timestamp)
└── deleted_at (timestamp, nullable)
```

#### 10. جدول جداول الموظفين (att_attschedule)
```
att_attschedule
├── id (PK, auto-increment)
├── start_date (date, NOT NULL)
├── end_date (date, NOT NULL)
├── employee_id (FK → users.id, NOT NULL)
├── shift_id (FK → att_attshift.id, NOT NULL)
├── created_at (timestamp)
├── updated_at (timestamp)
└── INDEX (employee_id, start_date, end_date)
```

#### 11. جدول الجداول المؤقتة (att_temporaryschedule)
```
att_temporaryschedule
├── id (PK, auto-increment)
├── create_time (timestamp, nullable)
├── create_user (varchar 150, nullable)
├── change_time (timestamp, nullable)
├── change_user (varchar 150, nullable)
├── status (smallint, NOT NULL, default: 1)
├── att_date (date, NOT NULL)
├── employee_id (FK → users.id, NOT NULL)
├── time_interval_id (FK → att_timeinterval.id, nullable)
├── created_at (timestamp)
├── updated_at (timestamp)
└── INDEX (employee_id, att_date)
```

#### 12. جدول политика القسم (att_departmentpolicy)
```
att_departmentpolicy
├── id (PK, auto-increment)
├── create_time (timestamp, nullable)
├── create_user (varchar 150, nullable)
├── change_time (timestamp, nullable)
├── change_user (varchar 150, nullable)
├── status (smallint, NOT NULL, default: 1)
├── department_id (FK → personnel_department.id, NOT NULL)
├── ... (نفس أعمدة att_grouppolicy تقريباً)
├── created_at (timestamp)
├── updated_at (timestamp)
└── deleted_at (timestamp, nullable)
```

#### 13. جدول جداول القسم (att_departmentschedule)
```
att_departmentschedule
├── id (PK, auto-increment)
├── create_time (timestamp, nullable)
├── create_user (varchar 150, nullable)
├── change_time (timestamp, nullable)
├── change_user (varchar 150, nullable)
├── status (smallint, NOT NULL, default: 1)
├── start_date (date, NOT NULL)
├── end_date (date, NOT NULL)
├── department_id (FK → personnel_department.id, NOT NULL)
├── shift_id (FK → att_attshift.id, NOT NULL)
├── created_at (timestamp)
├── updated_at (timestamp)
└── deleted_at (timestamp, nullable)
```

#### 14. جدول فترات الاستراحة (att_breaktime)
```
att_breaktime
├── id (PK, auto-increment)
├── alias (varchar 50, NOT NULL, UNIQUE)
├── period_start (time, NOT NULL)
├── duration (integer, NOT NULL) // بالدقائق
├── end_margin (integer, NOT NULL, default: 0)
├── func_key (smallint, NOT NULL, default: 0)
├── available_interval_type (smallint, NOT NULL, default: 0)
├── available_interval (integer, NOT NULL, default: 0)
├── multiple_punch (smallint, NOT NULL, default: 0)
├── calc_type (smallint, NOT NULL, default: 0)
├── minimum_duration (integer, nullable)
├── early_in (smallint, NOT NULL, default: 0)
├── late_in (smallint, NOT NULL, default: 0)
├── profit_rule (boolean, NOT NULL, default: false)
├── min_early_in (integer, NOT NULL, default: 0)
├── loss_rule (boolean, NOT NULL, default: false)
├── min_late_in (integer, NOT NULL, default: 0)
├── loss_code_id (FK → att_paycode.id, nullable)
├── profit_code_id (FK → att_paycode.id, nullable)
├── company_id (FK → personnel_company.id, NOT NULL)
├── created_at (timestamp)
├── updated_at (timestamp)
└── deleted_at (timestamp, nullable)
```

#### 15. جدول الربط بين الفواصل والاستراحتات (att_timeinterval_break_time)
```
att_timeinterval_break_time
├── id (PK, auto-increment)
├── timeinterval_id (FK → att_timeinterval.id, NOT NULL)
├── breaktime_id (FK → att_breaktime.id, NOT NULL)
├── UNIQUE (timeinterval_id, breaktime_id)
└── INDEXes
```

#### 16. جدول جداول البصمات (iclock_transaction)
```
iclock_transaction
├── id (PK, auto-increment)
├── emp_code (varchar 20, NOT NULL)
├── punch_time (timestamp, NOT NULL)
├── punch_state (varchar 5, NOT NULL) // 0=in, 1=out
├── verify_type (integer, NOT NULL, default: 0)
├── work_code (varchar 20, nullable)
├── terminal_sn (varchar 50, nullable)
├── terminal_alias (varchar 50, nullable)
├── area_alias (varchar 100, nullable)
├── longitude (double, nullable)
├── latitude (double, nullable)
├── gps_location (text, nullable)
├── mobile (varchar 50, nullable)
├── source (smallint, nullable)
├── purpose (smallint, nullable)
├── crc (varchar 100, nullable)
├── is_attendance (smallint, nullable)
├── reserved (varchar 100, nullable)
├── upload_time (timestamp, nullable)
├── sync_status (smallint, nullable)
├── sync_time (timestamp, nullable)
├── is_mask (smallint, nullable)
├── temperature (numeric(4,1), nullable)
├── emp_id (FK → users.id, nullable)
├── terminal_id (FK → iclock_terminal.id, nullable)
├── company_code (varchar 50, nullable)
├── created_at (timestamp)
├── updated_at (timestamp)
├── UNIQUE (company_code, emp_code, punch_time)
└── INDEX (emp_id, punch_time)
```

### النماذج (Models)

#### Model: AttendanceGroup
- **Table:** `att_attgroup`
- **Relationships:**
  - `company()` → BelongsTo → Company
  - `employees()` → HasMany → AttendanceEmployee
  - `schedules()` → HasMany → GroupSchedule
  - `policy()` → HasOne → GroupPolicy
- **Scopes:** `scopeByCompany()`, `scopeActive()`

#### Model: AttendanceEmployee
- **Table:** `att_attemployee`
- **Relationships:**
  - `employee()` → BelongsTo → User
  - `group()` → BelongsTo → AttendanceGroup
- **Scopes:** `scopeInGroup()`, `scopeActive()`

#### Model: GroupSchedule
- **Table:** `att_groupschedule`
- **Relationships:**
  - `group()` → BelongsTo → AttendanceGroup
  - `shift()` → BelongsTo → AttendanceShift
- **Scopes:** `scopeForDate()`, `scopeActive()`

#### Model: AttendanceShift
- **Table:** `att_attshift`
- **Relationships:**
  - `company()` → BelongsTo → Company
  - `details()` → HasMany → ShiftDetail
- **Scopes:** `scopeByCompany()`, `scopeActive()`

#### Model: ShiftDetail
- **Table:** `att_shiftdetail`
- **Relationships:**
  - `shift()` → BelongsTo → AttendanceShift
  - `timeInterval()` → BelongsTo → TimeInterval

#### Model: TimeInterval
- **Table:** `att_timeinterval`
- **Relationships:**
  - `company()` → BelongsTo → Company
  - `breakTimes()` → BelongsToMany → BreakTime
  - `otPayCode()` → BelongsTo → PayCode

#### Model: PayCode
- **Table:** `att_paycode`
- **Scopes:** `scopeByType()`, `scopeWork()`, `scopeActive()`

#### Model: AttCode
- **Table:** `att_attcode`

#### Model: GroupPolicy
- **Table:** `att_grouppolicy`
- **Relationships:**
  - `group()` → BelongsTo → AttendanceGroup
  - `otPayCode()` → BelongsTo → PayCode

#### Model: DepartmentPolicy
- **Table:** `att_departmentpolicy`
- **Relationships:**
  - `department()` → BelongsTo → Department
  - `otPayCode()` → BelongsTo → PayCode

#### Model: DepartmentSchedule
- **Table:** `att_departmentschedule`
- **Relationships:**
  - `department()` → BelongsTo → Department
  - `shift()` → BelongsTo → AttendanceShift

#### Model: BreakTime
- **Table:** `att_breaktime`
- **Relationships:**
  - `company()` → BelongsTo → Company
  - `lossCode()` → BelongsTo → PayCode
  - `profitCode()` → BelongsTo → PayCode

#### Model: EmployeeSchedule
- **Table:** `att_attschedule`
- **Relationships:**
  - `employee()` → BelongsTo → User
  - `shift()` → BelongsTo → AttendanceShift

#### Model: TemporarySchedule
- **Table:** `att_temporaryschedule`
- **Relationships:**
  - `employee()` → BelongsTo → User
  - `timeInterval()` → BelongsTo → TimeInterval

#### Model: IclockTransaction (Baklava)
- **Table:** `iclock_transaction`
- **Relationships:**
  - `employee()` → BelongsTo → User
  - `terminal()` → BelongsTo → FingerprintDevice

### الخدمات (Services)

#### AttendanceGroupService
- `createGroup(array $data): AttendanceGroup` — إنشاء فئة جديدة
- `updateGroup(AttendanceGroup $group, array $data): AttendanceGroup` — تعديل فئة
- `deleteGroup(AttendanceGroup $group): bool` — حذف فئة (مع التحقق من عدم ارتباط موظفين)
- `getGroupsByCompany(int $companyId): Collection` — جلب فئات الشركة
- `getGroupWithEmployees(int $groupId): AttendanceGroup` — جلب فئة مع موظفيها
- `assignEmployeeToGroup(int $employeeId, int $groupId, array $flags): AttendanceEmployee` — تعيين موظف لفئة
- `removeEmployeeFromGroup(int $employeeId): bool` — إزالة موظف من فئة
- `getEmployeesInGroup(int $groupId): Collection` — جلب موظفي الفئة

#### AttendanceShiftService
- `createShift(array $data): AttendanceShift` — إنشاء مناوبة
- `updateShift(AttendanceShift $shift, array $data): AttendanceShift` — تعديل مناوبة
- `deleteShift(AttendanceShift $shift): bool` — حذف مناوبة
- `getShiftsByCompany(int $companyId): Collection` — جلب مناوبات الشركة
- `createShiftDetail(array $data): ShiftDetail` — إنشاء تفصيل مناوبة
- `getShiftWithDetails(int $shiftId): AttendanceShift` — جلب مناوبة مع تفاصيلها

#### TimeIntervalService
- `createTimeInterval(array $data): TimeInterval` — إنشاء فاصل زمني
- `updateTimeInterval(TimeInterval $interval, array $data): TimeInterval` — تعديل فاصل
- `deleteTimeInterval(TimeInterval $interval): bool` — حذف فاصل
- `getTimeIntervalsByCompany(int $companyId): Collection` — جلب الفواصل

#### GroupScheduleService
- `createGroupSchedule(array $data): GroupSchedule` — إنشاء جدول فئة
- `updateGroupSchedule(GroupSchedule $schedule, array $data): GroupSchedule` — تعديل جدول فئة
- `deleteGroupSchedule(GroupSchedule $schedule): bool` — حذف جدول فئة
- `getActiveScheduleForGroup(int $groupId, string $date): ?GroupSchedule` — جلب الجدول النشط لفئة في تاريخ محدد
- `getSchedulesForGroup(int $groupId): Collection` — جلب جداول الفئة

#### AttendanceCalculationService (محرك الحساب)
**ملاحظة:** هذه الخدمة تعمل مع خدمات موجودة بالفعل في النظام — لا تستبدلها بل تكملها:
- `RawAttendanceLogService` ← مسؤولة عن تسجيل البصمات الخام من الأجهزة
- `AttendanceCalculationService` ← مسؤولة عن تحديد الفئة والمناوبة والفاصل ثم حساب الحالة
- `DailyAttendanceSummaryService` ← مسؤولة عن تلخيص يوم كامل وتخزين النتيجة النهائية

**التدفق:** بصمة خام → RawAttendanceLogService (تسجيل) → AttendanceCalculationService (حساب) → DailyAttendanceSummaryService (تلخيص)

- `calculateAttendance(int $employeeId, DateTimeInterface $punchTime): AttendanceSession` — حساب الحضور لبصمة
- `resolveEmployeeGroup(int $employeeId): ?AttendanceGroup` — تحديد فئة الموظف (مع الإسناد التلقائي للافتراضية)
- `resolveShiftForEmployee(int $employeeId, string $date): ?AttendanceShift` — تحديد مناوبة الموظف (الأولوية: جدول الموظف الفردي `att_attschedule` ← ثم جدول الفئة `att_groupschedule`)
- `resolveTimeInterval(AttendanceShift $shift, string $date): ?TimeInterval` — تحديد الفاصل الزمني
- `determineStatus(AttendanceSession $session, TimeInterval $interval): string` — تحديد الحالة
- `calculateLateMinutes(DateTimeInterface $actual, DateTimeInterface $expected, TimeInterval $interval): int` — حساب التاخير
- `calculateEarlyLeaveMinutes(DateTimeInterface $actual, DateTimeInterface $expected, TimeInterval $interval): int` — حساب الخروج المبكر
- `calculateOvertimeMinutes(DateTimeInterface $actual, DateTimeInterface $expected, TimeInterval $interval): int` — حساب الإvertime
- `calculateBreakMinutes(AttendanceSession $session): int` — حساب فترات الاستراحة

#### PayCodeService
- `createPayCode(array $data): PayCode` — إنشاء كود راتب
- `getPayCodesByCompany(int $companyId): Collection` — جلب أكواد الرواتب
- `getWorkCodes(int $companyId): Collection` — جلب أكواد العمل فقط

#### AttCodeService
- `createAttCode(array $data): AttCode` — إنشاء كود حضور
- `getAttCodes(): Collection` — جلب جميع أكواد الحضور

### المستودعات (Repositories)

#### AttendanceGroupRepository
- `getAll(array $filters, int $perPage): LengthAwarePaginator`
- `findById(int $id): ?AttendanceGroup`
- `create(array $data): AttendanceGroup`
- `update(AttendanceGroup $group, array $data): AttendanceGroup`
- `delete(AttendanceGroup $group): bool`
- `getByCompany(int $companyId): Collection`
- `countEmployeesInGroup(int $groupId): int`

#### AttendanceEmployeeRepository
- `getAll(array $filters, int $perPage): LengthAwarePaginator`
- `findById(int $id): ?AttendanceEmployee`
- `findByEmployee(int $employeeId): ?AttendanceEmployee`
- `create(array $data): AttendanceEmployee`
- `update(AttendanceEmployee $record, array $data): AttendanceEmployee`
- `delete(AttendanceEmployee $record): bool`
- `getByGroup(int $groupId): Collection`

#### AttendanceShiftRepository
- `getAll(array $filters, int $perPage): LengthAwarePaginator`
- `findById(int $id): ?AttendanceShift`
- `create(array $data): AttendanceShift`
- `update(AttendanceShift $shift, array $data): AttendanceShift`
- `delete(AttendanceShift $shift): bool`
- `getByCompany(int $companyId): Collection`

#### GroupScheduleRepository
- `getAll(array $filters, int $perPage): LengthAwarePaginator`
- `findById(int $id): ?GroupSchedule`
- `create(array $data): GroupSchedule`
- `update(GroupSchedule $schedule, array $data): GroupSchedule`
- `delete(GroupSchedule $schedule): bool`
- `getActiveForGroup(int $groupId, string $date): ?GroupSchedule`
- `getByGroup(int $groupId): Collection`
- `hasOverlap(int $groupId, string $startDate, string $endDate, ?int $excludeId = null): bool`

#### TimeIntervalRepository
- `getAll(array $filters, int $perPage): LengthAwarePaginator`
- `findById(int $id): ?TimeInterval`
- `create(array $data): TimeInterval`
- `update(TimeInterval $interval, array $data): TimeInterval`
- `delete(TimeInterval $interval): bool`
- `getByCompany(int $companyId): Collection`

### التحكم (Controllers)

#### AttendanceGroupsController
- `index()` — عرض قائمة الفئات
- `create()` — عرض نموذج الإنشاء
- `store(StoreAttendanceGroupRequest $request)` — حفظ الفئة
- `show(int $id)` — عرض تفاصيل الفئة
- `edit(int $id)` — عرض نموذج التعديل
- `update(UpdateAttendanceGroupRequest $request, int $id)` — تحديث الفئة
- `destroy(int $id)` — حذف الفئة
- `assignEmployee(AssignEmployeeToGroupRequest $request, int $groupId)` — تعيين موظف للفئة
- `removeEmployee(int $groupId, int $employeeId)` — إزالة موظف من الفئة
- `employees(int $groupId)` — عرض موظفي الفئة

#### AttendanceShiftsController
- `index()` — عرض قائمة المناوبات
- `create()` — عرض نموذج الإنشاء
- `store(StoreAttendanceShiftRequest $request)` — حفظ المناوبة
- `show(int $id)` — عرض تفاصيل المناوبة
- `edit(int $id)` — عرض نموذج التعديل
- `update(UpdateAttendanceShiftRequest $request, int $id)` — تحديث المناوبة
- `destroy(int $id)` — حذف المناوبة

#### GroupSchedulesController
- `index()` — عرض قوائم جداول الفئات
- `create()` — عرض نموذج الإنشاء
- `store(StoreGroupScheduleRequest $request)` — حفظ الجدول
- `show(int $id)` — عرض تفاصيل الجدول
- `edit(int $id)` — عرض نموذج التعديل
- `update(UpdateGroupScheduleRequest $request, int $id)` — تحديث الجدول
- `destroy(int $id)` — حذف الجدول

#### TimeIntervalsController
- `index()` — عرض قائمة الفواصل الزمنية
- `create()` — عرض نموذج الإنشاء
- `store(StoreTimeIntervalRequest $request)` — حفظ الفاصل
- `show(int $id)` — عرض تفاصيل الفاصل
- `edit(int $id)` — عرض نموذج التعديل
- `update(UpdateTimeIntervalRequest $request, int $id)` — تحديث الفاصل
- `destroy(int $id)` — حذف الفاصل

### المسارات (Routes)

```php
// فئات الحضور
Route::middleware('permission:view-attendance-groups')->prefix('attendance/groups')->name('attendance.groups.')->group(function () {
    Route::get('/', [AttendanceGroupsController::class, 'index'])->name('index');
    Route::get('create', [AttendanceGroupsController::class, 'create'])->name('create');
    Route::post('/', [AttendanceGroupsController::class, 'store'])->name('store');
    Route::get('{id}', [AttendanceGroupsController::class, 'show'])->name('show');
    Route::get('{id}/edit', [AttendanceGroupsController::class, 'edit'])->name('edit');
    Route::put('{id}', [AttendanceGroupsController::class, 'update'])->name('update');
    Route::delete('{id}', [AttendanceGroupsController::class, 'destroy'])->name('destroy');
    Route::post('{groupId}/employees', [AttendanceGroupsController::class, 'assignEmployee'])->name('assign-employee');
    Route::delete('{groupId}/employees/{employeeId}', [AttendanceGroupsController::class, 'removeEmployee'])->name('remove-employee');
    Route::get('{groupId}/employees', [AttendanceGroupsController::class, 'employees'])->name('employees');
});

// مناوبات الحضور
Route::middleware('permission:view-attendance-shifts')->prefix('attendance/shifts')->name('attendance.shifts.')->group(function () {
    Route::get('/', [AttendanceShiftsController::class, 'index'])->name('index');
    Route::get('create', [AttendanceShiftsController::class, 'create'])->name('create');
    Route::post('/', [AttendanceShiftsController::class, 'store'])->name('store');
    Route::get('{id}', [AttendanceShiftsController::class, 'show'])->name('show');
    Route::get('{id}/edit', [AttendanceShiftsController::class, 'edit'])->name('edit');
    Route::put('{id}', [AttendanceShiftsController::class, 'update'])->name('update');
    Route::delete('{id}', [AttendanceShiftsController::class, 'destroy'])->name('destroy');
});

// جداول الفئات
Route::middleware('permission:view-group-schedules')->prefix('attendance/group-schedules')->name('attendance.group-schedules.')->group(function () {
    Route::get('/', [GroupSchedulesController::class, 'index'])->name('index');
    Route::get('create', [GroupSchedulesController::class, 'create'])->name('create');
    Route::post('/', [GroupSchedulesController::class, 'store'])->name('store');
    Route::get('{id}', [GroupSchedulesController::class, 'show'])->name('show');
    Route::get('{id}/edit', [GroupSchedulesController::class, 'edit'])->name('edit');
    Route::put('{id}', [GroupSchedulesController::class, 'update'])->name('update');
    Route::delete('{id}', [GroupSchedulesController::class, 'destroy'])->name('destroy');
});

// الفواصل الزمنية
Route::middleware('permission:view-time-intervals')->prefix('attendance/time-intervals')->name('attendance.time-intervals.')->group(function () {
    Route::get('/', [TimeIntervalsController::class, 'index'])->name('index');
    Route::get('create', [TimeIntervalsController::class, 'create'])->name('create');
    Route::post('/', [TimeIntervalsController::class, 'store'])->name('store');
    Route::get('{id}', [TimeIntervalsController::class, 'show'])->name('show');
    Route::get('{id}/edit', [TimeIntervalsController::class, 'edit'])->name('edit');
    Route::put('{id}', [TimeIntervalsController::class, 'update'])->name('update');
    Route::delete('{id}', [TimeIntervalsController::class, 'destroy'])->name('destroy');
});
```

### الواجهات (Vue Pages)

#### صفحات فئات الحضور
```
resources/js/Pages/Shifts/AttendanceGroups/
├── Index.vue          — قائمة الفئات
├── Create.vue         — إنشاء فئة جديدة
├── Edit.vue           — تعديل فئة
├── Show.vue           — تفاصيل الفئة + قائمة الموظفين
└── AssignEmployee.vue — تعيين موظف للفئة
```

#### صفحات مناوبات الحضور
```
resources/js/Pages/Shifts/AttendanceShifts/
├── Index.vue          — قائمة المناوبات
├── Create.vue         — إنشاء مناوبة جديدة
├── Edit.vue           — تعديل مناوبة
├── Show.vue           — تفاصيل المناوبة + التفاصيل اليومية
└── ShiftDetailForm.vue — نموذج تفصيل المناوبة (من داخل Show)
```

#### صفحات جداول الفئات
```
resources/js/Pages/Shifts/GroupSchedules/
├── Index.vue          — قوائم جداول الفئات
├── Create.vue         — إنشاء جدول جديد
├── Edit.vue           — تعديل جدول
└── Show.vue           — تفاصيل الجدول
```

#### صفحات الفواصل الزمنية
```
resources/js/Pages/Shifts/TimeIntervals/
├── Index.vue          — قائمة الفواصل
├── Create.vue         — إنشاء فاصل جديد
├── Edit.vue           — تعديل فاصل
└── Show.vue           — تفاصيل الفاصل
```

#### تعديل الصفحات الموجودة
```
resources/js/Pages/Users/
├── Create.vue         — إضافة حقل "فئة الحضور" (اختياري، الافتراضي: الفئة الافتراضية للشركة) في نموذج إنشاء الموظف
├── Edit.vue           — إضافة حقل "فئة الحضور" (يمكن تغييره مع تحديد التاريخ) في نموذج تعديل الموظف
└── Show.vue           — إضافة قسم "فئة الحضور" الحالية + سجل التغييرات في عرض تفاصيل الموظف
```

### الصلاحيات

```
view-attendance-groups        — عرض فئات الحضور
create-attendance-groups      — إنشاء فئات حضور
edit-attendance-groups        — تعديل فئات حضور
delete-attendance-groups      — حذف فئات حضور
assign-attendance-groups      — تعيين موظفين لفئات الحضور

view-attendance-shifts        — عرض مناوبات الحضور
create-attendance-shifts      — إنشاء مناوبات حضور
edit-attendance-shifts        — تعديل مناوبات حضور
delete-attendance-shifts      — حذف مناوبات حضور

view-group-schedules          — عرض جداول الفئات
create-group-schedules        — إنشاء جداول فئات
edit-group-schedules          — تعديل جداول فئات
delete-group-schedules        — حذف جداول فئات

view-time-intervals           — عرض الفواصل الزمنية
create-time-intervals         — إنشاء فواصل زمنية
edit-time-intervals           — تعديل فواصل زمنية
delete-time-intervals         — حذف فواصل زمنية
```

### الترجمات (Translation Keys)

```php
// ar/attendance.php
'attendance_groups' => 'فئات الحضور',
'attendance_group_created' => 'تم إنشاء فئة الحضور بنجاح',
'attendance_group_updated' => 'تم تحديث فئة الحضور بنجاح',
'attendance_group_deleted' => 'تم حذف فئة الحضور بنجاح',
'attendance_group_has_employees' => 'لا يمكن حذف الفئة لوجود موظفين مرتبطين',

'attendance_shifts' => 'مناوبات الحضور',
'attendance_shift_created' => 'تم إنشاء مناوبة الحضور بنجاح',
'attendance_shift_updated' => 'تم تحديث مناوبة الحضور بنجاح',
'attendance_shift_deleted' => 'تم حذف مناوبة الحضور بنجاح',

'group_schedules' => 'جداول الفئات',
'group_schedule_created' => 'تم إنشاء جدول الفئة بنجاح',
'group_schedule_updated' => 'تم تحديث جدول الفئة بنجاح',
'group_schedule_deleted' => 'تم حذف جدول الفئة بنجاح',
'group_schedule_overlap' => 'يوجد تداخل مع جدول آخر لنفس الفئة',

'time_intervals' => 'الفواصل الزمنية',
'time_interval_created' => 'تم إنشاء الفاصل الزمني بنجاح',
'time_interval_updated' => 'تم تحديث الفاصل الزمني بنجاح',
'time_interval_deleted' => 'تم حذف الفاصل الزمني بنجاح',

'employee_assigned_to_group' => 'تم تعيين الموظف للفئة بنجاح',
'employee_removed_from_group' => 'تم إزالة الموظف من الفئة بنجاح',
'employee_already_in_group' => 'الموظف معيّن بالفعل في فئة',

'code_required' => 'الكود مطلوب',
'code_unique' => 'الكود موجود بالفعل',
'name_required' => 'الاسم مطلوب',
'date_range_required' => 'نطاق التاريخ مطلوب',
'date_end_after_start' => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية',
```

## معايير القبول

### Phase 1: قاعدة البيانات والموديلات
- [ ] جميع الترحيلات (migrations) تم إنشاؤها
- [ ] جميع الموديلات تم إنشاؤها مع العلاقات والأوسمة
- [ ] لا توجد مشاكل في بنية قاعدة البيانات
- [ ] البيانات الافتراضية (seeders) تم إنشاؤها (أكواد الحضور الافتراضية، الفئات الافتراضية)

### Phase 2: الخدمات والمستودعات
- [ ] جميع الخدمات تتبع نمط Controller → Service → Repository → Model
- [ ] Validation في Service layer لا في Controller
- [ ] لا استخدام app() أو resolve() — استخدام DI فقط
- [ ] لا N+1 queries — استخدام with() أو load()
- [ ] PHPDoc على كل method عام

### Phase 3: التحكم والمسارات
- [ ] Controllers رقيقة (لا business logic)
- [ ] جميع المسارات محمية بـ auth و permissions
- [ ] FormRequests للتحقق من المدخلات

### Phase 4: الواجهات
- [ ] جميع الصفحات تستخدم مكونات Mistral (DataTable, FormInput, FormModal, Button, Card)
- [ ] RTL مدعوم في جميع الصفحات
- [ ] الترجمة متوفرة (عربي/إنجليزي)
- [ ] Responsive design

### Phase 5: الحساب
- [ ] محرك الحساب يحدد الفئة تلقائياً من الموظف
- [ ] محرك الحساب يحدد الجدول النشط من الفئة والتاريخ
- [ ] محرك الحساب يحدد المناوبة والفاصل الزمني
- [ ] حساب التاخير والخروج المبكر صحيح
- [ ] حساب ساعات العمل صحيح
- [ ] حساب فترات الاستراحة صحيح
- [ ] حساب الإvertime صحيح

### Phase 6: الاختبارات
- [ ] اختبارات Unit للخدمات
- [ ] اختبارات Feature للمسارات
- [ ] الاختبارات تمر بنجاح

---

## خطة التنفيذ (التسلسل)

### الموجة 1: قاعدة البيانات والموديلات (الأسبوع 1)
1. إنشاء الترحيلات (migrations) لجميع الجداول الجديدة
2. إنشاء الموديلات مع العلاقات والأوسمة
3. إنشاء الـ Seeders للبيانات الافتراضية
4. تشغيل الترحيلات والتأكد من عملها

### الموجة 2: الخدمات والمستودعات (الأسبوع 1-2)
1. إنشاء المستودعات لجميع الجداول الجديدة
2. إنشاء الخدمات مع Validation
3. اختبار الخدمات

### الموجة 3: التحكم والمسارات (الأسبوع 2)
1. إنشاء FormRequests
2. إنشاء Controllers
3. إنشاء Routes
4. اختبار المسارات

### الموجة 4: الواجهات (الأسبوع 2-3)
1. إنشاء صفحات فئات الحضور (CRUD)
2. إنشاء صفحات مناوبات الحضور (CRUD)
3. إنشاء صفحات جداول الفئات (CRUD)
4. إنشاء صفحات الفواصل الزمنية (CRUD)
5. تعديل صفحات الموظفين لإضافة ربط الفئات

### الموجة 5: محرك الحساب (الأسبوع 3)
1. تنفيذ AttendanceCalculationService
2. ربط محرك الحساب ببصمات الأجهزة
3. اختبار الحسابات

### الموجة 6: الاختبارات والتحسين (الأسبوع 3-4)
1. كتابة اختبارات Unit و Feature
2. مراجعة الأداء
3. تنظيف الكود

---

## الاعتماديات

### يعتمد على:
- `Modules/Users` — سجل الموظفين (User model)
- `Modules\Companies` — إدارة الشركات
- `Modules\Departments` — إدارة الأقسام
- `Modules\Shifts` — المناوبات الحالية (سيتم توسيعها)
- `Modules\FingerprintDevices` — أجهزة البصمة
- `Modules\Holidays` — العطل

### مطلوب لـ:
- `Modules\Payroll` — الرواتب (تحتاج أكواد الرواتب من att_paycode)
- `Modules\Attendance` — الحضور (سيتم ربط محرك الحساب)

---

*آخر تحديث: 2026-07-16*
