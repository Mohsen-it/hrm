# Card Component Contract

**Path:** `resources/js/Components/ui/Card.vue`
**Status:** NEW
**Category:** UI (Container)

---

## Purpose

Standard container for grouped content. Replaces the current `.card` CSS class with a typed variant system. The dominant card radius is 12px (`--radius-lg`).

---

## Props

```yaml
variant:
  type: "'base' | 'feature' | 'cream' | 'cream-soft' | 'feature-product' | 'stat'"
  required: false
  default: 'base'

padding:
  type: "'sm' | 'md' | 'lg' | 'xl' | 'none'"
  required: false
  default: 'md'
  # sm: 16px, md: 24px, lg: 32px, xl: 48px, none: 0

bordered:
  type: boolean
  required: false
  default: true
  # When false, removes the 1px border (used for cards on colored backgrounds)

hoverable:
  type: boolean
  required: false
  default: false
  # When true, adds subtle elevation on hover (Level 1)

as:
  type: "string"
  required: false
  default: 'div'
  # HTML tag, can be 'div', 'section', 'article', 'a', etc.

dir:
  type: "'rtl' | 'ltr'"
  required: false
  default: 'rtl'
```

## Emits

None. Card is a passive container.

## Slots

```yaml
default:
  # Card body content

header:
  # Card header (e.g. Card title, breadcrumb)
  # Padded to match variant

footer:
  # Card footer (e.g. action buttons, meta)
  # Padded to match variant, top border

icon:
  # Optional icon at the top of the card
```

## Accessibility

```yaml
role: 
  - Use `role="region"` when as="section"
  - Use `role="article"` when as="article"
keyboard: none (passive)
aria-attrs:
  - aria-labelledby: when a heading is present, point to its id
```

## Variants

```yaml
base:
  description: Standard content card
  tokens:
    background: var(--color-mistral-canvas)
    border: 1px solid var(--color-mistral-hairline-soft)
    radius: var(--radius-lg) # 12px
    default-padding: md (24px)
  shadow: none

feature:
  description: Feature panel with more padding
  tokens:
    background: var(--color-mistral-canvas)
    border: 1px solid var(--color-mistral-hairline-soft)
    radius: var(--radius-lg)
    default-padding: lg (32px)
  shadow: none

cream:
  description: Cream-tinted feature card
  tokens:
    background: var(--color-mistral-cream)
    text: var(--color-mistral-ink)
    border: 1px solid var(--color-mistral-beige-deep)
    radius: var(--radius-lg)
    default-padding: lg (32px)
  shadow: none

cream-soft:
  description: Lighter cream variant
  tokens:
    background: var(--color-mistral-surface-cream-soft)
    text: var(--color-mistral-ink)
    radius: var(--radius-lg)
    default-padding: lg (32px)

feature-product:
  description: Product showcase with subtle elevation
  tokens:
    background: var(--color-mistral-canvas)
    border: 1px solid var(--color-mistral-hairline-soft)
    radius: var(--radius-lg)
    default-padding: lg (32px)
  shadow: 0 4px 12px rgba(0, 0, 0, 0.04)

stat:
  description: Dashboard stat tile
  tokens:
    background: var(--color-mistral-canvas)
    border: 1px solid var(--color-mistral-hairline-soft)
    radius: var(--radius-lg)
    default-padding: lg (32px)
  contains: typically a number (StatCard) or StatCard child
```

## Examples

```vue
<!-- Basic card -->
<Card>
  <p>Card content</p>
</Card>

<!-- Feature panel -->
<Card variant="feature">
  <h3>Featured</h3>
  <p>Content with more breathing room.</p>
</Card>

<!-- Cream form panel -->
<Card variant="cream" padding="xl">
  <form>
    <FormInput label="Name" v-model="form.name" />
    <FormInput label="Email" v-model="form.email" />
  </form>
</Card>

<!-- Stat card (or use StatCard component) -->
<Card variant="stat">
  <div class="text-3xl font-bold">{{ count }}</div>
  <div class="text-sm text-mistral-steel">Total Users</div>
</Card>

<!-- As an article -->
<Card as="article" variant="feature-product">
  <h3>Article title</h3>
</Card>
```

## Anti-Patterns

```vue
<!-- ❌ DO NOT use raw div with card class -->
<div class="card p-6">...</div>

<!-- ❌ DO NOT use rounded-3xl or arbitrary radius -->
<Card class="rounded-3xl">...</Card>

<!-- ❌ DO NOT use solid colored backgrounds (use cream variant) -->
<Card style="background: #fa520f">...</Card>
```

---

*Last updated: 2026-07-15*
