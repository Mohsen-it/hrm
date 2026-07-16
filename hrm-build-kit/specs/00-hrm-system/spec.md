# نظام HRM - المواصفات الكاملة للنظام
# HRM System - Complete System Specification

**الإصدار:** 3.0.0
**تاريخ الإنشاء:** 2026-07-13
**الحالة:** مواصفات كاملة لإعادة البناء
**DESIGN.md:** `specs/00-hrm-system/DESIGN.md` (نظام التصميم المعتمد)

---

## 📋 نظرة عامة

نظام متكامل لإدارة الموارد البشرية (HRM) مبني على Laravel 13 بهندسة معيارية (Modular Architecture). يدير النظام شركات متعددة مع هيكل تنظيمي كامل (فروع ➝ أقسام ➝ موظفين)، وأجهزة بصمة ZKTeco، ونظام حضور وانصراف متكامل، وإجازات، وعطل، ومناوبات، ومناطق، وإعدادات، ورواتب، وسير عمل، وزوار، واجتماعات، وتحكم بالوصول.

**إحصائيات قاعدة البيانات:**
- **100+ جدول** عبر 13 وحدة
- **أكبر جدول:** `personnel_employee` (60+ عمود) - محور النظام
- **أكثر جدول معاملات:** `iclock_transaction` (ملايين سجلات البصمة)
- **أكثر وحدة تعقيداً:** `att_` (34 جدول) - الحضور والسياسات
- **محرك سير العمل:** `workflow_` (8 جداول) يستخدم لـ: الإجازات، الـ OT، التسجيل اليدوي، تغيير الجدول، الاجتماعات

---

## 🎯 أهداف النظام

### الهدف الرئيسي
بناء نظام HRM متكامل وقابل للتطوير يدير دورة حياة الموظف بالكامل من التسجيل إلى الحضور والانصراف والإجازات.

### الأهداف الفرعية
1. إدارة الهيكل التنظيمي لشركات متعددة
2. ربط الموظفين بأجهزة البصمة لتسجيل الحضور
3. حساب ساعات العمل تلقائياً مع overtime
4. إدارة الإجازات والعطل بشكل ذكي
5. تقارير حضور شاملة (يومي، شهري، سنوي)
6. دعم ثنائي اللغة (عربي/إنجليزي) مع RTL
7. نظام صلاحيات متكامل (RBAC)

---

## 👤 قصص المستخدمين (User Stories)

### كـ Super Admin
- [ ] أستطيع إدارة الشركات والفروع والأقسام
- [ ] أستطيع إنشاء المستخدمين وتعيين الصلاحيات
- [ ] أستطيع إدارة أجهزة البصمة والمناوبات
- [ ] أستطيع عرض جميع تقارير الحضور
- [ ] أستطيع الموافقة على طلبات الإجازات
- [ ] أستطيع إدارة إعدادات النظام

### كـ Admin
- [ ] أستطيع إدارة الموظفين في شركتي
- [ ] أستطيع عرض تقارير حضور لموظفيّ
- [ ] أستطيع إدارة مناوبات الموظفين
- [ ] أستطيع إدارة أجهزة البصمة في فروعي

### كـ Manager
- [ ] أستطيع عرض تقارير حضور فريقي
- [ ] أستطيع تقديم طلبات إجازة لفريقي والموافقة عليها
- [ ] أستطيع تعديل بصمات أعضاء فريقي

### كـ Employee
- [ ] أستطيع تسجيل الدخول ومشاهدة حضوري
- [ ] أستطيع تقديم طلبات إجازة
- [ ] أستطيع مشاهدة رصيد إجازاتي

---

## 🏗️ المتطلبات التقنية

### البيئة الأساسية
| المتطلب | الإصدار |
|---------|---------|
| PHP | ^8.2 |
| Laravel | ^12.0 |
| Database | SQLite (dev) / MySQL 8.0+ (prod) |
| Node.js | ^20 |
| NPM | ^10 |
| Python (ZKTeco) | ^3.11 |

### الحزم الأساسية (Composer)
| الحزمة | الغرض |
|--------|-------|
| `laravel/framework` ^12.0 | الإطار الرئيسي |
| `nwidart/laravel-modules` ^12.0 | الهندسة المعيارية |
| `spatie/laravel-permission` ^6.21 | نظام الصلاحيات |
| `inertiajs/inertia-laravel` ^3.1 | SPA integration |
| `tightenco/ziggy` ^2.6 | مسارات JavaScript |
| `barryvdh/laravel-dompdf` ^3.1 | PDF |
| `phpoffice/phpspreadsheet` ^5.4 | Excel |
| `predis/predis` ^3.3 | Redis |

### الحزم الأمامية (NPM)
| الحزمة | الغرض |
|--------|-------|
| `vue` ^3.5 | إطار العمل الأمامي (SPA كامل) |
| `@inertiajs/vue3` ^3.6 | Inertia Vue adapter |
| `@vitejs/plugin-vue` ^6.0 | Vite plugin لـ Vue |
| `tailwindcss` ^4.0 | التنسيق |
| `@tailwindcss/vite` ^4.0 | Tailwind Vite plugin |
| `vite` ^7.0 | أداة البناء |
| `ziggy-js` ^2.6 | مسارات Laravel في Vue |
| `axios` ^1.11 | HTTP client |
| `mitt` ^3.0 | Event emitter |

---

## 🧩 الوحدات (22 وحدة)

### الوحدات الأساسية (Core Modules)

#### 1. Companies - إدارة الشركات
- **الجدول:** `personnel_company`
- **المسار:** `Modules/Companies`
- **الوصف:** إدارة بيانات الشركات مع الشعار والإحداثيات
- **الحقول:** company_code, company_name, address, address2, city, country, state, postal_code, phone, fax, email, website, logo, logo_pos, name_pos, status, is_default
- **الصلاحيات:** view-companies, create-companies, edit-companies, delete-companies

#### 2. Branches - إدارة الفروع
- **الجدول:** `personnel_department` (بـ parent_dept_id → هرمي)
- **المسار:** `Modules/Branches`
- **الوصف:** إدارة الأقسام/الفروع بشكل هرمي (parent → child)
- **التبعية:** يعتمد على Companies
- **الحقول:** dept_code (unique), dept_name, parent_dept_id, dept_manager_id, company_id, is_default, status
- **الصلاحيات:** view-branches, create-branches, edit-branches, delete-branches

#### 3. Departments - إدارة الأقسام
- **الجدول:** `personnel_department` (نفس جدول Branches - هرمي)
- **المسار:** `Modules/Departments`
- **الوصف:** الأقسام هي نفس الفروع في نظام هرمي واحد (company → department → sub-department)
- **التبعية:** يعتمد على Branches (parent_dept_id)
- **الحقول:** dept_code, dept_name, parent_dept_id, dept_manager_id, company_id
- **الصلاحيات:** view-departments, create-departments, edit-departments, delete-departments

#### 4. Positions - المواقع الوظيفية
- **الجدول:** `personnel_position`
- **المسار:** `Modules/Positions`
- **الوصف:** إدارة المسميات الوظيفية (هرمية أيضاً via parent_position_id)
- **الحقول:** position_code (unique), position_name, parent_position_id, company_id, is_default
- **الصلاحيات:** view-positions, create-positions, edit-positions, delete-positions
- **ملاحظة:** يحتاج Service + Repository (موجود حالياً Controller فقط)

#### 5. Grades - الدرجات الوظيفية
- **الجدول:** جديد (غير موجود في الـ SQL dump - يُنشأ)
- **المسار:** `Modules/Grades`
- **الوصف:** إدارة الدرجات الوظيفية
- **الحقول:** grade_code (unique), grade_name, min_salary, max_salary, company_id, status
- **الصلاحيات:** view-grades, create-grades, edit-grades, delete-grades

#### 6. Shifts - إدارة المناوبات
- **الجدول:** `att_attshift` + `att_shiftdetail` + `att_breaktime` + `att_timeinterval`
- **المسار:** `Modules/Shifts`
- **الوصف:** إدارة المناوبات مع فترات الراحة والتفاصيل
- **الحقول (att_attshift):** alias, cycle_unit, shift_cycle, work_weekend, weekend_type, work_day_off, day_off_type, auto_shift, enable_ot_rule, frequency, ot_rule, company_id
- **الحقول (att_shiftdetail):** in_time, out_time, day_index, shift_id, time_interval_id
- **الحقول (att_breaktime):** alias, period_start, duration, end_margin, calc_type, company_id
- **الصلاحيات:** view-shifts, create-shifts, edit-shifts, delete-shifts

#### 7. ShiftRotation - تناوب المناوبات
- **الجدول:** `att_tempschedule` + `att_changeschedule`
- **المسار:** `Modules/ShiftRotation`
- **الوصف:** نظام تناوب المناوبات للموظفين (جدول مؤقت أو تغيير جدول)
- **التبعية:** يعتمد على Shifts, Users

#### 8. Users - إدارة الموظفين
- **الجدول:** `personnel_employee` (50+ عمود - أغنى جدول في النظام)
- **المسار:** `Modules/Users`
- **الوصف:** إدارة المستخدمين والموظفين - الوحدة المركزية
- **التبعية:** يعتمد على Companies, Departments, Positions
- **الحقول:** emp_code (unique), first_name, last_name, nickname, gender, birthday, address, city, postcode, mobile, phone, email, passport, ssn, driver_licenses, religion, title, photo, hire_date, emp_type, status, is_active, enable_payroll, device_password, card_no, acc_group, verify_mode, enroll_sn, company_id, department_id, position_id, superior_id, leave_group, emp_code_digit
- **الصلاحيات:** view-users, create-users, edit-users, delete-users
- **ملاحظة:** هذا الجدول يربط كل شيء - الموظف هو محور النظام

### وحدات الحضور والأجهزة

#### 9. Attendance - نظام الحضور
- **الجداول:** `att_attemployee`, `att_attgroup`, `att_attschedule`, `att_temporaryschedule`, `iclock_transaction`, `att_manuallog`, `att_webpunch`, `att_attpolicy`, `att_departmentpolicy`, `att_grouppolicy`, `att_attcode`, `att_paycode`, `att_attendance` (محسوب)
- **المسار:** `Modules/Attendance`
- **الوصف:** نظام الحضور والانصراف الكامل مع السياسات والحسابات والتقارير
- **التبعية:** يعتمد على Users, Shifts
- **الخدمات (11):** AttendanceSessionService, DailyAttendanceSummaryService, MonthlyReportService, YearlyReportService, AttendanceReportService, AttendanceCacheService, AttendanceMonitoringService, AttendanceNotificationService, RawAttendanceLogService, AttendanceSessionTypeService, DailyAttendanceAutoCalculationService
- **الصلاحيات:** view-attendance, process-attendance, export-attendance

#### 10. FingerprintDevices - أجهزة البصمة (اتصال IP/Ethernet)
- **الجداول:** `iclock_terminal`, `iclock_biodata`, `iclock_biophoto`, `iclock_terminalemployee`, `iclock_terminalparameter`, `iclock_terminalworkcode`, `iclock_transaction`, `iclock_terminallog`, `iclock_devicemoduleconfig`
- **المسار:** `Modules/FingerprintDevices`
- **الوصف:** إدارة أجهزة ZKTeco للبصمة مع البيانات البيومترية — اتصال عبر IP/Ethernet (TCP/IP port 4370)
- **التبعية:** يعتمد على Companies
- **الحقول (iclock_terminal - 50+):** sn (unique), alias, ip_address (IP ثابت), real_ip, state, terminal_tz, heartbeat, transfer_mode, product_type, is_attendance, authentication, push_protocol, fw_ver, platform, user_count, transaction_count, fp_count, face_count, last_activity, area_id
- **إعدادات الشبكة الإلزامية:** كل جهاز يحتاج IP ثابت (Static IP) في نفس شبكة السيرفر، Port 4370 مفتوح، كلمة مرور الجهاز
- **الحقول (iclock_biodata):** employee_id, bio_tmp (Base64), bio_no, bio_index, bio_type (FP/Face/FV), major_ver, minor_ver, valid, duress, sn
- **استيراد/تصدير:**
  - **سحب الحضور:** `zkteco:pull-attendance` → Python Flask → Device (TCP 4370) → `iclock_transaction`
  - **دفع الموظفين:** `zkteco:push-users` → Python Flask → Device (إضافة/تحديث موظف)
  - **سحب البصمات:** `zkteco:pull-templates` → Python Flask → Device → `iclock_biodata`
  - **رفع البصمات:** `zkteco:push-templates` → Python Flask → Device (رفع قالب بصمة)
  - **ADMS Push (实时):** Device → Python TCP Server (8081) → `iclock_transaction`
  - **Queue Jobs:** `ProcessAttendanceLogsJob`, `PushUserToDeviceJob`, `PushFingerprintToDeviceJob`
  - **جدول زمني:** `zkteco:pull-attendance` كل 10 دقائق (Cron)
