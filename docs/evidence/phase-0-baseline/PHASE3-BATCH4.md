# Phase 3 — Batch 4: RTL pass + brand band (2026-09-14)

## SunsetStripeBand

- التحقق: layout واحد فقط (`AppLayout.vue:170`)، الارتفاع مقفل (`app.css:1099` governance + `--sunset-stripe-height: 3px` + `position: fixed`) — **لم يُلمس**.
- الفجوة: صفحة الدخول standalone بلا layout → بلا شريط. الإصلاح: `<SunsetStripeBand :dir>` في `Login.vue` (fixed/aria-hidden/pointer-events-none = صفر أثر تخطيطي).

## Login.vue (إضافي آمن)

- `left-4/right-4` المشروط ← `end-4` المنطقي (نفس العرض في الحالتين).

## FaceSyncDashboard RTL (10 إصلاحات، صفر LTR)

- toast: `right-4` ← `end-4` (كان ملتصقاً باليمين في العربية).
- فجوات الأيقونات: `mr-*` ← `me-*` (×8: toast، overview ×5، retry overview، retry-all). في LTR متطابق حرفياً؛ في RTL الفجوة تنتقل للجهة الصحيحة.
- `fa-arrow-right` ← أضيف `rtl-flip` (الموجود في `app.css:161`) — السهم كان يشير يميناً في العربية.
- متروك عمداً: `ml-auto` (النية غير مؤكدة)، busy-spinners (نمط صحيح).

## البوابات

- `lint:tokens`: PASS. `build`: SUCCESS (FaceSync 29.34KB ثابت).
- اختبارات: 36/36 (الأجهزة 31 + Auth incl. login 5).
