# عقد واجهة المكونات - السايدبار

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-15

---

## 1. Sidebar.vue

### الغرض
المكون الرئيسي للقائمة الجانبية. يستقبل قائمة العناصر ويعرضها مع دعم RTL، التجميع، والطي.

### Props

| Prop | النوع | افتراضي | الوصف |
|---|---|---|---|
| `isOpen` | `Boolean` | `true` | حالة الفتح/الإغلاق (للجوال). |
| `isCollapsed` | `Boolean` | `false` | حالة الطي على سطح المكتب. |
| `menuItems` | `Array<MenuItem \| MenuGroup>` | `[]` | عناصر القائمة. |

### Events

| Event | الحمولة | الوصف |
|---|---|---|
| `close` | — | يُطلق عندما يطلب المستخدم إغلاق السايدبار (على الجوال). |
| `toggle-collapse` | — | يُطلق عندما يطلب المستخدم تبديل وضع الطي. |

### Slots

| Slot | الوصف |
|---|---|
| `default` | قائمة العناصر المُمررة عبر `menuItems` تُعرض تلقائياً؛ لا يُستخدم slot إلا لحالات التخصيص النادرة. |

### مثال الاستخدام

```vue
<Sidebar
  :is-open="isSidebarOpen"
  :is-collapsed="isSidebarCollapsed"
  :menu-items="menuItems"
  @close="isSidebarOpen = false"
  @toggle-collapse="isSidebarCollapsed = !isSidebarCollapsed"
/>
```

---

## 2. SidebarGroup.vue

### الغرض
يعرض مجموعة من العناصر تحت عنوان واحد قابل للطي/الفتح.

### Props

| Prop | النوع | افتراضي | الوصف |
|---|---|---|---|
| `group` | `MenuGroup` | — | بيانات المجموعة. |
| `isCollapsed` | `Boolean` | `false` | هل السايدبار في وضع الأيقونات فقط. |

### Events

لا توجد events.

### Slots

| Slot | الوصف |
|---|---|
| `children` | يُستخدم داخلياً لعرض `SidebarItem` children. |

---

## 3. SidebarItem.vue

### الغرض
عنصر قائمة واحد في السايدبار.

### Props

| Prop | النوع | افتراضي | الوصف |
|---|---|---|---|
| `item` | `MenuItem` | — | بيانات العنصر. |
| `isCollapsed` | `Boolean` | `false` | هل السايدبار في وضع الأيقونات فقط. |
| `isActive` | `Boolean` | `false` | هل العنصر هو العنصر النشط. |

### Events

| Event | الحمولة | الوصف |
|---|---|---|
| `navigate` | `MenuItem` | يُطلق عند النقر على العنصر (اختياري، يمكن استخدام Inertia link مباشرة). |

### Slots

لا توجد slots.

---

## 4. AppLayout.vue

### التغييرات المطلوبة

- تمرير `isSidebarOpen` و `isSidebarCollapsed` إلى `Sidebar`.
- تمرير `menuItems` إلى `Sidebar`.
- إضافة زر الهامبرغر في `Navbar` على الشاشات الصغيرة.
- إضافة `backdrop` للجوال.

### Props

لا توجد تغييرات في props الحالية.

### State الجديد

| State | النوع | الوصف |
|---|---|---|
| `isSidebarOpen` | `ref<Boolean>` | للتحكم في drawer الجوال. |
| `isSidebarCollapsed` | `ref<Boolean>` | للتحكم في طي سطح المكتب. |

---

## 5. أنواع البيانات

راجع `data-model.md` للتعريف الكامل لـ `MenuItem` و `MenuGroup`.

---

*آخر تحديث: 2026-07-15*