- **الصلاحيات:** view-fingerprint-devices, manage-fingerprint-devices, pull-attendance-from-devices, push-users-to-devices, manage-fingerprint-templates

### وحدات الوقت والإجازات

#### 11. Holidays - العطل
- **الجدول:** `att_holiday`
- **المسار:** `Modules/Holidays`
- **الوصف:** إدارة العطل الرسمية والمتكررة
- **الحقول:** alias, start_date, end_date, duration_day, enable_ot_rule, ot_rule, department_id, att_group_id, color_setting
- **الصلاحيات:** view-holidays, create-holidays, edit-holidays, delete-holidays

#### 12. Vacations - الإجازات
- **الجداول:** `att_leave` (extends WorkflowInstance), `att_leaveyearbalance`, `att_leavegroup`, `att_leavegroupdetail`, `att_paycode`
- **المسار:** `Modules/Vacations`
- **الوصف:** نظام إدارة الإجازات والرصيد عبر محرك سير العمل
- **التبعية:** يعتمد على Users, Workflow
- **الحقول (att_leave):** start_time, end_time, apply_reason, attachment, pay_code_id, leave_day + (workflowinstance: approval_status, approval_remark)
- **الحقول (att_leaveyearbalance):** leave_type, year, entitlement_days, leave_day, pre_balance, employee_id, pay_code_id
- **الصلاحيات:** view-vacations, create-vacations, approve-vacation-requests

### وحدات إضافية

#### 13. Settings - الإعدادات
- **الجداول:** `base_systemsetting` (key-value), `base_sysparam`
- **المسار:** `Modules/Settings`
- **الوصف:** إعدادات النظام العامة
- **الصلاحيات:** view-settings, edit-settings

#### 14. Zones - المناطق
- **الجدول:** `personnel_area` (تسلسل هرمي عبر parent_area_id)
- **المسار:** `Modules/Zones`
- **الوصف:** إدارة المناطق (للتحكم بالوصول والأجهزة) - هرمية
- **التبعية:** يعتمد على Companies
- **الحقول:** area_code (unique), area_name, parent_area_id, company_id, device_count, employee_count, is_default
- **ملاحظة:** حالياً يستخدم `Entities/` بدلاً من `Models/` - يحتاج إصلاح
- **الصلاحيات:** view-zones, create-zones, edit-zones, delete-zones

#### 15. Payroll - الرواتب
- **الجداول:** `payroll_salarystructure`, `payroll_payrollpayload`, `payroll_emploan`, `payroll_salaryadvance`, `payroll_reimbursement`, `payroll_extraincrease`, `payroll_extradeduction`, `payroll_emppayrollprofile`, `payroll_empexpenseexemption`, `payroll_deductionformula`, `payroll_overtimeformula`, `payroll_leaveformula`, `payroll_socialsecuritydeduction`, `payroll_taxdeduction`, `payroll_specialpayment`
- **المسار:** `Modules/Payroll`
- **الوصف:** نظام الرواتب والأجور مع الاستقطاعات والزيادات والقروض
- **الصلاحيات:** view-payroll, create-payroll, edit-payroll

#### 16. Visitor - الزوار
- **الجداول:** `visitor_visitor`, `visitor_visitortransaction`, `visitor_visitorlog`, `visitor_visitorbiodata`, `visitor_visitorbiophoto`, `visitor_reason`
- **المسار:** `Modules/Visitor`
- **الوصف:** إدارة زوار الشركة مع البصمات والصور والتسجيل
- **الصلاحيات:** view-visitors, create-visitors, manage-visitors

#### 17. Meeting - الاجتماعات
- **الجداول:** `meeting_meetingentity` (extends WorkflowInstance), `meeting_meetingroom`, `meeting_meetingroomdevice`, `meeting_meetingmanuallog`, `meeting_meetingpayloadbase`
- **المسار:** `Modules/Meeting`
- **الوصف:** إدارة الاجتماعات مع حجز الغرف وبصمة الحضور
- **الصلاحيات:** view-meetings, create-meetings, manage-meetings

#### 18. AccessControl - التحكم بالوصول
- **الجداول:** `acc_accgroups`, `acc_accterminal`, `acc_accprivilege`, `acc_acctimezone`, `acc_accholiday`, `acc_acccombination`
- **المسار:** `Modules/AccessControl`
- **الوصف:** نظام التحكم بالوصول للأبواب مع المناطق الزمنية والمجموعات
- **الصلاحيات:** view-access-control, manage-access-control

#### 19. Workflow - سير العمل
- **الجداول:** `workflow_workflowengine`, `workflow_workflowinstance`, `workflow_workflownode`, `workflow_workflowrole`, `workflow_nodeinstance`
- **المسار:** `Modules/Workflow`
- **الوصف:** محرك سير العمل (يستخدم للإجازات، overtime, التسجيل اليدوي، تغيير الجدول، الاجتماعات)
- **الصلاحيات:** view-workflows, create-workflows, manage-workflows

#### 20. Mobile - الجوال
- **الجداول:** `mobile_gpslocation`, `mobile_gpsforemployee`, `mobile_gpsfordepartment`, `mobile_gpsforemployee_location`, `mobile_gpsfordepartment_location`
- **المسار:** `Modules/Mobile`
- **الوصف:** API للتطبيق الجوال مع تتبع GPS
- **الصلاحيات:** mobile-access

#### 21. Sync - المزامنة
- **الجداول:** `sync_employee`, `sync_department`, `sync_area`, `sync_job`
- **المسار:** `Modules/Sync`
- **الوصف:** مزامنة البيانات مع الأنظمة الخارجية (Active Directory, ERP, إلخ)

#### 22. Fingerprints - البصمات
- **الملاحظة:** البصمات موجودة فعلياً في `iclock_biodata` و `iclock_biophoto` ضمن FingerprintDevices

---

## 🗄️ قاعدة البيانات

**المصدر:** مستخرج من قاعدة بيانات PostgreSQL الفعلية (`20260624212228 .sql`)
**عدد الجداول:** 100+ جدول عبر 13 وحدة
**نوع قاعدة البيانات:** PostgreSQL (للإنتاج) → SQLite (للتطوير) → MySQL 8.0+ (اختياري)

### 📌 ملخص الوحدات والجداول

| الوحدة | البادئة | عدد الجداول | الوصف |
|--------|---------|-------------|-------|
| الهيكل التنظيمي | `personnel_` | 14 | company, department, employee, position, area, employment, resign |
| الحضور | `att_` | 34 | shift, schedule, attendance, leave, overtime, paycode, policy, report |
| أجهزة البصمة | `iclock_` | 13 | terminal, transaction, biodata, biophoto, logs, parameters |
| الرواتب | `payroll_` | 19 | salary structure, payroll payload, loans, formulas |
| سير العمل | `workflow_` | 8 | engine, instance, node, role, approval |
| الزوار | `visitor_` | 10 | visitor, transaction, logs, biodata, biophoto |
| الاجتماعات | `meeting_` | 4 | meeting entity, room, room device |
| التحكم بالوصول | `acc_` | 6 | access groups, terminal, privilege, timezone |
| الجوال | `mobile_` | 5 | GPS location, GPS employee/department |
| المزامنة | `sync_` | 4 | sync employee, department, area, job |
| الصلاحيات | `auth_` | 8 | user, group, permission |
| الإعدادات | `base_` | 21 | system settings, sysparam, admin log, email, alerts |
| أخرى | `django_`, `rest_` | 6 | migrations, content types, sessions, API logs |

### الجداول الأساسية (Core Structure)

#### personnel_company
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| company_code | varchar(50) UNIQUE | كود الشركة |
| company_name | varchar(100) | اسم الشركة |
| is_default | boolean | شركة افتراضية |
| address | varchar(200) | العنوان |
| address2 | varchar(200) | عنوان إضافي |
| city | varchar(10) | المدينة |
| country | varchar(10) | الدولة |
| state | varchar(20) | المنطقة |
| postal_code | varchar(20) | الرمز البريدي |
| phone | varchar(20) | الهاتف |
| fax | varchar(20) | الفاكس |
| email | varchar(50) | البريد |
| website | varchar(50) | الموقع |
| logo | varchar(200) | الشعار |
| logo_pos | smallint | موضع الشعار |
| name_pos | smallint | موضع الاسم |
| status | smallint | الحالة (1=نشط) |
| create_time | timestamp | تاريخ الإنشاء |
| change_time | timestamp | تاريخ التعديل |

#### personnel_department
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| dept_code | varchar(50) UNIQUE | كود القسم |
| dept_name | varchar(200) | اسم القسم |
| is_default | boolean | قسم افتراضي |
| parent_dept_id | integer FK → personnel_department.id | القسم الأب (تسلسل هرمي) |
| dept_manager_id | integer FK → personnel_employee.id | مدير القسم |
| company_id | integer FK → personnel_company.id | الشركة |

#### personnel_position
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| position_code | varchar(50) UNIQUE | كود المسمى |
| position_name | varchar(100) | اسم المسمى |
| is_default | boolean | افتراضي |
| parent_position_id | integer FK → personnel_position.id | المسمى الأب |
| company_id | integer FK → personnel_company.id | الشركة |

#### personnel_area (Zones)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| area_code | varchar(30) UNIQUE | كود المنطقة |
| area_name | varchar(100) | اسم المنطقة |
| is_default | boolean | افتراضي |
| parent_area_id | integer FK → personnel_area.id | المنطقة الأب (تسلسل هرمي) |
| company_id | integer FK → personnel_company.id | الشركة |
| device_count | integer | عدد الأجهزة |
| employee_count | integer | عدد الموظفين |

#### personnel_employee (الموظفون - 50+ عمود)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| emp_code | varchar(20) UNIQUE | كود الموظف |
| first_name | varchar(100) | الاسم الأول |
| last_name | varchar(100) | اسم العائلة |
| nickname | varchar(100) | الاسم المستعار |
| gender | varchar(1) | الجنس (M/F) |
| birthday | date | تاريخ الميلاد |
| address | varchar(200) | العنوان |
| city | varchar(20) | المدينة |
| postcode | varchar(10) | الرمز البريدي |
| phone/office_tel/contact_tel | varchar(20) | أرقام الهاتف |
| mobile | varchar(20) | الجوال |
| email | varchar(50) | البريد الإلكتروني |
| passport | varchar(30) | جواز السفر |
| ssn | varchar(20) | الرقم الوطني |
| driver_license_automobile | varchar(30) | رخصة قيادة |
| driver_license_motorcycle | varchar(30) | رخصة دراجة |
| religion | varchar(20) | الديانة |
| title | varchar(20) | اللقب |
| photo | varchar(200) | الصورة |
| hire_date | date | تاريخ التوظيف |
| emp_type | smallint | نوع الموظف |
| status | smallint | الحالة (1=نشط) |
| is_active | boolean | نشط |
| enable_payroll | boolean | راتب مفعل |
| self_password | varchar(128) | كلمة المرور |
| device_password | varchar(20) | كلمة جهاز البصمة |
| dev_privilege | integer | صلاحية الجهاز |
| card_no | varchar(20) | رقم البطاقة |
| acc_group | varchar(5) | مجموعة تحكم الوصول |
| acc_timezone | varchar(20) | منطقة تحكم الوصول |
| verify_mode | integer | وضع التحقق |
| enroll_sn | varchar(20) | رقم التسجيل |
| company_id | integer FK | الشركة |
| department_id | integer FK → personnel_department.id | القسم |
| position_id | integer FK → personnel_position.id | المسمى الوظيفي |
| superior_id | integer FK → personnel_employee.id | المشرف المباشر |
| leave_group | integer FK → att_leavegroup.id | مجموعة الإجازات |
| emp_code_digit | bigint | كود رقمي (للبصمة) |
| last_login | timestamp | آخر دخول |
| login_ip | varchar(32) | IP آخر دخول |
| session_key | varchar(32) | مفتاح الجلسة |
| app_status | smallint | حالة التطبيق |
| app_role | smallint | دور التطبيق |
| create_time/change_time/update_time | timestamp | أوقات الإنشاء/التعديل |

