# SunsetStripeBand Component Contract

**Path:** `resources/js/Components/layout/SunsetStripeBand.vue`
**Status:** NEW
**Category:** Layout (Brand Signature)

---

## Purpose

The most recognizable element of the Mistral design language — a horizontal multi-stop gradient band that appears at the foot of every page. This component is the brand's continuity element.

---

## Props

```yaml
height:
  type: string
  required: false
  default: '4px'
  # CSS height value; default is the subtle signature line
  # Use '8px' or '12px' for marketing pages

fixed:
  type: boolean
  required: false
  default: true
  # When true, fixed at bottom of viewport
  # When false, at the end of the document flow

zIndex:
  type: number
  required: false
  default: 30
  # Z-index when fixed; should be above content, below modals (50)

dir:
  type: "'rtl' | 'ltr'"
  required: false
  default: 'rtl'
```

## Emits

None.

## Slots

```yaml
default:
  # Optional content to overlay on the band (e.g. text, CTA)
  # Used in marketing pages for "Get started" call-to-action
```

## Visual

```yaml
gradient-stops:
  - var(--color-mistral-primary) 0%
  - var(--color-mistral-sunshine-700) 33%
  - var(--color-mistral-yellow-saturated) 66%
  - var(--color-mistral-cream) 100%
direction: horizontal (left to right in both LTR and RTL)
height: 4px (default) — full width
```

## Placement

- **Authenticated layout (`AppLayout.vue`):** Fixed at bottom of viewport, behind the footer.
- **Auth layout (login, register):** Fixed at bottom of viewport.
- **Marketing pages:** Optional inline use below the hero, full-width.

## Examples

```vue
<!-- Default signature line (4px) -->
<SunsetStripeBand />

<!-- Thicker for marketing -->
<SunsetStripeBand height="8px" :fixed="false" />

<!-- With overlay content -->
<SunsetStripeBand height="120px" :fixed="false">
  <div class="text-center py-12">
    <h2>Ready to get started?</h2>
    <Button variant="primary">Sign up</Button>
  </div>
</SunsetStripeBand>
```

## Anti-Patterns

```vue
<!-- ❌ DO NOT use a custom gradient without these stops -->
<div style="background: linear-gradient(...)" />

<!-- ❌ DO NOT place inside the main scrollable area -->
<main><SunsetStripeBand /></main>

<!-- ❌ DO NOT use solid colors for this element -->
<SunsetStripeBand class="bg-orange-500" />
```

## Browser Compatibility

- CSS `linear-gradient` with multiple stops: supported in all modern browsers.
- Fallback: `background-color: var(--color-mistral-primary)` if gradient fails.
- The 4px line is a perfect fallback (still looks like a brand accent).

---

*Last updated: 2026-07-15*
