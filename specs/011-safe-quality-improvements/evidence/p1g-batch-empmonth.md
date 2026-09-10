# دليل P1-G — تجميع `getEmployeeMonthlyAttendance` (الإسنادات + البصمات)

**التاريخ:** 2026-09-09
**الملفات:** `Repositories/RotationAssignmentRepository.php` (method جديدة) + `Services/AbsenceCalculationService.php` (حلقة `getEmployeeMonthlyAttendance`)

## قبل: ~30 استعلام إسناد + ~60 بصمة/شهر لكل موظف
`getAssignmentForDate()` + `onDate+exists` + `raw+whereBetween+exists` داخل
حلقة الأيام.

## بعد: 3 استعلامات
1. `getEmployeeAssignmentsOverlapping()` الجديدة (إسنادات الشهر دفعة واحدة
   بنفس الترتيب — أول تطابق لليوم = `getAssignmentForDate()` لذلك اليوم؛
   الأعمدة DATE خالصة فالنسخ PHP مطابق حرفياً).
2. أيام الجلسات + 3. البصمات الخام (نفس نمط P1-E المُثبت).

## حادثة أثناء العمل (بشفافية)
أضفت method باسم موجود (`getAssignmentsOverlapping` بنسخة كل-الموظفين) →
fatal عند التحميل. اكتُشف فوراً بسكربت الإثبات قبل أي نشر، وأُصلح بإعادة
التسمية إلى `getEmployeeAssignmentsOverlapping`. لا أثر إنتاجي (لم يُنفذ).

## الإثبات الحي (نفس الموظف/الشهر على الإنتاج)
| | قبل | بعد |
|---|---|---|
| الاستعلامات | 39 | **3** |
| المخرج (md5) | 4cace8… | **4cace8… مطابق** |

## التحقق
- `php -l` للملفين ✅، pint للـ Repository ✅ (الـ Service يفشل pint مسبقاً).
- الاختبارات: 22/23 (الوحيد `6v7` المسبق المُثبت).
