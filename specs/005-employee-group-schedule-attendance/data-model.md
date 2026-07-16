# نموذج البيانات - Employee Group Schedule Attendance

**التاريخ:** 2026-07-16

---

## الكيانات والعلاقات

```
personnel_company (1) ──── (N) att_attgroup
                              │
                              ├── (N) att_attemployee ──── (1) users
                              │
                              ├── (N) att_groupschedule ──── (1) att_attshift
                              │
                              └── (N) att_grouppolicy

att_attshift (1) ──── (N) att_shiftdetail ──── (1) att_timeinterval

att_timeinterval (M) ──── (M) att_breaktime

users (1) ──── (N) att_attschedule ──── (1) att_attshift

personnel_department (1) ──── (N) att_departmentpolicy
                              └── (N) att_departmentschedule ──── (1) att_attshift
```

---

## الكيانات التفصيلية

### 1. AttendanceGroup (att_attgroup)
| الحقل | النوع | القواعد | الوصف |
|-------|-------|---------|-------|
| id | integer | PK, auto-increment | المعرف |
| code | varchar(50) | NOT NULL, UNIQUE(company_id) | كود الفئة |
| name | varchar(100) | NOT NULL | اسم الفئة |
| company_id | integer | FK → companies.id, NOT NULL | الشركة |
| status | smallint | default: 1 | الحالة |
| create_time | timestamp | nullable | وقت الإنشاء |
| create_user | varchar(150) | nullable | المستخدم المنشئ |
| change_time | timestamp | nullable | وقت التعديل |
| change_user | varchar(150) | nullable | المستخدم المعدل |

### 2. AttendanceEmployee (att_attemployee)
| الحقل | النوع | القواعد | الوصف |
|-------|-------|---------|-------|
| id | integer | PK, auto-increment | المعرف |
| emp_id | integer | FK → users.id, NOT NULL, UNIQUE | الموظف |
| group_id | integer | FK → att_attgroup.id, nullable | الفئة |
| enable_attendance | boolean | NOT NULL, default: true | تفعيل الحضور |
| enable_schedule | boolean | NOT NULL, default: true | تفعيل الجدول |
| enable_overtime | boolean | NOT NULL, default: false | تفعيل الإvertime |
| enable_holiday | boolean | NOT NULL, default: true | تفعيل العطل |
| enable_compensatory | boolean | NOT NULL, default: false | تفعيل التعويض |
| status | smallint | default: 1 | الحالة |

### 3. GroupSchedule (att_groupschedule)
| الحقل | النوع | القواعد | الوصف |
|-------|-------|---------|-------|
| id | integer | PK, auto-increment | المعرف |
| group_id | integer | FK → att_attgroup.id, NOT NULL | الفئة |
| shift_id | integer | FK → att_attshift.id, nullable | المناوبة |
| start_date | date | NOT NULL | تاريخ البداية |
| end_date | date | NOT NULL | تاريخ النهاية |
| status | smallint | default: 1 | الحالة |

### 4. AttendanceShift (att_attshift)
| الحقل | النوع | القواعد | الوصف |
|-------|-------|---------|-------|
| id | integer | PK, auto-increment | المعرف |
| alias | varchar(50) | NOT NULL | اسم المناوبة |
| cycle_unit | smallint | NOT NULL, default: 1 | وحدة الدورة (1=daily, 2=weekly, 3=monthly) |
| shift_cycle | integer | NOT NULL, default: 1 | طول الدورة |
| work_weekend | boolean | NOT NULL, default: false | العمل في عطلة نهاية الأسبوع |
| weekend_type | smallint | NOT NULL, default: 0 | نوع عطلة نهاية الأسبوع |
| work_day_off | boolean | NOT NULL, default: false | العمل في أيام الراحة |
| day_off_type | smallint | NOT NULL, default: 0 | نوع أيام الراحة |
| auto_shift | smallint | NOT NULL, default: 0 | المناوبة التلقائية |
| enable_ot_rule | boolean | NOT NULL, default: false | تفعيل قاعدة الإvertime |
| ot_rule | uuid | nullable | قاعدة الإvertime |
| frequency | smallint | NOT NULL, default: 1 | التكرار |
| company_id | integer | FK → companies.id, NOT NULL | الشركة |

### 5. ShiftDetail (att_shiftdetail)
| الحقل | النوع | القواعد | الوصف |
|-------|-------|---------|-------|
| id | integer | PK, auto-increment | المعرف |
| shift_id | integer | FK → att_attshift.id, NOT NULL | المناوبة |
| time_interval_id | integer | FK → att_timeinterval.id, NOT NULL | الفاصل الزمني |
| day_index | integer | NOT NULL | فهرس اليوم (0=الأحد, ..., 6=السبت) |
| in_time | time | NOT NULL | وقت الدخول |
| out_time | time | NOT NULL | وقت الخروج |

### 6. TimeInterval (att_timeinterval)
| الحقل | النوع | القواعد | الوصف |
|-------|-------|---------|-------|
| id | integer | PK, auto-increment | المعرف |
| alias | varchar(50) | NOT NULL, UNIQUE | اسم الفاصل |
| in_time | time | NOT NULL | وقت الدخول |
| duration | integer | NOT NULL, default: 0 | مدة العمل (دقائق) |
| in_ahead_margin | integer | NOT NULL, default: 0 | دقائق قبل الدخول |
| in_above_margin | integer | NOT NULL, default: 0 | دقائق بعد الدخول |
| out_ahead_margin | integer | NOT NULL, default: 0 | دقائق قبل الخروج |
| out_above_margin | integer | NOT NULL, default: 0 | دقائق بعد الخروج |
| day_change | time | NOT NULL | وقت تغيير اليوم |
| enable_overtime | boolean | NOT NULL, default: false | تفعيل الإvertime |
| company_id | integer | FK → companies.id, NOT NULL | الشركة |

