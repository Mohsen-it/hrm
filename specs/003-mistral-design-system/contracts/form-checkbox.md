# FormCheckbox Component Contract

**Path:** `resources/js/Components/ui/FormCheckbox.vue`
**Status:** NEW
**Category:** UI (Form Control)

---

## Purpose

Custom-styled checkbox using a wrapper element + Font Awesome 6 icon. The native `<input type="checkbox" class="form-checkbox">` used in pages today is replaced by this component to ensure cross-browser visual consistency and RTL correctness.

---

## Props

```yaml
modelValue:
  type: boolean | array
  required: true
  # When array, the value prop must also be set; checkbox is checked when array.includes(value)
  # v-model:modelValue support

value:
  type: string | number | boolean
  required: false
  # When modelValue is array, this is the value to add/remove

label:
  type: string
  required: false
  # Visible label text

disabled:
  type: boolean
  required: false
  default: false

indeterminate:
  type: boolean
  required: false
  default: false
  # Shows a horizontal line in the box (used for "select all" partial state)

name:
  type: string
  required: false
  # HTML name attribute for form submission

id:
  type: string
  required: false
  # HTML id; auto-generated if not provided

error:
  type: string
  required: false
  # Error message displayed below the checkbox

hint:
  type: string
  required: false
  # Helper text displayed below the checkbox

size:
  type: "'sm' | 'md' | 'lg'"
  required: false
  default: 'md'
  # sm: 14px box, md: 16px box, lg: 20px box

required:
  type: boolean
  required: false
  default: false

dir:
  type: "'rtl' | 'ltr'"
  required: false
  default: 'rtl'
```

## Emits

```yaml
update:modelValue:
  payload: boolean | array
  # Emitted when checkbox is toggled
  # If modelValue is array, emits array with value added/removed
  # If modelValue is boolean, emits true/false

change:
  payload: Event
  # Native change event
```

## Slots

```yaml
default:
  # Override the entire label area (instead of using the label prop)
  # Example: include rich HTML in the label

description:
  # Optional description below the label
```

## Accessibility

```yaml
role: checkbox
keyboard:
  - Space: toggle
  - Tab: focus
aria-attrs:
  - aria-checked: 'true' | 'false' | 'mixed'
  - aria-disabled: when disabled
  - aria-required: when required
  - aria-invalid: when error
  - aria-describedby: points to error or hint text id
```

## Visual States

```yaml
unchecked:
  box: 1px var(--color-mistral-hairline-strong) border
  bg: var(--color-mistral-canvas)
  radius: var(--radius-xs) # 4px

checked:
  box: 1px var(--color-mistral-primary) border
  bg: var(--color-mistral-primary)
  icon: Font Awesome fa-check (white)
  radius: var(--radius-xs)

indeterminate:
  box: 1px var(--color-mistral-primary) border
  bg: var(--color-mistral-primary)
  icon: Font Awesome fa-minus (white)
  radius: var(--radius-xs)

hover (unchecked):
  border: var(--color-mistral-primary)

focus:
  outline: 2px solid var(--color-mistral-primary)
  outline-offset: 2px

disabled:
  box: 1px var(--color-mistral-hairline) border
  bg: var(--color-mistral-surface)
  icon: var(--color-mistral-muted)
  cursor: not-allowed
  opacity: 0.6

error:
  box: 1px var(--color-mistral-danger) border
  message: var(--color-mistral-danger) text below
```

## Examples

```vue
<!-- Single boolean checkbox -->
<FormCheckbox v-model="agreed" label="I agree to terms" />

<!-- Required checkbox -->
<FormCheckbox v-model="remember" label="Remember me" required />

<!-- With error -->
<FormCheckbox v-model="terms" label="Accept terms" :error="errors.terms" />

<!-- Array (multi-select) -->
<FormCheckbox
  v-model="selectedPermissions"
  value="create-companies"
  label="Can create companies"
/>
<FormCheckbox
  v-model="selectedPermissions"
  value="edit-companies"
  label="Can edit companies"
/>

<!-- Indeterminate (parent of nested checkboxes) -->
<FormCheckbox
  v-model="allSelected"
  :indeterminate="someSelected"
  label="Select all"
/>

<!-- With description slot -->
<FormCheckbox v-model="notifications">
  Email notifications
  <template #description>
    Receive updates about your account activity
  </template>
</FormCheckbox>

<!-- Disabled -->
<FormCheckbox v-model="locked" label="Locked setting" disabled />
```

## Anti-Patterns

```vue
<!-- ❌ DO NOT use raw input -->
<input type="checkbox" class="form-checkbox" v-model="agreed" />

<!-- ❌ DO NOT change the box color directly -->
<FormCheckbox class="bg-green-500" />

<!-- ❌ DO NOT use a different icon library -->
```

---

*Last updated: 2026-07-15*
