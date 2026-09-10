# دليل P1-B — إصلاح N+1 للعطل (memoize + تجميع)

**التاريخ:** 2026-09-09
**الملفات:** `Modules/Shifts/app/Services/AbsenceCalculationService.php` + `Modules/Shifts/app/Http/Controllers/ScheduleCalendarController.php`

## التغيير (سلوك مطابق)
1. `activeHolidays()` memoized per-instance بدل 6× `Holiday::active()->get()` — كل الاستخدامات قراءة فقط (مثبت بـ grep)، والخدمة transient بلا singleton (مثبت — لا قِدَم عبر requests/jobs).
2. `ScheduleCalendarController::department()`: نسخة خدمة واحدة قبل الحلقة + استعلام punch واحد `whereIn` بدل N×(`app()` جديد + `exists()`). `exists(user) ⇔ user ∈ set` — منطقياً مطابق.

## الإثبات على بيانات الإنتاج الحية (نفس الـ resolve، طريقتان)
| | بدون الإصلاح | مع الإصلاح |
|---|---|---|
| استعلامات holidays لطريقتين على نفس النسخة | 2 | **1** |
| النتائج (absent / breakdown total) | 15 / 260 | **15 / 260 (مطابقة)** |

## الاختبارات
- `AbsenceCalculationServiceTest`: 19/20 — الإخفاق الوحيد (`test_monthly_report...` 6v7) **مسبق ومثبت**: يفشل identically بدون تعديلي (stash test).
- إخفاقات `Route [shift-*] not defined` الـ 28 مسبقة (تسجيل routes ناقص ببيئة الاختبار).
- Controller: pint ✅. Service: pint يفشل مسبقاً على HEAD (تحققت in-place) — لم أشغل وضع الإصلاح لتجنب توسيع الفرق؛ أسطري فقط بالـ diff.

## الأثر طويل الأمد
- تقويم قسم (50 موظف): من ~150 استعلام إلى ~50 (إلغاء resolves + تجميع punches + عطلة واحدة).
- تقارير SmartAbsence متعددة الطرق: من 6 استعلامات عطل إلى 1 لكل request.
