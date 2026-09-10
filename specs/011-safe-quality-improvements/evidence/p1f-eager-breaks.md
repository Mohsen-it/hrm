# دليل P1-F — eager-load فترات الراحة بتقرير الغياب الذكي

**التاريخ:** 2026-09-09
**الملف:** `Modules/Shifts/app/Repositories/RotationAssignmentRepository.php` (سطر واحد)

## التشخيص
تقرير الغياب الذكي (يومي/شهري/تقويم) نفسه مُجمّع جيداً (استعلامات `whereIn`
+ تجميع PHP). الـ N+1 المتبقية: `RotationEngine::resolveTimes()` يقرأ
`timeSchedule->breaks` لكل assignment — و`$defaultWith` كان يحمّل
`rotation.timeSchedule.*` **بدون** `breaks`، فكل assignment تُطلق lazy query
(نسخ Eloquent لا تُشارك حتى لنفس الجدول).

## الإصلاح (سطر واحد، بيانات مطابقة)
إضافة `'rotation.timeSchedule.breaks'` إلى `$defaultWith` — نفس البيانات،
تحميل مُسبق بدل كسول. يخدم كل التقارير دفعة واحدة.

## الإثبات الحي (`getDailyStatusBreakdown` على الإنتاج)
| | قبل | بعد |
|---|---|---|
| استعلامات breaks | 4 | **2** (الباقي من مسارات أخرى خارج `defaultWith`) |
| المخرج (md5) | 71706b… | **71706b… مطابق** |

## التحقق
- pint ✅. الاختبارات: 27/28 (الوحيد هو `6v7` المسبق المُثبت).