#### personnel_employment
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| employment_type | smallint | نوع التوظيف |
| start_date | date | تاريخ البداية |
| end_date | date | تاريخ النهاية |
| active_time | timestamp | وقت التفعيل |
| inactive_time | timestamp | وقت الإنهاء |
| employee_id | integer FK | الموظف |

#### personnel_resign
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| resign_date | date | تاريخ الاستقالة |
| resign_type | integer | نوع الاستقالة |
| disableatt | boolean | تعطيل الحضور |
| reason | varchar(200) | السبب |
| employee_id | integer FK | الموظف |

### جداول الحضور (Attendance - 34 جدول)

#### att_attshift (المناوبات)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| alias | varchar(50) | اسم المناوبة |
| cycle_unit | smallint | وحدة الدورة (يوم/أسبوع/شهر) |
| shift_cycle | integer | دورة المناوبة (أيام) |
| work_weekend | boolean | دوام في عطلة نهاية الأسبوع |
| weekend_type | smallint | نوع العطلة الأسبوعية |
| work_day_off | boolean | دوام في يوم الإجازة |
| day_off_type | smallint | نوع يوم الإجازة |
| auto_shift | smallint | مناوبة تلقائية |
| enable_ot_rule | boolean | تفعيل حساب OT |
| frequency | smallint | التكرار |
| ot_rule | uuid | قاعدة OT |
| company_id | integer FK | الشركة |

#### att_shiftdetail (تفاصيل المناوبة)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| in_time | time | وقت الحضور |
| out_time | time | وقت الانصراف |
| day_index | integer | ترتيب اليوم في الدورة |
| shift_id | integer FK → att_attshift.id | المناوبة |
| time_interval_id | integer FK → att_timeinterval.id | الفترة الزمنية |

#### att_breaktime (فترات الراحة)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| alias | varchar(50) | الاسم |
| period_start | time | وقت البداية |
| duration | integer | المدة (دقائق) |
| end_margin | integer | هامش النهاية |
| calc_type | smallint | نوع الحساب |
| minimum_duration | integer | الحد الأدنى |
| multiple_punch | smallint | بصمة متعددة |
| profit_rule | boolean | قاعدة الربح |
| loss_rule | boolean | قاعدة الخسارة |
| company_id | integer FK | الشركة |

#### att_attgroup (مجموعات الحضور)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| code | varchar(50) UNIQUE | الكود |
| name | varchar(100) | الاسم |
| company_id | integer FK | الشركة |

#### att_attemployee (إعدادات حضور الموظف)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| emp_id | integer FK → personnel_employee.id | الموظف |
| group_id | integer FK → att_attgroup.id | مجموعة الحضور |
| enable_attendance | boolean | تفعيل الحضور |
| enable_schedule | boolean | تفعيل الجدول |
| enable_overtime | boolean | تفعيل OT |
| enable_holiday | boolean | تفعيل الإجازات |
| enable_compensatory | boolean | تفعيل التعويض |
| ip_address | inet | IP مسموح |

#### att_attschedule (جدول المناوبات)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| start_date | date | تاريخ البداية |
| end_date | date | تاريخ النهاية |
| employee_id | integer FK | الموظف |
| shift_id | integer FK → att_attshift.id | المناوبة |

#### att_temporaryschedule (جدول مؤقت)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| att_date | date | التاريخ |
| employee_id | integer FK | الموظف |
| time_interval_id | integer FK → att_timeinterval.id | الفترة الزمنية |

#### att_holiday (العطل)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| alias | varchar(50) | اسم العطلة |
| start_date | date | تاريخ البداية |
| end_date | date | تاريخ النهاية |
| duration_day | smallint | المدة (أيام) |
| enable_ot_rule | boolean | تفعيل OT |
| ot_rule | uuid | قاعدة OT |
| department_id | integer FK → personnel_department.id | القسم (اختياري) |
| att_group_id | integer FK → att_attgroup.id | مجموعة الحضور |
| color_setting | varchar(30) | لون العرض |

#### att_leave (الإجازات - extends Workflow)
| العمود | النوع | الوصف |
|--------|------|-------|
| workflowinstance_ptr_id | integer PK FK → workflow_workflowinstance.id | معرف سير العمل |
| start_time | timestamp | وقت البداية |
| end_time | timestamp | وقت النهاية |
| apply_reason | text | سبب الطلب |
| apply_time | timestamp | وقت الطلب |
| attachment | varchar(100) | المرفق |
| pay_code_id | integer FK → att_paycode.id | كود الدفع |
| leave_day | double precision | عدد أيام الإجازة |

#### att_leaveyearbalance (رصيد الإجازات السنوي)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| leave_type | integer | نوع الإجازة |
| year | integer | السنة |
| entitlement_days | smallint | الأيام المستحقة |
| leave_day | double precision | الأيام المتبقية |
| pre_balance | smallint | الرصيد السابق |
| employee_id | integer FK | الموظف |
| pay_code_id | integer FK → att_paycode.id | كود الدفع |

#### att_overtime (العمل الإضافي - extends Workflow)
| العمود | النوع | الوصف |
|--------|------|-------|
| workflowinstance_ptr_id | integer PK FK | معرف سير العمل |
| overtime_type | smallint | نوع OT |
| start_time | timestamp | وقت البداية |
| end_time | timestamp | وقت النهاية |
| apply_reason | text | السبب |
| attachment | varchar(100) | المرفق |
| pay_code_id | integer FK → att_paycode.id | كود الدفع |

#### att_manuallog (تسجيل يدوي)
| العمود | النوع | الوصف |
|--------|------|-------|
| workflowinstance_ptr_id | integer PK FK | معرف سير العمل |
| punch_time | timestamp | وقت البصمة |
| punch_state | varchar(5) | نوع البصمة (CheckIn/CheckOut) |
| work_code_id | integer FK | كود العمل |
| apply_reason | text | السبب |
| apply_time | timestamp | وقت الطلب |
| attachment | varchar(100) | المرفق |

#### att_paycode (أكواد الدفع)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| code | varchar(20) UNIQUE | الكود |
| name | varchar(50) | الاسم |
| code_type | smallint | النوع |
| is_work | boolean | دوام |
| is_paid | boolean | مدفوع |
| is_benefit | boolean | علاوة |
| fixed_hours | numeric(8,2) | ساعات ثابتة |
| tag | smallint | علامة |
| color_setting | varchar(30) | لون العرض |

### جداول أجهزة البصمة (iClock - 13 جدول)

#### iclock_terminal (الأجهزة - 50+ عمود)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| sn | varchar(50) UNIQUE | الرقم التسلسلي |
| alias | varchar(50) | الاسم المخصص |
| ip_address | inet | عنوان IP |
| real_ip | inet | IP الحقيقي |
| state | integer | الحالة (0=غير متصل, 1=متصل) |
| terminal_tz | smallint | المنطقة الزمنية |
| heartbeat | integer | معدل نبضات القلب (ثواني) |
| transfer_mode | smallint | وضع النقل |
| transfer_interval | integer | فترة النقل |
| transfer_time | varchar(100) | وقت النقل |
| product_type | smallint | نوع المنتج |
| is_attendance | smallint | جهاز حضور |
| is_registration | smallint | جهاز تسجيل |
| purpose | smallint | الغرض |
| authentication | smallint | طريقة التحقق |
| push_protocol | varchar(50) | بروتوكول الدفع |
| push_ver | varchar(50) | إصدار الدفع |
| fw_ver | varchar(100) | إصدار الـ Firmware |
| platform | varchar(30) | المنصة |
| oem_vendor | varchar(50) | الشركة المصنعة |
| user_count | integer | عدد المستخدمين |
| transaction_count | integer | عدد المعاملات |
| fp_count | integer | عدد البصمات |
| face_count | integer | عدد الوجوه |
| lock_func | smallint | وظيفة القفل |
| last_activity | timestamp | آخر نشاط |
| upload_time | timestamp | وقت الرفع |
| push_time | timestamp | وقت الدفع |
| area_id | integer FK → personnel_area.id | المنطقة |

#### iclock_transaction (سجلات البصمة)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| emp_code | varchar(20) | كود الموظف |
| punch_time | timestamp | وقت البصمة |
| punch_state | varchar(5) | الحالة (0=دخول, 1=خروج) |
| verify_type | integer | نوع التحقق |
| work_code | varchar(20) | كود العمل |
| terminal_sn | varchar(50) | الرقم التسلسلي للجهاز |
| terminal_alias | varchar(50) | اسم الجهاز |
| area_alias | varchar(100) | اسم المنطقة |
| longitude/latitude | double precision | الإحداثيات |
| gps_location | text | موقع GPS |
| mobile | varchar(50) | رقم الجوال |
| source | smallint | المصدر |
| temperature | numeric(4,1) | درجة الحرارة |
| is_mask | smallint | كمامة |
| emp_id | integer FK | الموظف |
| terminal_id | integer FK | الجهاز |
| company_code | varchar(50) | كود الشركة |
| upload_time | timestamp | وقت التحميل |
| sync_status | smallint | حالة المزامنة |

#### iclock_biodata (بصمات الأصابع)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| employee_id | integer FK | الموظف |
| bio_tmp | text TEXT | قالب البصمة (Base64) |
| bio_no | integer | رقم البصمة |
| bio_index | integer | فهرس البصمة |
| bio_type | integer | نوع البصمة (FP=1, Face=2, etc.) |
| bio_format | integer | التنسيق |
| major_ver | varchar(30) | الإصدار الرئيسي |
| minor_ver | varchar(30) | الإصدار الفرعي |
| valid | integer | صالحة |
| duress | integer | إكراه |
| sn | varchar(50) | الرقم التسلسلي للجهاز |
| update_time | timestamp | وقت التحديث |

#### iclock_biophoto (صور الوجه)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| employee_id | integer FK | الموظف |
| first_name/last_name | varchar(100) | الاسم |
| email | varchar(254) | البريد |
| enroll_sn | varchar(50) | رقم التسجيل |
| register_photo | varchar(100) | صورة التسجيل |
| register_time | timestamp | وقت التسجيل |
| approval_photo | varchar(100) | صورة الموافقة |
| approval_state | smallint | حالة الموافقة |
| approval_time | timestamp | وقت الموافقة |

#### iclock_terminalemployee (ربط موظف-جهاز)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| terminal_sn | varchar(50) | الجهاز |
| emp_code | varchar(20) | كود الموظف |
| privilege | smallint | الصلاحية على الجهاز |

#### iclock_terminalparameter (إعدادات الجهاز)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| param_type | varchar(10) | نوع المعامل |
| param_name | varchar(30) | اسم المعامل |
| param_value | varchar(100) | القيمة |
| terminal_id | integer FK | الجهاز |

#### iclock_terminalworkcode (أكواد العمل على الجهاز)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| code | varchar(8) UNIQUE | الكود |
| alias | varchar(24) | الاسم |
| last_activity | timestamp | آخر نشاط |
| company_id | integer FK | الشركة |
| pay_code_id | integer FK | كود الدفع |

### جداول سير العمل (Workflow - 8 جداول)

#### workflow_workflowengine (محرك سير العمل)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| workflow_code | varchar(50) UNIQUE | كود سير العمل |
| workflow_name | varchar(50) | الاسم |
| start_date | date | تاريخ البداية |
| end_date | date | تاريخ النهاية |
| description | varchar(50) | الوصف |
| workflow_type | smallint | النوع |
| applicant_position_id | integer FK | المسمى الوظيفي للمتقدم |
| departments_id | integer FK → personnel_department.id | القسم |
| is_leave | boolean | إجازة |
| leave_type_id | integer FK → att_paycode.id | نوع الإجازة |
| company_id | integer FK | الشركة |

#### workflow_workflowinstance (مثيل سير العمل)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| approval_status | smallint | حالة الموافقة (0=قيد الانتظار, 1=موافق, -1=مرفوض) |
| approval_time | timestamp | وقت الموافقة |
| approval_remark | text | ملاحظات الموافقة |
| approver | varchar(30) | الموافق |
| approver_instance | text | معلومات الموافق |
| employee_id | integer FK | الموظف مقدم الطلب |
| workflow_engine_id | integer FK → workflow_workflowengine.id | المحرك |