### 7. BreakTime (att_breaktime)
| الحقل | النوع | القواعد | الوصف |
|-------|-------|---------|-------|
| id | integer | PK, auto-increment | المعرف |
| alias | varchar(50) | NOT NULL, UNIQUE | اسم الاستراحة |
| period_start | time | NOT NULL | وقت البداية |
| duration | integer | NOT NULL | المدة (دقائق) |
| company_id | integer | FK → companies.id, NOT NULL | الشركة |

### 8. PayCode (att_paycode)
| الحقل | النوع | القواعد | الوصف |
|-------|-------|---------|-------|
| id | integer | PK, auto-increment | المعرف |
| code | varchar(20) | NOT NULL, UNIQUE | الكود |
| name | varchar(50) | NOT NULL, UNIQUE | الاسم |
| code_type | smallint | NOT NULL, default: 0 | النوع (0=work, 1=leave, 2=overtime, 3=other) |
| is_work | boolean | NOT NULL, default: false | هل هو كود عمل |
| is_paid | boolean | NOT NULL, default: false | هل هو مدفوع |
| is_benefit | boolean | NOT NULL, default: false | هل هو ميزة |

### 9. AttCode (att_attcode)
| الحقل | النوع | القواعد | الوصف |
|-------|-------|---------|-------|
| id | integer | PK, auto-increment | المعرف |
| code | varchar(20) | NOT NULL, UNIQUE | الكود |
| alias | varchar(50) | NOT NULL, UNIQUE | الاسم |
| symbol | varchar(20) | NOT NULL | الرمز |
| display_format | smallint | NOT NULL, default: 0 | تنسيق العرض |

### 10. GroupPolicy (att_grouppolicy)
| الحقل | النوع | القواعد | الوصف |
|-------|-------|---------|-------|
| id | integer | PK, auto-increment | المعرف |
| group_id | integer | FK → att_attgroup.id, NOT NULL | الفئة |
| weekend1 | smallint | NOT NULL, default: 6 | يوم عطلة 1 |
| weekend2 | smallint | NOT NULL, default: 0 | يوم عطلة 2 |
| max_hrs | numeric(4,1) | NOT NULL, default: 8 | أقصى ساعات |
| daily_ot | boolean | NOT NULL, default: false | إvertime يومي |
| weekly_ot | boolean | NOT NULL, default: false | إvertime أسبوعي |
| weekend_ot | boolean | NOT NULL, default: false | إvertime عطلة نهاية الأسبوع |
| holiday_ot | boolean | NOT NULL, default: false | إvertime العطل |
| late_in2absence | integer | NOT NULL, default: 0 | تحويل التاخير لغياب |
| early_out2absence | integer | NOT NULL, default: 0 | تحويل الخروج المبكر لغياب |

### 11. DepartmentPolicy (att_departmentpolicy)
- نفس أعمدة GroupPolicy مع `department_id` بدلاً من `group_id`

### 12. DepartmentSchedule (att_departmentschedule)
| الحقل | النوع | القواعد | الوصف |
|-------|-------|---------|-------|
| id | integer | PK, auto-increment | المعرف |
| department_id | integer | FK → departments.id, NOT NULL | القسم |
| shift_id | integer | FK → att_attshift.id, NOT NULL | المناوبة |
| start_date | date | NOT NULL | تاريخ البداية |
| end_date | date | NOT NULL | تاريخ النهاية |

### 13. EmployeeSchedule (att_attschedule)
| الحقل | النوع | القواعد | الوصف |
|-------|-------|---------|-------|
| id | integer | PK, auto-increment | المعرف |
| employee_id | integer | FK → users.id, NOT NULL | الموظف |
| shift_id | integer | FK → att_attshift.id, NOT NULL | المناوبة |
| start_date | date | NOT NULL | تاريخ البداية |
| end_date | date | NOT NULL | تاريخ النهاية |

### 14. TemporarySchedule (att_temporaryschedule)
| الحقل | النوع | القواعد | الوصف |
|-------|-------|---------|-------|
| id | integer | PK, auto-increment | المعرف |
| employee_id | integer | FK → users.id, NOT NULL | الموظف |
| att_date | date | NOT NULL | التاريخ |
| time_interval_id | integer | FK → att_timeinterval.id, nullable | الفاصل الزمني |

---

## جداول الربط

### time_interval_break_time
| الحقل | النوع | القواعد |
|-------|-------|---------|
| id | integer | PK, auto-increment |
| timeinterval_id | integer | FK → att_timeinterval.id, NOT NULL |
| breaktime_id | integer | FK → att_breaktime.id, NOT NULL |

**Unique:** (timeinterval_id, breaktime_id)

---

## الفهارس المطلوبة

| الجدول | الفهرس | النوع |
|--------|--------|-------|
| att_attgroup | company_id | عادي |
| att_attgroup | (company_id, code) | فريد |
| att_attemployee | emp_id | فريد |
| att_attemployee | group_id | عادي |
| att_groupschedule | (group_id, start_date, end_date) | عادي |
| att_attshift | company_id | عادي |
| att_shiftdetail | (shift_id, day_index) | عادي |
| att_timeinterval | company_id | عادي |
| att_grouppolicy | group_id | عادي |
| att_attschedule | (employee_id, start_date, end_date) | عادي |
| att_temporaryschedule | (employee_id, att_date) | عادي |

---

*آخر تحديث: 2026-07-16*
