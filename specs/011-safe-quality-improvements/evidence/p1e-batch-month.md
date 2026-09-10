# دليل P1-E — تجميع حلقة `getMonthlyAbsence` الشهرية

**التاريخ:** 2026-09-09
**الملف:** `Modules/Shifts/app/Services/AbsenceCalculationService.php` (diff محصور بمنطقة الحلقة + helper واحد)

## قبل: ~4 exists() لكل يوم (~120/شهر لكل موظف)
`AttendanceSession::onDate+exists` + `RawAttendanceLog+whereBetween+exists`
+ `UserVacationRequest+exists` + `ShiftException+exists` داخل حلقة الأيام.

## بعد: 4 استعلامات للشهر كله (نفس أنماط `getMonthlyAbsenceReport` المُثبتة)
1. `betweenDates+where user+distinct+pluck` → مجموعة أيام الجلسات.
2. `raw_attendance_logs+whereBetween(month UTC bounds)+pluck` → توزيع محلي
   عبر `localDateFromUtc` (يكافئ `localDayUtcBounds` لكل يوم — نفس التعبير).
3. `UserVacationRequest approved+overlapping(month)` → `indexCoverage`.
4. `ShiftException active+types+month overlap` → `indexCoverage`.
5. الحلقة تستخدم `isset()` على المجموعات + `isCoveredBy()` (توأم منطقي
   لـ `getCoverage` للمواضع التي تحتاج boolean فقط).

## تكافؤ الدلالات (مثبت سطر سطر)
- `onDate(D)` = `attendance_date BETWEEN 'D 00:00:00' AND 'D 23:59:59'` على
  عمود DATE ≡ تجميع `dateKey()` لنفس النطاق الشهري.
- `whereDate(start)<=D && whereDate(end)>=D` ≡ `overlapping(month)` +
  فحص `from<=D<=to` بلغة PHP (نفس `dateKey`) — الصفوف خارج الشهر لا تطابق
  أي يوم داخله أصلاً.
- لا كود ميت: `localDayUtcBounds` و`RawAttendanceLog` ما زالا مستخدمين
  بطرق أخرى (393/540).

## الإثبات الحي (بيانات الإنتاج، نفس الموظف/الشهر)
| | قبل | بعد |
|---|---|---|
| استعلامات الجداول الأربعة | 16 | **3** |
| المخرج (md5 للنتيجة الكاملة) | 786f16… | **786f16… مطابق** |

## الاختبارات
- `AbsenceCalculationServiceTest`: 19/20 — الوحيد (`6v7`) مسبق ومثبت
  بالـ stash بتاريخ P1-B (يفشل identically بدون أي تعديل).