#### workflow_workflownode (عقدة سير العمل)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| node_name | varchar(30) | اسم العقدة |
| order_id | integer | الترتيب |
| approver_by_overall | boolean | موافقة شاملة |
| notify_by_overall | boolean | إشعار شامل |
| workflow_engine_id | integer FK | المحرك |
| from_day | integer | من يوم |
| to_day | integer | إلى يوم |

#### workflow_nodeinstance (مثيل العقدة)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| node_name | varchar(30) | اسم العقدة |
| order_id | integer | الترتيب |
| approval_status | smallint | حالة الموافقة |
| approval_time | timestamp | وقت الموافقة |
| approval_remark | varchar(255) | ملاحظات |
| active | boolean | نشط |
| targeted | boolean | مستهدف |
| approver_employee_id | integer FK | الموافق |
| workflow_instance_id | integer FK | مثيل سير العمل |
| workflow_node_id | integer FK | العقدة |

#### workflow_workflowrole (أدوار سير العمل)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| role_code | varchar(30) UNIQUE | كود الدور |
| role_name | varchar(50) | اسم الدور |
| description | varchar(200) | الوصف |
| parent_role_id | integer FK → workflow_workflowrole.id | الدور الأب |
| company_id | integer FK | الشركة |

### جداول الرواتب (Payroll - 19 جدول)

#### payroll_salarystructure (هيكل الراتب)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| salary_amount | double precision | مبلغ الراتب |
| effective_date | date | تاريخ التفعيل |
| salary_remark | varchar(300) | ملاحظات |
| employee_id | integer FK | الموظف |

#### payroll_payrollpayload (الراتب المحسوب)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| employee_id | integer FK | الموظف |
| calc_time | date | وقت الحساب |
| basic_salary | double precision | الراتب الأساسي |
| effective_date | date | تاريخ التفعيل |
| increase | double precision | الزيادات |
| deduction | double precision | الخصومات |
| extra_increase | double precision | زيادات إضافية |
| extra_deduction | double precision | خصومات إضافية |
| total_loan_amount | double precision | إجمالي القروض |
| refund_loan_amount | double precision | أقساط القروض |
| loan_deduction | double precision | خصم القرض |
| advance_increase/deduction | double precision | سلفة |
| reimbursement | double precision | تعويضات |
| social_security_deduction | double precision | تأمينات |
| tax_deduction | double precision | ضرائب |
| total_increase | double precision | إجمالي الزيادات |
| total_deduction | double precision | إجمالي الخصومات |
| total_salary | double precision | صافي الراتب |
| net_pay | double precision | صافي الدفع |

#### payroll_emploan (قروض الموظفين)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| employee_id | integer FK | الموظف |
| loan_amount | double precision | مبلغ القرض |
| loan_time | timestamp | وقت القرض |
| refund_cycle | smallint | دورة السداد |
| per_cycle_refund | double precision | القسط لكل دورة |
| loan_clean_time | timestamp | وقت التسوية |

### جداول الزوار (Visitor - 10 جداول)

#### visitor_visitor
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| visitor_code | varchar(20) UNIQUE | كود الزائر |
| first_name/last_name | varchar(25) | الاسم |
| cert_no | varchar(50) | رقم الهوية |
| cert_type_id | integer FK | نوع الهوية |
| photo | varchar(200) | الصورة |
| password | varchar(20) | كلمة المرور |
| card_no | varchar(20) | رقم البطاقة |
| gender | varchar(1) | الجنس |
| company | varchar(100) | الشركة |
| mobile | varchar(20) | الجوال |
| email | varchar(50) | البريد |
| visit_quantity | integer | عدد الزوار |
| start_time/end_time | timestamp | وقت الزيارة |
| exit_time | timestamp | وقت المغادرة |
| entry_carrying_goods | varchar(200) | الأغراض عند الدخول |
| exit_carrying_goods | varchar(200) | الأغراض عند الخروج |
| visit_reason_id | integer FK | سبب الزيارة |
| visit_department_id | integer FK | القسم المزار |
| visited_id | integer FK → personnel_employee.id | الموظف المزار |

#### visitor_visitortransaction (بصمات الزوار)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| visitor_id | integer FK | الزائر |
| visitor_code | varchar(50) | كود الزائر |
| area | varchar(30) | المنطقة |
| punch_time | timestamp | وقت البصمة |
| punch_state | varchar(5) | الحالة |
| verify_type | integer | نوع التحقق |
| temperature | numeric(4,1) | درجة الحرارة |
| is_mask | integer | كمامة |
| terminal_sn/alias | varchar(50) | الجهاز |
| terminal_id | integer FK | الجهاز |

### جداول الاجتماعات (Meeting - 4 جداول)

#### meeting_meetingentity
| العمود | النوع | الوصف |
|--------|------|-------|
| workflowinstance_ptr_id | integer PK FK | سير العمل |
| code | varchar(32) UNIQUE | كود الاجتماع |
| alias | varchar(50) | الاسم |
| content | varchar(200) | المحتوى |
| meeting_date | date | التاريخ |
| start_time/end_time | timestamp | الوقت |
| duration | integer | المدة |
| in_required/out_required | boolean | بصمة دخول/خروج مطلوبة |
| room_id | integer FK → meeting_meetingroom.id | الغرفة |
| apply_reason | varchar(200) | سبب الطلب |

#### meeting_meetingroom
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| code | varchar(32) UNIQUE | كود الغرفة |
| alias | varchar(50) | اسم الغرفة |
| capacity | integer | السعة |
| location | varchar(200) | الموقع |
| state | smallint | الحالة |
| enable_room | boolean | تفعيل الغرفة |

### جداول التحكم بالوصول (Access Control - 6 جداول)

#### acc_accgroups (مجموعات الوصول)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| group_no | integer UNIQUE | رقم المجموعة |
| group_name | varchar(100) | الاسم |
| verify_mode | integer | وضع التحقق |
| timezone1/2/3 | integer FK → acc_acctimezone.id | مناطق زمنية |
| area_id | integer FK → personnel_area.id | المنطقة |

#### acc_accterminal (إعدادات باب الجهاز)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| terminal_id | integer FK → iclock_terminal.id | الجهاز |
| door_name | varchar(50) | اسم الباب |
| door_lock_delay | integer | تأخير القفل |
| door_sensor_delay | integer | تأخير الحساس |
| retry_times | smallint | عدد محاولات إعادة المحاولة |
| anti_passback_mode | smallint | وضع منع العودة |
| alarm settings | smallint | إعدادات الإنذار |

#### acc_acctimezone (المناطق الزمنية للأبواب)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| timezone_no | integer UNIQUE | رقم المنطقة |
| timezone_name | varchar(100) | الاسم |
| sun_start/end | time | الأحد |
| mon_start/end | time | الاثنين |
| tue_start/end | time | الثلاثاء |
| wed_start/end | time | الأربعاء |
| thu_start/end | time | الخميس |
| fri_start/end | time | الجمعة |
| sat_start/end | time | السبت |
| area_id | integer FK → personnel_area.id | المنطقة |

### جداول الجوال (Mobile - GPS)

#### mobile_gpslocation
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| alias | varchar(100) | الاسم |
| location | text | الوصف |
| longitude | double precision | خط الطول |
| latitude | double precision | خط العرض |

#### mobile_gpsforemployee
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| employee_id | integer FK | الموظف |
| distance | integer | المسافة المسموحة (متر) |
| start_date/end_date | date | تاريخ التفعيل |

### جداول المزامنة (Sync)

#### sync_employee
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| emp_code | varchar(20) | كود الموظف |
| first_name/last_name | varchar(100) | الاسم |
| dept_code/name | varchar | القسم |
| job_code/name | varchar | المسمى |
| area_code/name | varchar | المنطقة |
| card_no | varchar(20) | البطاقة |
| hire_date | date | تاريخ التوظيف |
| active_status | boolean | الحالة |
| flag | smallint | علامة المزامنة |
| sync_ret | varchar(200) | نتيجة المزامنة |

### جداول الإعدادات (Base)

#### base_systemsetting (key-value)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| name | varchar(30) UNIQUE | المفتاح |
| value | text | القيمة |

#### base_sysparam (معاملات النظام)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| para_name | varchar(30) | اسم المعامل |
| para_type | varchar(10) | النوع |
| para_value | varchar(250) | القيمة |

### الخريطة الكاملة للعلاقات

```
personnel_company
├── personnel_department (company_id)
│   └── parent_dept_id → personnel_department.id (تسلسل هرمي)
├── personnel_employee (company_id)
├── personnel_position (company_id)
│   └── parent_position_id → personnel_position.id
├── personnel_area (company_id) ← Zones
│   └── parent_area_id → personnel_area.id (تسلسل هرمي)
├── att_attshift (company_id)
├── att_attgroup (company_id)
├── att_leavegroup (company_id)
├── workflow_workflowengine (company_id)
└── workflow_workflowrole (company_id)

personnel_department
├── dept_manager_id → personnel_employee.id
└── personnel_employee (department_id)
    ├── position_id → personnel_position.id
    ├── superior_id → personnel_employee.id
    ├── att_attemployee (emp_id)
    ├── att_attschedule (employee_id)
    ├── iclock_biodata (employee_id)
    ├── iclock_biophoto (employee_id)
    ├── att_leaveyearbalance (employee_id)
    ├── workflow_workflowinstance (employee_id)
    ├── payroll_salarystructure (employee_id)
    ├── payroll_payrollpayload (employee_id)
    ├── payroll_emploan (employee_id)
    └── visitor_visitor (visited_id)

iclock_terminal
├── iclock_transaction (terminal_id)
├── iclock_terminalparameter (terminal_id)
├── iclock_terminallog (terminal_id)
├── acc_accterminal (terminal_id)
└── personnel_area (area_id)

att_attshift
├── att_shiftdetail (shift_id) → att_timeinterval
├── att_attschedule (shift_id)
├── att_breaktime (time_interval_id via att_timeinterval_break_time)
└── att_temporaryschedule (time_interval_id)

workflow_workflowengine
├── workflow_workflowinstance (workflow_engine_id)
├── workflow_workflownode (workflow_engine_id)
├── att_leave (workflowinstance_ptr_id)
├── att_overtime (workflowinstance_ptr_id)
├── att_manuallog (workflowinstance_ptr_id)
├── att_webpunch (workflowinstance_ptr_id)
├── att_changeschedule (workflowinstance_ptr_id)
└── meeting_meetingentity (workflowinstance_ptr_id)
```

### سياسات الحضور (Attendance Policies)

#### att_attpolicy (السياسة العامة)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| use_ot | smallint | تفعيل OT |
| weekend1/weekend2 | smallint | أيام العطلة |
| start_of_week | smallint | بداية الأسبوع |
| max_hrs | numeric(4,1) | الحد الأقصى للساعات |
| day_change | time | وقت تغيير اليوم |
| paring_rule | smallint | قاعدة المزاوجة |
| punch_period | smallint | فترة البصمة |
| daily_ot / weekly_ot / weekend_ot / holiday_ot | boolean | تفعيل OT لكل نوع |
| daily_ot_rule / weekly_ot_rule / weekend_ot_rule / holiday_ot_rule | uuid | قواعد OT |
| late_in2absence | integer | التأخير → غياب (دقائق) |
| early_out2absence | integer | الخروج المبكر → غياب |
| miss_in / miss_out | smallint | بصمة مفقودة |
| late_in_hrs / early_out_hrs | integer | ساعات التأخير/الخروج المبكر |
| require_capture / require_work_code / require_punch_state | boolean | متطلبات البصمة |
| max_absent / max_early_out / max_late_in | smallint | الحدود القصوى |

#### att_departmentpolicy (سياسة القسم)
نفس حقول `att_attpolicy` ولكن مرتبطة بـ `department_id`

#### att_grouppolicy (سياسة المجموعة)
نفس حقول `att_attpolicy` ولكن مرتبطة بـ `group_id`

### جداول إضافية مهمة

#### att_attendance (جدول الحضور المحسوب)
- غير موجود في dump (محسوب وقت التشغيل من `iclock_transaction` و `att_manuallog` و `att_webpunch`)

