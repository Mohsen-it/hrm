# دليل P2-B — تلميع الواجهة (توحيد، صفر باكند)

**التاريخ:** 2026-09-09

## التغييرات (3، واجهة فقط)
1. `FaceSyncDashboard.vue`: المودال المخصص (Teleport+Transition يدوي)
   → `<FormModal v-model size="sm" :title>` — نفس المحتوى (قائمة الأجهزة)،
   نفس الإغلاق بالخلفية، + Esc وfocus-trap وإغلاق موحد (تحسين).
2. `EmployeeMonthlyAttendance.vue`: زر المسح الخام → `<IconButton
   ghost sm>` (نفس الأيقونة والسلوك).
3. `ui/image.png` (68KB، غير مشار إليه بأي ملف — مثبت بالبحث) → نُقل
   بـ `git mv` إلى `resources/js/assets/` (التاريخ محفوظ).

## التحقق
- `npm run build`: نجاح (2.97s) — القوالب تُترجم ✅.
- مخرجات البناء git-ignored (0 ملفات بناء بالـ status) ✅.
- لا تعديل PHP/باكند إطلاقاً بهذا البند ✅.
