# نموذج البيانات - إعادة تصميم واجهات السايدبار

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-15

---

## ملاحظة

هذه الميزة لا تتطلب جداول قاعدة بيانات جديدة. البيانات المُستخدمة هي بيانات موجودة مسبقاً (الصلاحيات، أسماء الوحدات، المسارات). الوثيقة التالية تصف **نموذج المكونات (Component Model)** — أي props، state، والبيانات المُمررة بين المكونات.

---

## كيان: Sidebar

**المسار:** `resources/js/Components/layout/Sidebar.vue`

### Props

| الاسم | النوع | الإلزامية | الوصف |
|---|---|---|---|
| `isOpen` | `Boolean` | نعم | يحدد ما إذا كان السايدبار مفتوحاً (مفيد على الجوال). |
| `isCollapsed` | `Boolean` | لا (افتراضي: `false`) | يحدد وضع الطي على سطح المكتب. |
| `menuItems` | `Array<MenuItem \| MenuGroup>` | نعم | قائمة العناصر والمجموعات المراد عرضها. |

### State

| الاسم | النوع | الوصف |
|---|---|---|
| `activeRoute` | `Computed<String>` | المسار النشط الحالي مأخوذ من `usePage().url`. |
| `visibleItems` | `Computed<Array>` | العناصر التي يملك المستخدم صلاحية عرضها. |

---

## كيان: SidebarGroup

**المسار:** `resources/js/Components/layout/SidebarGroup.vue`

### Props

| الاسم | النوع | الإلزامية | الوصف |
|---|---|---|---|
| `group` | `MenuGroup` | نعم | بيانات المجموعة (العنوان، الأيقونة، الأطفال). |
| `isCollapsed` | `Boolean` | لا (افتراضي: `false`) | هل السايدبار في وضع الأيقونات فقط. |

### State

| الاسم | النوع | الوصف |
|---|---|---|
| `isExpanded` | `Boolean` | يحدد ما إذا كانت المجموعة مفتوحة. |

---

## كيان: SidebarItem

**المسار:** `resources/js/Components/layout/SidebarItem.vue`

### Props

| الاسم | النوع | الإلزامية | الوصف |
|---|---|---|---|
| `item` | `MenuItem` | نعم | بيانات عنصر القائمة. |
| `isCollapsed` | `Boolean` | لا (افتراضي: `false`) | هل السايدبار في وضع الأيقونات فقط. |
| `isActive` | `Boolean` | لا | هل العنصر هو العنصر النشط. |

---

## أنواع البيانات (Type Definitions)

```typescript
// MenuItem
interface MenuItem {
  id: string;           // معرف فريد (مثال: companies)
  label: string;        // مفتاح الترجمة (مثال: __('companies.title'))
  icon: string;         // اسم أيقونة Font Awesome (مثال: fa-building)
  route: string;        // اسم المسار (مثال: companies.index)
  permission?: string;  // الصلاحية المطلوبة (مثال: view-companies)
  badge?: number;       // عدد الإشعارات/العناصر المعلقة (اختياري)
}

// MenuGroup
interface MenuGroup {
  id: string;
  label: string;
  icon: string;
  permission?: string;
  children: MenuItem[];
}
```

---

## بيانات Inertia Shared

يجب أن توفر الطبقة الخلفية للواجهة الأمامية:

```typescript
// usePage().props.auth.permissions
permissions: string[];  // قائمة بأسماء الصلاحيات التي يملكها المستخدم الحالي
```

---

## قواعد التحقق (Validation)

1. يجب أن يكون `id` فريداً ضمن السايدبار.
2. يجب أن يكون `route` مسجلاً في تعريفات Inertia/Ziggy.
3. إذا وُجد `permission` يجب أن يكون المستخدم يملكها لعرض العنصر.
4. يجب ألا يتجاوز عمق القائمة مستويين (مجموعة → عنصر).

---

*آخر تحديث: 2026-07-15*