#### auth_user (مستخدمي النظام)
| العمود | النوع | الوصف |
|--------|------|-------|
| id | integer PK | المعرف |
| username | varchar(30) UNIQUE | اسم المستخدم |
| password | varchar(128) | كلمة المرور (hashed) |
| first_name/last_name | varchar(30) | الاسم |
| email | varchar(254) | البريد |
| is_active | boolean | نشط |
| is_staff | boolean | فريق العمل |
| is_superuser | boolean | مشرف النظام |
| can_manage_all_dept | boolean | إدارة كل الأقسام |
| date_joined | timestamp | تاريخ الانضمام |
| last_login | timestamp | آخر دخول |

### فهارس الأداء (Indexes)
- جميع الـ Foreign Keys مفهرسة
- Unique indexes على: `emp_code`, `company_code`, `dept_code`, `position_code`, `area_code`, `terminal.sn`
- Composite indexes على: `(employee_id, year)` في `att_leaveyearbalance`, `(employee_id, att_date)` في `att_temporaryschedule`
- Full-text غير مستخدم (حجم البيانات متوسط)

---

## 🔐 نظام الصلاحيات

### الأدوار (Roles)
| الدور | الوصف |
|-------|-------|
| super-admin | وصول كامل للنظام |
| admin | إدارة شركته |
| manager | إدارة فريقه |
| employee | وصول أساسي |

### الصلاحيات (Permissions)
```
# CRUD عام
view-{module}      create-{module}
edit-{module}      delete-{module}

# صلاحيات خاصة بالحضور
process-attendance           معالجة الحضور
export-attendance            تصدير تقارير الحضور
approve-attendance           الموافقة على تعديل الحضور

# صلاحيات الإجازات
approve-vacation-requests    الموافقة على طلبات الإجازات
reject-vacation-requests     رفض طلبات الإجازات

# صلاحيات الأجهزة
manage-fingerprint-devices   إدارة أجهزة البصمة
pull-attendance-from-devices سحب الحضور من الأجهزة
push-users-to-devices        دفع الموظفين للأجهزة
manage-fingerprint-templates إدارة قوالب البصمات

# صلاحيات سير العمل
manage-workflows             إدارة محركات سير العمل
approve-workflow-requests    الموافقة على طلبات سير العمل

# صلاحيات الزوار
manage-visitors              إدارة الزوار
register-visitors            تسجيل الزوار

# صلاحيات الاجتماعات
manage-meeting-rooms         إدارة غرف الاجتماعات

# صلاحيات الرواتب
view-payroll                 عرض الرواتب
process-payroll              معالجة الرواتب
manage-payroll-formulas      إدارة معادلات الرواتب

# صلاحيات الجوال
mobile-access                وصول تطبيق الجوال
gps-tracking                 تتبع GPS

# صلاحيات الإعدادات
edit-system-settings         تعديل إعدادات النظام
manage-system-parameters     إدارة معاملات النظام
```

---

## 🌐 المسارات (Routes)

### المسارات العامة
```
GET  /                     → تسجيل الدخول
GET|POST /login            → Auth
POST /logout               → تسجيل الخروج
GET  /language/{locale}    → تبديل اللغة

# ZKTeco Device Push (للأجهزة، بدون auth)
GET|POST /iclock/connect.aspx
GET|POST /iclock/pushattlog.aspx
GET|POST /attrecord.aspx
POST /api/fingerprint-devices/realtime-push
```

### المسارات المحمية (auth)
```
/dashboard                 → لوحة التحكم
/companies/*               → إدارة الشركات
/branches/*                → إدارة الفروع
/departments/*             → إدارة الأقسام
/positions/*               → المواقع الوظيفية
/grades/*                  → الدرجات
/shifts/*                  → المناوبات
/users/*                   → الموظفين
/attendance/*              → الحضور
/fingerprint-devices/*     → أجهزة البصمة
/holidays/*                → العطل
/vacations/*               → الإجازات
/vacation-requests/*       → طلبات الإجازات
/settings/*                → الإعدادات
/zones/*                   → المناطق
/roles/*                   → الأدوار (super-admin)
/permissions               → الصلاحيات (super-admin)
/fingerprint-templates/*   → قوالب البصمات
```

### نمط التسمية
```
{module}.{action}  مثال: companies.index, companies.create
```

---

## 🎨 واجهة المستخدم (SPA كامل)

**نظام التصميم:** `specs/00-hrm-system/DESIGN.md` (يحتوي على التوكينز الكاملة: ألوان، خطوط، مسافات، مكونات، RTL)

### 🏛️ أنماط التصميم المعتمدة (من awesome-design-md-main)

| النمط | المصدر | الاستخدام في HRM |
|-------|--------|------------------|
| **جداول البيانات** | [Airtable](awesome-design-md-main/design-md/airtable) | `DataTable.vue` — كل قوائم الموظفين، الحضور، الرواتب |
| **البطاقات والأزرار** | [Linear.app](awesome-design-md-main/design-md/linear.app) | `StatCard.vue`, `FormModal.vue`, أزرار الإجراءات |
| **القائمة الجانبية** | [Notion](awesome-design-md-main/design-md/notion) | `Sidebar.vue` — تنقل ثابت بين 22 وحدة |
| **النماذج والإشعارات** | [Supabase](awesome-design-md-main/design-md/supabase) | `FormInput.vue`, `Alert.vue`, `Badge.vue` |

**انظر `DESIGN.md` للتوكينز الكاملة:** الألوان، الخطوط، المسافات، RTL mapping، القيم الدقيقة لجميع المكونات.

### التقنيات
- **Vue 3** ^3.5 - إطار العمل الأمامي (Composition API - إلزامي، لا Options API)
- **Inertia.js** ^3.1 - bridge بين Laravel و Vue (SPA)
- **Tailwind CSS** ^4.0 - التنسيق
- **Vite** ^7.0 - أداة البناء والتطوير
- **Font Awesome** 6 - الأيقونات (import جزئي - فقط المستخدمة)
- **Ziggy** ^2.6 - مسارات Laravel في JavaScript
- **Mitt** ^3.0 - Event emitter للتواصل بين المكونات

### المكونات المشتركة (Shared Components System)

**القانون:** لا يُسمح ببناء أي عنصر UI من الصفر أكثر من مرة. جميع المكونات أدناه إلزامية ويجب استخدامها في كل مكان.

```
resources/js/Components/
├── ui/                              ← مكونات UI قابلة لإعادة الاستخدام
│   ├── DataTable.vue                ← جدول مع sort/search/pagination/RTL
│   ├── FormInput.vue                ← حقل إدخال مع label + error
│   ├── FormSelect.vue               ← قائمة منسدلة مع label + error
│   ├── FormDatepicker.vue           ← منتقي تاريخ RTL
│   ├── FormTextarea.vue             ← حقل نص طويل
│   ├── FormModal.vue                ← نافذة منبثقة
│   ├── FormGroup.vue                ← مجموعة حقل كاملة
│   ├── ConfirmDialog.vue            ← تأكيد حذف/إجراء
│   ├── PageHeader.vue               ← عنوان الصفحة + أزرار
│   ├── Badge.vue                    ← حالة (نشط/غير نشط)
│   ├── Pagination.vue               ← ترقيم RTL
│   ├── SearchInput.vue              ← بحث مع debounce
│   ├── LoadingSpinner.vue           ← تحميل
│   ├── EmptyState.vue               ← لا توجد بيانات
│   ├── Alert.vue                    ← رسائل نجاح/خطأ/تحذير
│   ├── Breadcrumb.vue               ← مسار التنقل RTL
│   └── Tabs.vue                     ← تبويبات
├── layout/
│   ├── AppLayout.vue                ← التخطيط الرئيسي RTL
│   ├── Sidebar.vue                  ← القائمة الجانبية (ثابت)
│   └── Navbar.vue                   ← الشريط العلوي
├── LanguageSwitcher.vue             ← تبديل اللغة
└── index.js                         ← تصدير مركزي
```

### جزئيات الصفحات (Partials)
كل صفحة تستخدم `Partials/` للأجزاء المتكررة بين Create/Edit/Show:
```
resources/js/Pages/{Module}/
├── Partials/
│   ├── CompanyForm.vue              ← form fields (مشترك بين Create + Edit)
│   ├── CompanyInfo.vue              ← عرض بيانات
│   └── CompanyStats.vue             ← إحصائيات
├── Index.vue
├── Create.vue
├── Edit.vue
└── Show.vue
```

### هيكل الـ SPA الكامل
```
resources/js/
├── app.js                          ← نقطة الدخول (Inertia)
├── bootstrap.js                    ← إعداد Axios, CSRF, EventBus
├── ziggy.js                        ← مسارات (مولّدة)
├── Layouts/
│   └── AppLayout.vue               ← التخطيط الرئيسي (sidebar + navbar)
├── Pages/
│   ├── Auth/
│   │   └── Login.vue
│   ├── Dashboard.vue
│   ├── Companies/
│   │   ├── Index.vue
│   │   ├── Create.vue
│   │   ├── Edit.vue
│   │   └── Show.vue
│   ├── Branches/
│   ├── Departments/
│   ├── Users/
│   ├── Attendance/
│   ├── FingerprintDevices/
│   ├── Vacations/
│   ├── Holidays/
│   ├── Zones/
│   ├── Settings/
│   └── Roles/
├── Components/                     ← مكونات قابلة لإعادة الاستخدام
│   ├── StatCard.vue
│   ├── LanguageSwitcher.vue
│   ├── BranchBar.vue
│   └── ...
└── composables/                    ← Vue composables
    └── useTranslations.js
```

### تدفق البيانات (SPA)
```
Browser (Vue SPA)
    ↕ Inertia (JSON + HTML)
Laravel (Controllers → Inertia::render)
    ↕ Eloquent
Database
```

### اللغة الأساسية: العربية (RTL إلزامي)

#### 🌐 سياسة اللغات
- **اللغة الأساسية (Primary):** العربية - RTL إلزامي لجميع عناصر الواجهة
- **اللغة الثانوية (Secondary):** الإنجليزية (اختيارية)
- **الافتراضي:** العربية عند أول تسجيل دخول
- **التبديل:** مسموح بين العربي والإنجليزي، لكن **العربية هي الأساس**

#### 📐 قواعد RTL الصارمة

| العنصر | الاتجاه | ملاحظة |
|--------|---------|--------|
| **النصوص** | Right-to-Left | جميع النصوص العربية |
| **الجداول** | RTL | `text-align: right` + `direction: rtl` |
| **النماذج (Forms)** | RTL | Labels يمين، Inputs يمين |
| **الحقول (Inputs)** | RTL | `direction: rtl; text-align: right` |
| **Sidebar** | يمين الشاشة | عكس الـ LTR |
| **Navbar** | RTL | Logo يمين، User menu يسار |
| **Breadcrumb** | RTL | من اليمين لليسار |
| **Pagination** | RTL | الأرقام من اليمين لليسار |
| **Dropdowns** | RTL | القوائم تفتح لليسار |
| **Modals** | RTL | المحتوى من اليمين |
| **Notifications** | RTL | النصوص من اليمين |
| **Icons** | مرآة (Flip) | أيقونات الأسهم والاتجاهات تُعكس |
| **DataTables** | RTL | أعمدة من اليمين لليسار |
| **Tree/نموذج هرمي** | RTL | الفروع من اليمين لليسار |
| **التقويم (Date picker)** | RTL | أيام الأسبوع من الأحد لليمين |

#### 🛠️ تطبيق RTL في Tailwind CSS 4.3

```css
/* app.css - RTL أساسي */
@import "tailwindcss";

/* RTL Utilities */
[dir="rtl"] .rtl-flip {
    transform: scaleX(-1);  /* عكس الأيقونات */
}

[dir="rtl"] .space-x-reverse > :not([hidden]) ~ :not([hidden]) {
    --tw-space-x-reverse: 1;  /* عكس المسافات */
}
```

```javascript
// bootstrap.js - RTL إلزامي عند التحميل
document.documentElement.dir = 'rtl';
document.documentElement.lang = 'ar';

// Axios interceptor - إرسال اللغة في كل طلب
axios.defaults.headers.common['Accept-Language'] = 'ar';
```

```vue
<!-- AppLayout.vue - التخطيط الأساسي RTL -->
<template>
  <div :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'"
       :class="$page.props.locale === 'ar' ? 'font-arabic' : 'font-english'">
    <Sidebar class="right-0 left-auto" />  <!-- Sidebar يمين -->
    <main class="mr-64 ml-0">               <!-- هامش أيمن للـ sidebar -->
      <slot />
    </main>
  </div>
</template>
```

