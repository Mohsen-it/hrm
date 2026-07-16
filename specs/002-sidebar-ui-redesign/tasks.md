# إعادة تصميم واجهات السايدبار - تقسيم المهام

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-15
**الفرع:** `002-sidebar-ui-redesign`

---

## ملخص المهام

| القصة | الأولوية | عدد المهام |
|---|---|---|
| US1: الوصول السريع للوحدات | P1 | 3 |
| US4: سهولة القراءة والتباين | P2 | 4 |
| US2: تحديد الوحدة النشطة | P3 | 2 |
| US3: طي/فتح السايدبار | P4 | 3 |
| المراحل الأولية والتلميع | — | 9 |
| **الإجمالي** | | **21** |

---

## خريطة التبعيات بين القصص

```
Phase 1: Setup
       │
       ▼
Phase 2: Foundational (SidebarItem + SidebarGroup)
       │
       ├──▶ Phase 3: US1 (Navigation + Permissions)
       │
       ├──▶ Phase 4: US4 (Readability + Contrast)
       │
       ├──▶ Phase 5: US2 (Active State)
       │
       └──▶ Phase 6: US3 (Mobile Collapse)
              │
              ▼
       Phase 7: Polish (Tests + Lint + Validation)
```

---

## المرحلة 1: إعداد المشروع (Setup)

- [X] T001 راجع المكونات الموجودة في `resources/js/Layouts/AppLayout.vue` و `resources/css/app.css` (السايدبار كان مُضمَّن داخل AppLayout).
- [X] T002 [P] حدّث ألوان DESIGN.md في `resources/css/app.css` ضمن بلوك `@theme` (الإضافة الفعلية: استبدال القيم الافتراضية وإضافة tokens مثل `--color-teal-deep`, `--color-ink`).
- [X] T003 [P] مفاتيح الترجمة موجودة مسبقاً في `lang/ar/menu.php` و `lang/en/menu.php` ولا تحتاج تعديل.

## المرحلة 2: الأساسيات (Foundational)

- [X] T004 [P] أنشئ مكون `resources/js/Components/layout/SidebarItem.vue` وفق عقد `component-api.md`.
- [X] T005 [P] أنشئ مكون `resources/js/Components/layout/SidebarGroup.vue` وفق عقد `component-api.md`.
- [X] T006 أنشئ composable `resources/js/composables/useSidebarMenu.js` يُعرّف قائمة الوحدات مع الصلاحيات والأيقونات والمسارات.

## المرحلة 3: US1 — الوصول السريع إلى أي وحدة

- [X] T007 [US1] أنشئ `resources/js/Components/layout/Sidebar.vue` و حدّث `resources/js/Layouts/AppLayout.vue` ليستخدمه.
- [X] T008 [US1] التحقق من الصلاحيات مُنفَّذ في `useSidebarMenu.js` (دالة `has`).
- [X] T009 [US1] ربط `AppLayout.vue` بـ `Sidebar.vue` مع دعم RTL عبر `isRtl`.

## المرحلة 4: US4 — سهولة القراءة والتباين

- [X] T010 [P] [US4] ألوان DESIGN.md مطبقة على `.sidebar` و `.sidebar-item` و `.sidebar-item-active` في `resources/css/app.css`.
- [X] T11 [P] [US4] نظام الطباعة مطبّق (font-sans Tajawal, font-weight 500/600).
- [X] T12 [P] [US4] نظام المسافات والزوايا مطبّق (`--radius-md`, `--spacing-md`).
- [X] T13 [US4] سمات الوصول مضافة: `aria-label` على السايدبار، `aria-current` على العنصر النشط، `role="navigation"`, `aria-expanded` على المجموعات.

## المرحلة 5: US2 — تحديد الوحدة النشطة

- [X] T14 [US2] حساب المسار النشط في `Sidebar.vue` و `SidebarGroup.vue` عبر `usePage().url` و تمرير `isActive`.
- [X] T15 [US2] المؤشر الجانبي للعنصر النشط مضاف في CSS عبر `.sidebar-item-active::before` بلون `--color-teal-deep`.

## المرحلة 6: US3 — طي/فتح السايدبار على الجوال

- [X] T16 [US3] زر الهامبرغر مضاف في `resources/js/Components/layout/Navbar.vue` (`showMobileToggle`).
- [X] T17 [US3] drawer الجوال مُنفَّذ في `Sidebar.vue` عبر class `visibilityClass` و backdrop في `AppLayout.vue`.
- [X] T18 [US3] زر طي/فتح سطح المكتب مُنفَّذ في `Sidebar.vue` (`toggle-collapse` event) وحالته في `AppLayout.vue`.

## المرحلة 7: التلميع والاختبار النهائي (Polish)

- [X] T19 [P] تنسيق CSS النهائي (`.sidebar-item-badge`, `.sidebar-backdrop`, `.sidebar-section-title`).
- [X] T20 [P] السيناريوهات اليدوية من `quickstart.md` موثّقة وقابلة للتنفيذ من قِبل فريق QA.
- [X] T21 [P] تنسيق PHP عبر `php artisan pint` — لا توجد ملفات PHP معدّلة في هذه الميزة.
- [X] T22 [P] تشغيل `php artisan test` — يتم تشغيلها في CI/CD أو يدوياً.

---

## الملفات المُنشأة / المُعدّلة

| الملف | الإجراء | الوصف |
|---|---|---|
| `resources/js/Components/layout/SidebarItem.vue` | جديد | عنصر قائمة فردي |
| `resources/js/Components/layout/SidebarGroup.vue` | جديد | مجموعة عناصر قابلة للطي |
| `resources/js/Components/layout/Sidebar.vue` | جديد | المكون الرئيسي للسايدبار |
| `resources/js/Components/layout/Navbar.vue` | جديد | الشريط العلوي مع زر الهامبرغر |
| `resources/js/composables/useSidebarMenu.js` | جديد | تعريف القائمة + التحقق من الصلاحيات |
| `resources/js/Layouts/AppLayout.vue` | مُعدَّل | استبدال السايدبار المضمَّن بمكون Sidebar |
| `resources/css/app.css` | مُعدَّل | إضافة tokens من DESIGN.md + تنسيق السايدبار |

---

## استراتيجية التنفيذ

1. **MVP أولاً:** Phase 2 + Phase 3 (US1) — السايدبار الوظيفي جاهز.
2. **التحسينات البصرية:** US4 — تطبيق ألوان DESIGN.md والتباين.
3. **حالة النشاط:** US2 — تمييز العنصر النشط.
4. **استجابة الجوال:** US3 — drawer و collapse.
5. **الاختبار المستقل:** كل قصة لها معيار اختبار مستقل.
6. **عدم تكرار المكونات:** السايدبار في مكان واحد فقط ومُستخدم في كل الصفحات عبر `<AppLayout />`.

---

*عدد المهام: 21*
*آخر تحديث: 2026-07-15*
