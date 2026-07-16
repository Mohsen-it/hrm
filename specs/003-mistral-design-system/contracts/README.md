# Component API Contracts

**Feature:** 003-mistral-design-system
**Date:** 2026-07-15

This directory contains the **public API contracts** for the design system's Vue components. Each contract specifies:
- Component name and file path
- Props (inputs) with type, default, and validation
- Emits (outputs) with payload
- Slots for composition
- Behavioral contract (focus, keyboard, accessibility)

Contracts are binding — breaking changes require a deprecation cycle.

---

## Files

| Contract | Component | Status |
|----------|-----------|--------|
| [button.md](./button.md) | `Button` | New |
| [card.md](./card.md) | `Card` | New |
| [form-checkbox.md](./form-checkbox.md) | `FormCheckbox` | New |
| [form-radio.md](./form-radio.md) | `FormRadio` | New |
| [form-switch.md](./form-switch.md) | `FormSwitch` | New |
| [form-group.md](./form-group.md) | `FormGroup` | New |
| [tabs.md](./tabs.md) | `Tabs` | New |
| [stat-card.md](./stat-card.md) | `StatCard` | New |
| [sunset-stripe-band.md](./sunset-stripe-band.md) | `SunsetStripeBand` | New |
| [breadcrumb.md](./breadcrumb.md) | `Breadcrumb` | New |
| [pagination.md](./pagination.md) | `Pagination` | New |
| [avatar.md](./avatar.md) | `Avatar` | New |
| [icon-button.md](./icon-button.md) | `IconButton` | New |
| [form-datepicker.md](./form-datepicker.md) | `FormDatepicker` | New |
| [alert.md](./alert.md) | `Alert` | Updated |
| [badge.md](./badge.md) | `Badge` | Updated |
| [data-table.md](./data-table.md) | `DataTable` | Updated |
| [form-input.md](./form-input.md) | `FormInput` | Updated |
| [form-modal.md](./form-modal.md) | `FormModal` | Updated |
| [form-select.md](./form-select.md) | `FormSelect` | Updated |
| [form-textarea.md](./form-textarea.md) | `FormTextarea` | Updated |
| [page-header.md](./page-header.md) | `PageHeader` | Updated |
| [search-input.md](./search-input.md) | `SearchInput` | Updated |
| [sidebar.md](./sidebar.md) | `Sidebar` | Updated |
| [confirm-dialog.md](./confirm-dialog.md) | `ConfirmDialog` | Updated |
| [loading-spinner.md](./loading-spinner.md) | `LoadingSpinner` | Updated |
| [empty-state.md](./empty-state.md) | `EmptyState` | Updated |

---

## Convention

All component contracts follow this template:

```yaml
ComponentName:
  path: resources/js/Components/{category}/ComponentName.vue
  status: new | updated | stable
  category: ui | layout
  purpose: One-line description
  
  props:
    - name: propName
      type: string | number | boolean | array | object | function
      required: true | false
      default: <value or fn>
      validation: rule if any
  
  emits:
    - event: event-name
      payload: { field: type }
  
  slots:
    - name: slotName
      purpose: Description
  
  accessibility:
    role: <ARIA role>
    keyboard: <Tab/Enter/Esc behavior>
    aria-attrs: <list>
  
  variants:
    - name: variantName
      description: Visual style
      tokens-used: <list of color/spacing/radius tokens>
  
  examples:
    - code: <minimal usage>
```

---

## Cross-Cutting Concerns

### RTL Support

All components must accept a `dir` prop:

```js
defineProps({
    dir: { type: String, default: 'rtl' }
});
```

Internally use Tailwind logical properties: `ms-2` (margin-start), `me-2` (margin-end), `ps-4` (padding-start), `pe-4` (padding-end).

### Focus Ring

All interactive components apply:

```css
focus-visible: outline-2 outline-[var(--color-mistral-primary)] outline-offset-2
```

### Loading State

All action components (Button, FormSubmit) support:

```js
loading: { type: Boolean, default: false }
```

When `true`:
- Show `LoadingSpinner` size `sm`
- Disable pointer events
- Set `aria-busy="true"`

### Disabled State

All interactive components support:

```js
disabled: { type: Boolean, default: false }
```

When `true`:
- Apply `cursor-not-allowed opacity-50`
- Remove from tab order (`tabindex="-1"`)
- Set `aria-disabled="true"`

### TypeScript Definitions (Future)

Components are written in `.vue` (JavaScript) today. When the project adopts TypeScript (separate spec), these contracts become `.d.ts` files co-located with each component.

---

*Last updated: 2026-07-15*
