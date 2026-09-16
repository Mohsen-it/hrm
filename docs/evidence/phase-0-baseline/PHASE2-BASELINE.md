# Phase 2 — Performance Baseline (measurement only, 2026-09-14)

> لم يتم تغيير أي سطر إنتاج في هذه المرحلة. قياس فقط + تحليل قراءة.

## 1. Query counts (SQLite معزولة، 25 موظف + super-admin، cold cache لكل endpoint)

| الصفحة | الاستعلامات | الملاحظة |
|---|---|---|
| `/dashboard` | 39 | 6 صلاحيات (test-only، مخزنة 24h في الإنتاج) + مجاميع مميزة لكل widget |
| `/users` | 18 | شكل سليم، لا تكرار لكل صف |
| `/attendance/reports` | 10 | سليم |
| `/attendance/live` | 11 | سليم |
| `/fingerprint-devices/devices` | 9 | سليم |

المنهجية: اختبار مؤقت (حُذف بعد الالتقاط) — endpoint واحد لكل test = تطبيق وقاعدة جديدة وcold cache. التكرار الأول الملوث (listeners متراكمة أعطت 26/15/4) رُفض وأُعيد بمنهجية نظيفة — الأرقام أعلاه هي المعتمدة.

## 2. أهم استنتاج: لا N+1 ولا تكرار مثبت

- كل الاستعلامات `x1` عدا Dashboard (x3/x2 على نفس النص).
- فحص `DashboardController.php:222`: يستدعي `getDailyTrend` مرتين بنطاقين مختلفين (7 أيام + 30 يوماً) — نفس نص SQL لكن bindings مختلفة → **استعلامات شرعية مميزة، ليست تكراراً**.
- `AttendanceReportService` يستخدم `cache->remember` لكل دالة — البنية صحيحة.
- **قرار: لا تحسين backend قبل قياس staging.** أي "إصلاح" الآن كان سيغير سلوكاً سليماً.

## 3. Bundle (public/build — قراءة فقط)

- الإجمالي ~2.75MB. الأكبر: `inertia-vendor 201KB`، `charts 186KB`، `app.css 136.5KB`، خطوط FA (solid 116.7 + brands 112.7 + regular 19.1 + cairo ~110KB).
- **تصحيح لفرضية الخطة:** `resources/js/app.js:2` يستورد FA كـ CSS webfonts (`all.min.css`) وليس JS — "الاستيراد الانتقائي" لا ينطبق حرفياً؛ البديل الحقيقي هو font-subsetting أو SVG sprites (مشروع مستقل بأدواته).
- `DesignSystemShowcase (16.5KB)` صفحة dev تُشحن في الـ build — مرشح استبعاد من إنتاج (build config فقط، صفر خطر وظيفي).
- `charts 186KB` + `realtime 70KB` مرشحان لـ async chunks (قياس أثر أولاً).

## 4. المطلوب من بيئة staging (لا يُنفذ من هنا)

1. `DB::listen` + sampling على MySQL ببيانات حقيقية: p50/p95/p99 للصفحات الخمس.
2. Slow-query log حد 100ms (بدون أسرار) + `EXPLAIN` لأثقل 5 استعلامات.
3. مراجعة `config:cache / route:cache` (حالياً NOT CACHED حسب `about.json`).
4. قياس زمن `migrate` للـ migration الجديدة (عمودان nullable — آمن نظرياً).

## 5. الخلاصة

البنية الخلفية سليمة الشكل على بيانات صغيرة؛ عنق الزجاجة الحقيقي (إن وجد) يظهر فقط ببيانات الإنتاج. المرحلة 2 استنفدت ما يمكن قياسه بأمان من هنا دون لمس التشغيل الحي.