#### 📋 RTL للجداول
```vue
<!-- DataTable.vue - قالب الجدول RTL -->
<template>
  <table class="w-full text-right" dir="rtl">  <!-- text-right إلزامي -->
    <thead>
      <tr>
        <th class="text-right">#</th>          <!-- عناوين يمين -->
        <th class="text-right">{{ __('name') }}</th>
        <th class="text-right">{{ __('actions') }}</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="row in rows" :key="row.id">
        <td class="text-right">{{ row.id }}</td>
        <td class="text-right">{{ row.name }}</td>
        <td class="text-right">
          <button class="ml-2 mr-0">تعديل</Button>  <!-- ml للأزرار -->
        </td>
      </tr>
    </tbody>
  </table>
  <!-- Pagination RTL: أرقام من اليمين لليسار -->
  <Pagination :links="data.links" dir="rtl" />
</template>
```

#### 📝 RTL للنماذج
```vue
<!-- Form.vue - نموذج RTL -->
<template>
  <form dir="rtl" class="space-y-4">
    <div>
      <label class="block text-right">{{ __('name') }}</label>  <!-- Label يمين -->
      <input type="text" class="w-full text-right" dir="rtl"   <!-- Input RTL -->
             :placeholder="__('enter_name')" />
    </div>
    <div>
      <label class="block text-right">{{ __('email') }}</label>
      <input type="email" class="w-full text-right" dir="rtl"
             :placeholder="__('enter_email')" />
    </div>
    <div class="flex justify-start">  <!-- أزرار في البداية (يمين) -->
      <button type="submit">{{ __('save') }}</Button>
      <button type="reset" class="mr-2 ml-0">{{ __('cancel') }}</Button>
    </div>
  </form>
</template>
```

#### 🧭 دليل الـ Margin/Padding في RTL
```css
/* LTR → RTL Mapping */
/* LTR: mr-4 (margin-right) → RTL: ml-4 (margin-left) */
/* LTR: pl-2 (padding-left)  → RTL: pr-2 (padding-right) */
/* LTR: left-0               → RTL: right-0 */
/* LTR: text-left            → RTL: text-right */
/* LTR: border-l             → RTL: border-r */
/* LTR: rounded-l            → RTL: rounded-r */

/* استخدام utility classes الخاصة بـ Tailwind RTL */
.rtl\:right-0    /* في RTL: right: 0 */
.rtl\:left-auto  /* في RTL: left: auto */
.ltr\:left-0     /* في LTR: left: 0 (نادر الاستخدام) */
```

#### handleInertiaRequests - تمرير RTL لكل الصفحات
```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'auth' => [
            'user' => $request->user(),
        ],
        'locale' => app()->getLocale(),
        'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
        'translations' => [
            'ar' => __('messages', [], 'ar'),
        ],
    ];
}
```

#### useTranslations Composable
```javascript
// composables/useTranslations.js
export function useTranslations() {
    const page = usePage();
    const locale = computed(() => page.props.locale);
    const dir = computed(() => page.props.direction);
    const t = (key) => page.props.translations[locale.value]?.[key] || key;

    // تحديث اتجاه الصفحة عند تغيير اللغة
    watch(dir, (newDir) => {
        document.documentElement.dir = newDir;
        document.documentElement.lang = locale.value;
    }, { immediate: true });

    return { t, locale, dir };
}
```

### التخطيط (Layout)
```
AppLayout.vue (RTL)
├── Sidebar (يمين الشاشة - ثابت)
│   ├── Logo (يمين)
│   ├── روابط الوحدات (محاذاة لليمين)
│   │   ├── Dashboard
│   │   ├── Companies
│   │   ├── Branches → Departments → Users
│   │   ├── Attendance
│   │   └── ...
│   ├── LanguageSwitcher
│   └── Footer
├── Navbar (علوي)
│   ├── Breadcrumb (RTL)
│   ├── Notifications (RTL)
│   └── User Menu (RTL)
└── Main Content (RTL)
    ├── Page Header (يمين)
    ├── Content (يمين)
    └── Footer (يمين)
```

### معايير الـ SPA + RTL
- [ ] لا reload للصفحة عند التنقل بين الوحدات
- [ ] Sidebar ثابت لا يعاد تحميله
- [ ] شريط تقدم (Progress bar)
- [ ] CSRF محمي عبر Inertia
- [ ] lazy loading للمكونات
- [ ] **جميع الصفحات RTL بشكل افتراضي**
- [ ] **جميع الجداول `text-right` + `dir="rtl"`**
- [ ] **جميع النماذج Labels يمين + Inputs RTL**
- [ ] **جميع الـ DataTables أعمدة من اليمين**
- [ ] **أيقونات الأسهم معكوسة في RTL**
- [ ] **Pagination من اليمين لليسار**
- [ ] **Validation errors تظهر يمين الحقل**

---

## 📊 التقارير

### تقارير الحضور
| التقرير | الوصف | التنسيقات |
|---------|-------|-----------|
| يومي | ملخص حضور لكل موظف في يوم | HTML, PDF, Excel |
| شهري | إحصائيات شهرية كاملة | HTML, PDF, Excel |
| سنوي | تحليل سنوي للحضور | HTML, PDF, Excel |
| أداء الموظف | تقرير أداء فردي | PDF |
| مقارنة أقسام | مقارنة أداء الأقسام | HTML |
| تحليل Overtime | ساعات العمل الإضافية | HTML |

### تقارير الإجازات
- رصيد الإجازات لكل موظف
- سجل طلبات الإجازات
- إحصائيات الإجازات

---

## 🔌 التكاملات الخارجية

### ZKTeco (أجهزة البصمة) — اتصال IP/Ethernet

**جميع أجهزة ZKTeco تتصل عبر IP/Ethernet (TCP/IP). لا يوجد USB أو Serial.**

#### بنية التكامل (IP Ethernet)
```
┌─ Ethernet Network ──────────────────────────────────────┐
│                                                          │
│  ┌──────────┐    TCP/IP (port 4370)    ┌──────────────┐ │
│  │  Device   │ ←─────────────────────→ │  Python/Flask │ │
│  │  ZKTeco   │    pyzk library         │  (port 5000)  │ │
│  │  (IP:xxx) │                         │               │ │
│  └──────────┘                          └──────┬────────┘ │
│       │                                       │          │
│       │ ADMS Push (TCP/IP)                    │ HTTP     │
│       │ port 8081                             │ REST     │
│       ↓                                       ↓          │
│  ┌──────────────────┐              ┌───────────────┐     │
│  │ Python TCP Server│              │   Laravel 13  │     │
│  │ (port 8081)      │              │               │     │
│  └──────────────────┘              └───────┬───────┘     │
│                                            │             │
│                                            ↓             │
│                                      ┌──────────┐       │
│                                      │ Database  │       │
│                                      └──────────┘       │
│                                                          │
└──────────────────────────────────────────────────────────┘
  الشبكة: Ethernet (كابل شبكة) — جميع الأجهزة لها IP ثابت
```

#### إعدادات الجهاز على الشبكة
كل جهاز ZKTeco يحتاج:
- **IP ثابت (Static IP)** — في نفس نطاق الشبكة (مثال: 192.168.1.100)
- **Port:** 4370 (TCP — اتصال pyzk)
- **Port:** 80 (HTTP — واجهة الويب للجهاز، اختياري)
- **Subnet Mask:** 255.255.255.0
- **Gateway:** IP الراوتر
- **DNS:** 8.8.8.8 (اختياري)
- **كلمة مرور الجهاز:** (رقم 0-999999، الافتراضي 0)
- **الجهاز يجب أن يكون على نفس الشبكة** (أو شبكة متصلة) مع سيرفر Laravel/Python

#### اختبار الاتصال بالجهاز
```bash
# 1. اختبار Ping
ping 192.168.1.100

# 2. فحص المنفذ مفتوح
tnc 192.168.1.100 -Port 4370

# 3. عبر Laravel
php artisan zkteco:check-connection {device_id}

# 4. عبر Python مباشرة
curl -X POST http://localhost:5000/device/test-connection \
  -H "Content-Type: application/json" \
  -d '{"ip":"192.168.1.100","port":4370,"password":0}'
```

#### خدمات Python

| الخدمة | الملف | التقنية | البروتوكول | المنفذ | الوظيفة |
|--------|-------|---------|------------|--------|---------|
| Flask API | `app.py` | Flask + pyzk | HTTP (REST) | 5000 | REST API لسحب الحضور، إدارة المستخدمين، البصمات ← يتصل بالجهاز عبر TCP/IP 4370 |
| ADMS TCP | `adms_server.py` | Socket TCP | TCP/IP | 8081 | استقبال push مباشر من الأجهزة عبر IP

#### Endpoints Flask Microservice (`POST /device/*`)
| الـ Endpoint | الوظيفة | البيانات المرسلة (IP Ethernet) |
|---|---|---|
| `GET /health` | فحص الخدمة | — |
| `test-connection` | اختبار اتصال الجهاز عبر IP | ip, port, password |
| `get-attendance` | سحب سجلات الحضور عبر IP | ip, port, password, timeout, force_udp, ommit_ping |
| `get-users` | سحب المستخدمين من الجهاز عبر IP | ip, port, password |
| `get-templates` | سحب قوالب البصمات عبر IP | ip, port, password, uid (اختياري) |
| `add-user` | إضافة مستخدم للجهاز عبر IP | ip, port, password, uid, user_id, name, ... |
| `add-users-batch` | إضافة مستخدمين مجمعة عبر IP | ip, port, password, users[ ] |
| `delete-user` | حذف مستخدم من الجهاز عبر IP | ip, port, password, uid |
| `export-template` | رفع قالب بصمة للجهاز عبر IP | ip, port, password, uid, finger_id, template_data |
| `export-templates-batch` | رفع بصمات متعددة عبر IP | ip, port, password, templates[ ] |
| `clear-attendance` | مسح سجلات الحضور من الجهاز عبر IP | ip, port, password |
| `info` | معلومات الجهاز عبر IP | ip, port, password |
| `adms-config` | إعدادات ADMS (Device → Server IP) | ip, port, password, server_url |

#### Laravel Bridge (`app/Services/ZKTecoPythonBridgeService.php`)
```php
// كل methode تتوافق مع endpoint في Flask
$bridge->getAttendance($ip, $port, $password);
$bridge->getUsers($ip, $port, $password);
$bridge->getTemplates($ip, $port, $password, $uid);
$bridge->exportTemplate($ip, $port, $password, $uid, $fingerId, $templateData);
$bridge->addUser($ip, $port, $password, $uid, $userId, $name, ...);
$bridge->addUsersBatch($ip, $port, $password, $usersData);
$bridge->deleteUser($ip, $port, $password, $uid);
$bridge->clearAttendance($ip, $port, $password);
$bridge->testConnection($ip, $port, $password);
$bridge->getDeviceInfo($ip, $port, $password);
$bridge->isAvailable();
```

#### أوامر Artisan
```bash
php artisan zkteco:service start   # تشغيل Python service
php artisan zkteco:service stop    # إيقاف
php artisan zkteco:service status  # الحالة
```

#### المكتبات المطلوبة
```
# Python (requirements.txt) - اتصال IP Ethernet
flask==3.0.0                   # REST API
flask-cors==4.0.0              # CORS للـ API
pyzk==0.9                      # ZKTeco SDK - اتصال TCP/IP عبر Ethernet (port 4370)
python-dotenv==1.0.0           # إعدادات البيئة
requests==2.31.0               # HTTP requests (اختياري)

# PHP (Laravel)
# illuminate/http (Http facade)
# لا يحتاج مكتبات إضافية - يستخدم Laravel Http Client

# ملاحظة: pyzk يتصل بجهاز ZKTeco عبر TCP/IP على port 4370
# الجهاز يجب أن يكون له IP ثابت ومنفذ 4370 مفتوح
```

#### البروتوكول (IP Ethernet فقط)
- **البروتوكول:** TCP/IP فقط — لا USB، لا Serial، لا RS232، لا RS485
- **منفذ الجهاز:** 4370 (TCP) — pyzk يتصل عبر IP
- **الخدمة المصغرة:** Python Flask على port 5000 (HTTP REST)
- **ADMS Push:** TCP/IP مباشر من الجهاز → Python TCP Server على port 8081
- **تنسيق البيانات:** JSON (REST API) بين Laravel و Python
- **تشفير الاتصال:** لا يوجد تشفير مدمج — الاتصال داخل الشبكة المحلية (LAN)
- **عدد الأجهزة:** غير محدود — كل جهاز له IP مستقل

