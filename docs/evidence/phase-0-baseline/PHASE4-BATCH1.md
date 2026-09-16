# Phase 4 — Batch 1: navigation ordering + module tokens (2026-09-14)

## التدقيق (قراءة فقط)

- بحث sidebar يحترم الصلاحيات فعلاً (`useNavSidebar: visibleGroups → allVisibleItems → searchResults/favorites`) — لا فجوة.
- `CommandPalette.vue` و`navigationModules` (module switcher): الأولى **ميتة** (لا استيراد لها)، الثانية مستهلكة عبر `useNavigation` في `AppLayout`.
- الخام في `navigationModules`: `bg-blue-50/purple-50/cyan-50` خارج نطاق `lint:tokens` (ملف js) لكنه مخالف لروح النظام.

## ما تغيّر (`navigation.js` فقط — ترتيب وألوان، صفر routes/صلاحيات)

1. مجموعة `people`: المستخدمون أولاً (تكرار يومي) بدل التسلسل الهرمي. المفضلة بالـ id والـ active-matching بالـ route — لا أثر.
2. الألوان الخام ← tokens بنفس الدرجة تقريباً: blue→`mistral-info` (#2563eb)، purple→`mistral-status-overtime` (#7c3aed)، cyan→`mistral-status-vacation` (#0891b2)، بنفس نمط `/10`.
3. مؤجل عمداً: ربط CommandPalette (ميزة جديدة بسطح صلاحيات/keyboard — تحتاج مواصفة واختبارات، و`QUICK_ACTIONS` فيها بلا permissions اليوم).

## البوابات

- `lint:tokens`: PASS. `build`: SUCCESS.
- إثبات التوليد: الكلاسات الست في CSS المبني (`bg-*/10` ×2، `text-*` ×1-3).
- اختبارات Auth: 18/18 PASS.
