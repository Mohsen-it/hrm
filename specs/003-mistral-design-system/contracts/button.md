# Button Component Contract

**Path:** `resources/js/Components/ui/Button.vue`
**Status:** NEW
**Category:** UI (Action)

---

## Purpose

The single source of truth for clickable actions across the HRM application. Replaces the current `.btn-primary`, `.btn-secondary`, `.btn-ghost`, `.btn-danger`, `.btn-icon` CSS classes used directly in pages (Constitution Article VII.2 violation).

---

## Props

```yaml
variant:
  type: "'primary' | 'secondary' | 'cream' | 'dark' | 'on-cream' | 'link' | 'danger' | 'ghost' | 'icon'"
  required: false
  default: 'primary'

size:
  type: "'sm' | 'md' | 'lg'"
  required: false
  default: 'md'
  # sm: 32px height, lg: 44px height (WCAG AAA touch target)

type:
  type: "'button' | 'submit' | 'reset'"
  required: false
  default: 'button'

disabled:
  type: boolean
  required: false
  default: false

loading:
  type: boolean
  required: false
  default: false

href:
  type: string
  required: false
  # When set, renders as Inertia <Link> instead of <button>

icon:
  type: string
  required: false
  # Font Awesome 6 class, e.g. "fas fa-plus"

iconPosition:
  type: "'start' | 'end'"
  required: false
  default: 'start'
  # In RTL, "start" is the right side; "end" is the left

block:
  type: boolean
  required: false
  default: false
  # Makes button full-width (used in forms)

ariaLabel:
  type: string
  required: false
  # Required for icon-only buttons

dir:
  type: "'rtl' | 'ltr'"
  required: false
  default: 'rtl'
```

## Emits

```yaml
click:
  payload: MouseEvent
  # Standard click event, only fires when not disabled or loading
```

## Slots

```yaml
default:
  # Button label content. If `icon` is not set, this is the main content.
  # May include additional elements like <span> for sub-text.

icon:
  # Custom icon slot. Overrides the `icon` prop.
  # Example: <template #icon><svg>...</svg></template>
```

## Accessibility

```yaml
role: button
keyboard:
  - Space: triggers click
  - Enter: triggers click
  - Tab: focus
aria-attrs:
  - aria-disabled: when disabled
  - aria-busy: when loading
  - aria-label: required for icon-only (no default slot content)
```

## Variants

```yaml
primary:
  description: Primary CTA — saturated orange
  tokens:
    background: var(--color-mistral-primary)
    text: var(--color-mistral-on-primary)
    border: none
    padding: 10px 20px
    radius: var(--radius-md)
  hover: subtle bg darken 5%
  pressed: var(--color-mistral-primary-deep)
  disabled: var(--color-mistral-hairline) bg, var(--color-mistral-muted) text

secondary:
  description: Secondary action — outlined
  tokens:
    background: transparent
    text: var(--color-mistral-ink)
    border: 1px solid var(--color-mistral-hairline-strong)
    padding: 10px 20px
    radius: var(--radius-md)

cream:
  description: Action on cream surfaces
  tokens:
    background: var(--color-mistral-cream)
    text: var(--color-mistral-ink)
    border: 1px solid var(--color-mistral-beige-deep)
    padding: 10px 20px
    radius: var(--radius-md)

dark:
  description: CTA on cream surfaces
  tokens:
    background: var(--color-mistral-ink)
    text: var(--color-mistral-on-dark)
    border: none
    padding: 10px 20px
    radius: var(--radius-md)

on-cream:
  description: White button on cream background
  tokens:
    background: var(--color-mistral-canvas)
    text: var(--color-mistral-ink)
    border: 1px solid var(--color-mistral-beige-deep)

link:
  description: Inline orange text link
  tokens:
    background: transparent
    text: var(--color-mistral-primary)
    padding: 0
    underline: on hover

danger:
  description: Destructive action
  tokens:
    background: var(--color-mistral-danger)
    text: var(--color-mistral-on-primary)

ghost:
  description: Toolbar button (no border)
  tokens:
    background: transparent
    text: var(--color-mistral-ink)
    padding: 8px 12px
  hover: var(--color-mistral-surface) bg

icon:
  description: Icon-only square button
  tokens:
    background: transparent
    text: var(--color-mistral-ink)
    padding: 8px
    width: 40px (md) / 32px (sm) / 44px (lg)
    height: same as width
  required-aria-label: true
```

## Behavior

- When `loading` is `true`:
  - Replace icon with `LoadingSpinner` size sm
  - Set `aria-busy="true"`
  - Disable pointer events
  - Disable keyboard events
- When `disabled` is `true`:
  - Set `disabled` attribute
  - Apply `cursor-not-allowed`
  - Reduce opacity to 0.5
- When `href` is set:
  - Render as `<Link :href="href">` (Inertia)
  - Skip type="button" — the link handles navigation
- When `block` is `true`:
  - Apply `w-full` class

## Examples

```vue
<!-- Primary CTA -->
<Button variant="primary" @click="save">Save</Button>

<!-- With icon -->
<Button variant="primary" icon="fas fa-plus">Add New</Button>

<!-- Submit form -->
<Button type="submit" variant="primary" :loading="processing">Save</Button>

<!-- Icon-only (requires ariaLabel) -->
<Button variant="icon" icon="fas fa-trash" aria-label="Delete" @click="confirmDelete" />

<!-- Link to a route -->
<Button variant="link" :href="route('users.index')">View all users</Button>

<!-- Block (full-width) in form -->
<Button variant="primary" block type="submit">Sign in</Button>

<!-- Disabled state -->
<Button variant="primary" disabled>Unavailable</Button>
```

## Anti-Patterns (Rejected by Lint)

```vue
<!-- ❌ DO NOT use raw button with btn classes -->
<button class="btn btn-primary">Save</Button>

<!-- ❌ DO NOT use rounded-full on button -->
<Button class="rounded-full">Save</Button>

<!-- ❌ DO NOT use hex colors -->
<Button style="background: #fa520f">Save</Button>

<!-- ❌ DO NOT omit aria-label for icon-only -->
<Button variant="icon" icon="fas fa-trash" @click="del" />
```

---

*Last updated: 2026-07-15*