### 📥📤 استيراد وتصدير بيانات أجهزة البصمة (Data Sync)

#### 🎯 نظرة عامة على تدفق البيانات

```
┌─────────────────────────────────────────────────────────────────┐
│                      ZKTeco Data Sync Flow                       │
└─────────────────────────────────────────────────────────────────┘

1. سحب الحضور (Pull Attendance) — عبر IP Ethernet:
   ┌──────────┐    TCP/IP 4370    ┌──────────────┐    HTTP POST    ┌──────────┐    Eloquent    ┌────────┐
   │  Device   │ ──────────────→  │  Python/Flask │ ─────────────→ │  Laravel  │ ────────────→  │   DB   │
   │ (IP:xxx) │   (pyzk)         │  (port 5000)  │                │  Service  │               │ (SQL)  │
   └──────────┘                  └──────────────┘                └──────────┘               └────────┘

2. دفع المستخدمين للجهاز (Push Users) — عبر IP Ethernet:
   ┌────────┐    Eloquent    ┌──────────┐    HTTP POST    ┌──────────────┐    TCP/IP 4370    ┌──────────┐
   │   DB   │ ────────────→  │  Laravel  │ ─────────────→ │  Python/Flask │ ──────────────→  │  Device   │
   │ (SQL)  │              │  Service  │                │  (port 5000)  │   (pyzk)        │ (IP:xxx) │
   └────────┘              └──────────┘                └──────────────┘                  └──────────┘

3. ADMS Push (استقبال آني من الجهاز) — عبر IP Ethernet:
   ┌──────────┐    TCP/IP Push  ┌──────────────────┐    إشعار    ┌──────────┐    Eloquent    ┌────────┐
   │  Device   │ ─────────────→ │  Python TCP      │ ─────────→ │  Laravel  │ ────────────→  │   DB   │
   │ (IP:xxx) │   (port 8081)  │  Server (8081)    │            │  Service  │               │ (SQL)  │
   └──────────┘                └──────────────────┘            └──────────┘               └────────┘
```

#### 📋 عمليات استيراد البيانات (من الجهاز إلى Laravel)

| العملية | الوصف | الآلية | التوقيت |
|---------|-------|--------|---------|
| **سحب الحضور** | استيراد سجلات `iclock_transaction` من الجهاز | أمر Artisan + Queue | كل N دقيقة (Cron) أو يدوي |
| **سحب البصمات** | استيراد قوالب `iclock_biodata` من الجهاز | أمر Artisan | عند تسجيل موظف جديد أو يدوي |
| **سحب المستخدمين** | استيراد قائمة المستخدمين من الجهاز للتحقق | أمر Artisan | عند الفحص الأولي |
| **ADMS Push** | استقبال实时 سجلات الحضور مباشرة | TCP Socket + Queue | فوري (real-time) |

#### 📤 عمليات تصدير البيانات (من Laravel إلى الجهاز)

| العملية | الوصف | الآلية | التوقيت |
|---------|-------|--------|---------|
| **دفع الموظفين** | إرسال الموظفين الجدد إلى الجهاز | Queue Job | بعد إنشاء موظف جديد |
| **رفع البصمات** | رفع قوالب البصمات المسجلة في Laravel إلى الجهاز | Queue Job | بعد تسجيل بصمة موظف |
| **حذف موظف** | حذف موظف من الجهاز عند إنهاء خدمته | Queue Job | عند تغيير حالة الموظف |
| **مسح الحضور** | تنظيف سجلات الحضور من الجهاز | أمر Artisan | حسب الحاجة |

#### 🔄 سير العمل التفصيلي: سحب الحضور (Pull Attendance)

```
1. Cron Job (كل 5-15 دقيقة) أو أمر يدوي
   ↓
2. php artisan zkteco:pull-attendance {device_id}
   ↓
3. AttendanceService → ZKTecoPythonBridgeService::getAttendance()
   ↓
4. Python Flask → pyzk.get_attendance() → Device TCP (4370)
   ↓
5. Device يرد بسجلات الحضور (emp_code, punch_time, punch_state, verify_type)
   ↓
6. Flask يعيد JSON → Laravel يستقبل
   ↓
7. AttendanceImportService::processRawLogs():
   ├── 7a. تجاهل السجلات المكررة (باستخدام CRC أو punch_time + emp_code)
   ├── 7b. ربط emp_code بــ employee_id (من جدول personnel_employee)
   ├── 7c. إدراج في iclock_transaction
   └── 7d. تشغيل CalculateDailySummary (Queue) للموظف المتأثر
   ↓
8. Log: سجل عدد السجلات المستوردة في base_adminlog
```

#### 🔄 سير العمل التفصيلي: دفع الموظفين للجهاز (Push Users)

```
1. حدث: إنشاء/تعديل موظف في Laravel
   ↓
2. User Model Observer أو Event (UserCreated/UserUpdated)
   ↓
3. Dispatch Queue Job: PushUserToDeviceJob
   ↓
4. Queue Worker يتولى المهمة
   ↓
5. ZKTecoPythonBridgeService::addUser() أو addUsersBatch()
   ↓
6. Python Flask → pyzk.add_user() → Device
   ↓
7. النتيجة:
   ├── نجاح → تحديث sync_status في iclock_terminalemployee
   └── فشل → تسجيل الخطأ + إعادة المحاولة (3 مرات)
```

#### 🔄 سير العمل التفصيلي: ADMS Push (استقبال آني)

```
1. جهاز البصمة يرسل بصمة (HTTP GET Request إلى Laravel)
   ↓
2. Route: GET|POST /iclock/pushattlog.aspx?SN=XXX&table=ATTLOG&...
   ↓
3. ZKTeco ADMS Controller:
   ├── 3a. التحقق من الجهاز (SN موجود في iclock_terminal؟)
   ├── 3b. فك تشفير البيانات (base64 أو plain text)
   └── 3c. إدراج في iclock_transaction
   ↓
4. تشغيل ProcessAttendanceLogs queue job
   ↓
5. Log + Response to device (OK)
```

#### ⚙️ أوامر Artisan لاستيراد/تصدير البيانات

| الأمر | الوظيفة | المعاملات |
|-------|---------|-----------|
| `zkteco:pull-attendance {device_id?}` | سحب سجلات الحضور من جهاز/كل الأجهزة | `--from=Y-m-d`, `--to=Y-m-d`, `--force` |
| `zkteco:push-users {device_id?}` | دفع جميع الموظفين النشطين لجهاز | `--dry-run` (اختبار بدون تنفيذ) |
| `zkteco:push-user {employee_id}` | دفع موظف واحد لجهازه المحدد | `--device-id=` |
| `zkteco:pull-templates {device_id?}` | سحب قوالب البصمات من الجهاز | `--employee-id=` |
| `zkteco:push-templates {employee_id}` | رفع بصمات موظف للجهاز | `--device-id=` |
| `zkteco:sync-all` | مزامنة كاملة (push users + pull attendance) | `--device-id=` |
| `zkteco:delete-user {employee_id}` | حذف موظف من الجهاز | `--device-id=` |
| `zkteco:clear-attendance {device_id}` | مسح سجلات الحضور من الجهاز | `--confirm` |
| `zkteco:service {start\|stop\|status}` | إدارة خدمة Python | — |
| `zkteco:check-connection {device_id}` | اختبار اتصال جهاز | — |
| `attendance:process-logs` | معالجة سجلات iclock_transaction غير المعالجة | `--date=Y-m-d` |
| `attendance:recalculate {employee_id?}` | إعادة حساب ملخصات الحضور | `--from=`, `--to=` |

#### 📦 Queue Jobs للاستيراد/التصدير

| الـ Job | الوصف | الأولوية | إعادة المحاولة |
|---------|-------|----------|----------------|
| `ProcessAttendanceLogsJob` | معالجة سجلات بصمة خام → حساب الحضور | عالية | 3 مرات |
| `PushUserToDeviceJob` | دفع موظف جديد للجهاز | متوسطة | 3 مرات |
| `PushUserBatchToDeviceJob` | دفع مجموعة موظفين للجهاز | منخفضة | 2 مرات |
| `PushFingerprintToDeviceJob` | رفع بصمة موظف للجهاز | متوسطة | 3 مرات |
| `DeleteUserFromDeviceJob` | حذف موظف من الجهاز | عالية | 3 مرات |
| `PullAttendanceFromDeviceJob` | سحب الحضور من جهاز (جدول زمني) | منخفضة | مرة واحدة |
| `SyncEmployeeToDeviceJob` | مزامنة موظف مع الجهاز | متوسطة | 3 مرات |

#### ⏰ المهام المجدولة (Cron / Laravel Scheduler)

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // سحب الحضور من كل الأجهزة كل 10 دقائق (ساعات الدوام)
    $schedule->command('zkteco:pull-attendance')
        ->everyTenMinutes()
        ->between('6:00', '23:00')
        ->withoutOverlapping(5)
        ->runInBackground();

    // دفع الموظفين الجدد كل ساعة
    $schedule->command('zkteco:push-users')
        ->hourly()
        ->withoutOverlapping(10);

    // معالجة سجلات الحضور غير المعالجة كل 5 دقائق
    $schedule->command('attendance:process-logs')
        ->everyFiveMinutes()
        ->withoutOverlapping(3);

    // تنظيف البيانات المكررة كل يوم في منتصف الليل
    $schedule->command('attendance:deduplicate')
        ->dailyAt('00:30')
        ->withoutOverlapping(30);
}
```

#### 🧹 معالجة البيانات المكررة (Deduplication)

```
مشكلة: أجهزة ZKTeco قد ترسل نفس سجل البصمة أكثر من مرة (خاصة مع ADMS Push)
الحل: استخدام CRC hash فريد لكل سجل

معيار التكرار:
  - iclock_transaction: (emp_code + punch_time + punch_state + terminal_sn) → UNIQUE
  - عند الإدراج: INSERT IGNORE أو upsert

أمر التنظيف:
  php artisan attendance:deduplicate
  → يمسح سجلات iclock_transaction المكررة (يحتفظ بالأقدم)
```

#### 🔐 الأمان في استيراد/تصدير البيانات

- **مصادقة الجهاز:** التحقق من SN في `iclock_terminal` قبل قبول أي بيانات
- **ADMS Push:** التحقق من IP المصدر (اختياري)
- **Rate Limiting:** حد أقصى للطلبات من جهاز واحد (10 req/min)
- **Validation:** التحقق من صحة `emp_code` قبل الإدراج في `iclock_transaction`
- **Logging:** تسجيل كل عملية استيراد/تصدير في `base_adminlog`
- **Queue:** جميع العمليات الثقيلة عبر Queue (لا تعطّل واجهة المستخدم)

#### 📊 Dashboard عرض حالة المزامنة

```
بطاقة حالة الجهاز:
├── 🟢 متصل (last_activity < 5 دقائق)
├── 🟡 غير نشط (last_activity > 30 دقيقة)
└── 🔴 غير متصل (last_activity > ساعتين)

آخر سحب:
├── آخر مرة: 2026-07-13 14:30:00
├── عدد السجلات: 1,234
└── الحالة: نجاح

الموظفين:
├── في الجهاز: 150
├── في النظام: 148
└── الفرق: 2 (يحتاج مزامنة)
```

### التقارير
- **PDF:** DomPDF + mPDF
- **Excel:** PhpSpreadsheet

### التخزين المؤقت
- **Redis/Predis** للتخزين المؤقت
- **ملف (File)** كخيار احتياطي

### قائمة الانتظار
- **Database driver** للمهام الثقيلة
- معالجة الفواتير، حسابات الحضور، الإشعارات

---

## ⚙️ الإعدادات (Environment)

```
APP_NAME=HRM
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mysql  # pgsql للإنتاج الفعلي (حسب الـ SQL dump), sqlite للتطوير
DB_HOST=127.0.0.1
DB_PORT=5432         # 3306 لـ MySQL, 5432 لـ PostgreSQL
DB_DATABASE=hrm
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=file
QUEUE_CONNECTION=sync
CACHE_STORE=file

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=63791

