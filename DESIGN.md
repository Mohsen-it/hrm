---
version: 1.0.0
name: HRM-Design-System-Mistral
description: The canonical design system for the HRM application, inspired by Mistral AI's editorial sunset palette. All color, typography, spacing, radius, elevation, and component tokens are defined in `mistral.ai\DESIGN.md` (the single source of truth). This file exists for historical reference only.

canonical_source: mistral.ai/DESIGN.md
adoption_date: 2026-07-15
status: active
---

# ملاحظة هامة

> **هذا الملف للتوثيق التاريخي فقط.**
>
> **المرجع المرجعي الموحد** لرموز التصميم هو: `mistral.ai\DESIGN.md`
>
> أي تحديث لرموز التصميم يجب أن يُجرى في `mistral.ai\DESIGN.md` ثم يُسحب إلى `resources\css\app.css` عبر متغيرات CSS.

# سجل الإصدارات

| الإصدار | التاريخ | الوصف | الملف المرجعي |
|---------|---------|-------|---------------|
| 1.0.0 | 2026-07-15 | اعتماد Mistral AI كنظام تصميم وحيد | `mistral.ai\DESIGN.md` |
| 0.9.0 | 2026-07-15 | نظام هجين Airtable+Linear+Notion+Supabase (مستبدل) | (هذا الملف، الأسطر أدناه) |
| 0.1.0 | 2026-07-08 | Superhumon-inspired initial system (مستبدل) | (هذا الملف، الأسطر أدناه) |

---

# الإصدار السابق (0.9.0 / 0.1.0) — للتوثيق التاريخي فقط

> **مهم:** لا تستخدم القيم أدناه في كود جديد. استخدم الرموز من `mistral.ai\DESIGN.md`.

```yaml
colors:
  primary: "#1b1938"
  primary-deep: "#0e0c1f"
  on-primary: "#ffffff"
  ink: "#292827"
  ink-mute: "#73706d"
  ink-faint: "#9a9794"
  canvas: "#ffffff"
  canvas-soft: "#fafaf8"
  surface-violet-soft: "#c9b4fa"
  surface-teal-deep: "#0e3030"
  surface-teal-mid: "#155555"
  hairline: "#e8e4dd"
  hairline-dark: "#3f3a52"
  on-dark-mute: "#bcbac9"
  on-dark-faint: "#5a5772"
```

**ملاحظة على الـ migration:** لاستخدام النظام الجديد:

```css
/* ❌ قديم - لا تستخدم */
color: var(--color-primary);
background: var(--color-hairline);

/* ✅ جديد - استخدم */
color: var(--color-mistral-primary);
background: var(--color-mistral-hairline);
```

كل الرموز الجديدة في `resources\css\app.css` باسم `mistral-*` مع aliases للرموز القديمة لمدة release واحد.

---

*آخر تحديث: 2026-07-15*