ZKTECO_PYTHON_SERVICE_ENABLED=true
ZKTECO_PYTHON_SERVICE_URL=http://localhost:5000
ZKTECO_PYTHON_SERVICE_TIMEOUT=60
ZKTECO_PYTHON_SERVICE_OS=windows
ZKTECO_DEVICE_PORT=4370
ZKTECO_DEVICE_TIMEOUT=30
ZKTECO_NETWORK_INTERFACE=0.0.0.0
```

---

## 📁 هيكل المشروع الكامل

```
hrm/
├── app/                          ← التطبيق الأساسي
│   ├── Console/Commands/         ← أوامر Artisan
│   ├── Http/
│   │   ├── Controllers/          ← تحكمات Auth, Dashboard
│   │   └── Middleware/            ← Middleware مخصصة
│   ├── Models/                   ← نماذج مشتركة
│   ├── Providers/                ← مزودي الخدمة
│   └── Services/                 ← خدمات مشتركة
├── Modules/                      ← 22 وحدة
│   ├── Companies/
│   ├── Branches/
│   ├── Departments/
│   ├── Positions/
│   ├── Grades/
│   ├── Shifts/
│   ├── ShiftRotation/
│   ├── Users/
│   ├── Attendance/
│   ├── FingerprintDevices/
│   ├── Holidays/
│   ├── Vacations/
│   ├── Settings/
│   ├── Zones/
│   ├── Payroll/
│   ├── Visitor/
│   ├── Meeting/
│   ├── AccessControl/
│   ├── Workflow/
│   ├── Mobile/
│   ├── Sync/
│   └── Fingerprints/
├── config/                      ← الإعدادات
├── database/                    ← الترحيلات والبذور
│   ├── migrations/              ← 34 ترحيل
│   └── seeders/                 ← 10 بذور
├── resources/                   ← الموارد
│   ├── js/                      ← Vue 3 SPA (app.js, Pages, Layouts, Components)
│   ├── lang/                    ← ترجمة (ar, en)
│   └── views/                   ← قالب Blade وحيد (app.blade.php) لـ Inertia
├── routes/                      ← المسارات
├── specs/                       ← التوثيق
├── .specify/                    ← Spec Kit
├── zkteco-service/              ← خدمة Python
├── tests/                       ← الاختبارات
└── public/                      ← الملفات العامة
```

---

## ✅ معايير القبول (Acceptance Criteria)

### البنية والهندسة
- [ ] جميع الوحدات تتبع نمط Controller → Service → Repository → Model
- [ ] جميع الصلاحيات محمية بـ Spatie Permission middleware
- [ ] Routes naming موحد لجميع الوحدات
- [ ] Auth middleware على جميع المسارات المحمية
- [ ] CSRF protection على جميع النماذج
- [ ] **جميع الـ Services تستخدم Dependency Injection (لا `app()` أو `resolve()`)**
- [ ] **جميع الـ Controllers نحيفة (Thin) - لا منطق أعمال**
- [ ] **جميع الـ Validations في FormRequests (لا في Controllers أو Services)**

### المكونات المشتركة (UI)
- [ ] **لا يوجد `<table>` مكتوب يدوي - كل الجداول عبر `<DataTable />`**
- [ ] **لا يوجد `<input>` مباشر - كل الحقول عبر `<FormInput />`**
- [ ] **لا يوجد modal مخصص - كل النوافذ عبر `<FormModal />`**
- [ ] **لا يوجد `<select>` مباشر - كل القوائم عبر `<FormSelect />`**
- [ ] **لا يوجد `confirm()` مباشر - كل التأكيدات عبر `<ConfirmDialog />`**
- [ ] **لا يوجد pagination مكتوب يدوي - كل الترقيم عبر `<Pagination />`**

### الأداء والسرعة
- [ ] **N+1 ممنوع تماماً** - جميع الاستعلامات تستخدم `with()` أو `load()`
- [ ] Pagination لجميع القوائم
- [ ] Eager loading للعلاقات
- [ ] Cache للإعدادات والتقارير
- [ ] Queue للمهام الثقيلة
- [ ] **جميع المكونات الثقيلة (charts, maps) تُحمّل lazily**
- [ ] **Debounce 300ms للبحث**
- [ ] **Time to First Paint < 1.5 ثانية**

---

## 📌 الاعتماديات (Dependencies) بين الوحدات

```
Companies
└── Branches
    ├── Departments
    │   └── Users ←──────────────┐
    │       ├── Attendance       │
    │       ├── Vacations        │
    │       └── FingerprintDevices│
    │                            │
    └── Zones                    │
                                  │
Positions (standalone) ──────────┘
Grades (standalone) ────────────┘
Shifts (standalone) ────────────┘
Holidays (standalone)
Settings (standalone)

ShiftRotation ← Shifts + Users
```

### ترتيب البناء
```
Phase 1: Companies → Departments → Positions → Areas (Zones)
Phase 2: Shifts (att_attshift + att_shiftdetail + att_breaktime)
Phase 3: Users (personnel_employee - يعتمد على كل ما سبق)
Phase 4: Attendance (att_attemployee, att_attgroup, att_attschedule)
Phase 5: FingerprintDevices (iclock_terminal, iclock_biodata)
Phase 6: AttendanceProcessing (iclock_transaction + policies)
Phase 7: Holidays (att_holiday) + Leave Types (att_paycode)
Phase 8: Workflow Engine (workflow_*) ← أساسي للإجازات
Phase 9: Vacations (att_leave + att_leaveyearbalance) ← يعتمد على Workflow
Phase 10: Payroll (payroll_*) ← يعتمد على كل شيء
Phase 11: AccessControl (acc_*) ← يعتمد على Areas + Devices
Phase 12: Visitor (visitor_*) ← يعتمد على Workflow
Phase 13: Meeting (meeting_*) ← يعتمد على Workflow
Phase 14: Mobile (mobile_*) + Sync (sync_*)
Phase 15: Reports + Dashboard
```

### الإصدارات (Versioning)
| الإصدار | الجداول | الوصف |
|---------|---------|-------|
| v1.0 | 15-20 | الهيكل التنظيمي + المستخدمين + المناوبات الأساسية |
| v2.0 | 30-40 | الحضور + الأجهزة + الإجازات + سير العمل |
| v3.0 | 60-70 | الرواتب + التحكم بالوصول + الزوار + الاجتماعات |
| v4.0 | 80-100 | الجوال + المزامنة + التقارير المتقدمة |

---

## 🛠️ أوامر Artisan

### أوامر ZKTeco (استيراد/تصدير البيانات عبر IP Ethernet)

| الأمر | الوظيفة | المعاملات |
|-------|---------|-----------|
| `zkteco:pull-attendance {device_id?}` | سحب سجلات الحضور من جهاز/كل الأجهزة عبر TCP/IP 4370 | `--from=Y-m-d`, `--to=Y-m-d`, `--force` |
| `zkteco:push-users {device_id?}` | دفع جميع الموظفين النشطين لجهاز عبر IP | `--dry-run` |
| `zkteco:push-user {employee_id}` | دفع موظف واحد لجهازه (IP محدد في الجهاز) | `--device-id=` |
| `zkteco:pull-templates {device_id?}` | سحب قوالب البصمات من الجهاز عبر IP | `--employee-id=` |
| `zkteco:push-templates {employee_id}` | رفع بصمات موظف للجهاز عبر IP | `--device-id=` |
| `zkteco:sync-all` | مزامنة كاملة (push+ pull) عبر IP | `--device-id=` |
| `zkteco:delete-user {employee_id}` | حذف موظف من الجهاز عبر IP | `--device-id=` |
| `zkteco:clear-attendance {device_id}` | مسح حضور الجهاز عبر IP | `--confirm` |
| `zkteco:service {start\|stop\|status}` | إدارة خدمة Python | — |
| `zkteco:check-connection {device_id}` | اختبار اتصال IP + Port 4370 | — |
| `attendance:process-logs` | معالجة سجلات خام | `--date=Y-m-d` |
| `attendance:recalculate {employee_id?}` | إعادة حساب ملخصات | `--from=`, `--to=` |
| `attendance:deduplicate` | تنظيف السجلات المكررة | `--date=Y-m-d` |

### أوامر عامة (من النظام القديم)
| الأمر | الوصف |
|-------|-------|
| `Add48HourAttendance` | إضافة حضور 48 ساعة |
| `AddAttendanceToEmployee` | إضافة حضور لموظف |
| `AssignFirstShiftToUsers` | تخصيص أول مناوبة |
| `CreateShiftAndEmployee` | إنشاء مناوبة وموظف |
| `DeduplicateFingerprintTemplates` | إزالة تكرار البصمات |
| `GenerateThemeCSS` | توليد CSS الثيم |
| `InitializeVacationBalancesForNewYear` | تهيئة رصيد الإجازات |
| `KillAllQueueWorkers` | إيقاف عمال queue |
| `ManageVacationBalance` | إدارة رصيد الإجازات |
| `ProcessMasterFingerprints` | معالجة البصمات الرئيسية |
| `PullFingerprintsFromDevices` | سحب البصمات من الأجهزة |
| `QueueWorkersStatus` | حالة عمال queue |
| `RealtimeFingerprintPull` | سحب بصمات آني |
| `ReassignUserIds` | إعادة تخصيص IDs |
| `VacationRequestCommand` | إدارة طلبات الإجازات |
| `ZKTecoServiceCommand` | تشغيل/إيقاف خدمة ZKTeco |

### أوامر الوحدة (Attendance)
| الأمر | الوصف |
|-------|-------|
| `AttendanceStatistics` | إحصائيات الحضور |
| `CalculateSessionDurations` | حساب مدة الجلسات |
| `CleanupAttendanceData` | تنظيف بيانات الحضور |
| `ClearAttendanceCache` | مسح cache الحضور |
| `DeduplicateAttendanceSessions` | إزالة جلسات مكررة |
| `DeduplicateRawAttendanceLogs` | إزالة سجلات خام مكررة |
| `FixDailyAttendanceCalculations` | إصلاح حسابات يومية |
| `MonitorAttendance` | مراقبة الحضور |
| `ProcessAttendanceLogs` | معالجة سجلات الحضور |
| `RecalculateDailySummaries` | إعادة حساب الملخصات |
| `RecalculateSessionTypes` | إعادة حساب أنواع الجلسات |
| `ScheduleAttendanceTasks` | جدولة مهام الحضور |
| `SendAttendanceNotifications` | إرسال إشعارات الحضور |
| `WarmAttendanceCache` | تدفئة cache الحضور |

---

## 🔧 الإصلاحات المطلوبة (Known Issues)

### ذات أولوية عالية
- [ ] **Zones**: نقل Model من `Entities/` إلى `Models/` (يجب أن يكون `personnel_area`)
- [ ] **Companies**: نقل validation من Controller إلى Service
- [ ] **Branches/Departments**: نقل validation من Controller إلى Service
- [ ] **Positions**: إضافة Service و Repository (موجود Controller فقط)
- [ ] **Zones**: إضافة Service و Repository (غير موجودة)
- [ ] **Modules**: توحيد Route naming لجميع الـ 22 وحدة
- [ ] **Grades**: هذا الجدول غير موجود في الـ SQL dump - يحتاج إنشاء من الصفر
- [ ] **Branches vs Departments**: النظام القديم يستخدم `personnel_department` للاثنين بشكل هرمي - يحتاج قرار تصميم

### ذات أولوية متوسطة
- [ ] إضافة auth middleware لجميع المسارات العامة
- [ ] توثيق PHPDoc للخدمات العامة
- [ ] اختبارات للوحدات التي تفتقر إليها
- [ ] تحسين الفهارس للاستعلامات البطيئة
- [ ] إعادة تصميم Models لتطابق الجداول الفعلية (100+ جدول)
- [ ] إضافة Models للوحدات: Payroll, Visitor, Meeting, AccessControl, Workflow, Mobile, Sync

### ذات أولوية منخفضة
- [ ] توثيق جميع الوحدات الـ 22 في AGENTS.md
- [ ] إزالة الحزم غير المستخدمة
- [ ] تحسين أداء التقارير الكبيرة
- [ ] إنشاء seeders للبيانات الأساسية (من SQL dump)
- [ ] تحويل الـ SQL dump من PostgreSQL إلى MySQL/SQLite migrations

---

*نهاية المواصفات الكاملة للنظام*
