# Component Inventory Snapshot — 2026-07-15

**Purpose:** Baseline snapshot of all UI components BEFORE Mistral design system migration.
**Used for:** Diff comparison after migration to detect missed components.

## Existing UI Components (`resources\js\Components\ui\`)

| File | Status | Notes |
|------|--------|-------|
| `Alert.vue` | exists | Uses `.alert-*` CSS classes |
| `Badge.vue` | exists | Variants: active, inactive, pending, absent, overtime, vacation, info |
| `ConfirmDialog.vue` | exists | Uses internal CSS, not Card |
| `DataTable.vue` | exists | Uses CSS vars directly |
| `EmptyState.vue` | exists | — |
| `FormInput.vue` | exists | 40px height, 1px hairline border |
| `FormModal.vue` | exists | Uses `.modal-overlay` |
| `FormSelect.vue` | exists | Native select with custom arrow |
| `FormTextarea.vue` | exists | — |
| `LoadingSpinner.vue` | exists | — |
| `PageHeader.vue` | exists | Custom typography |
| `SearchInput.vue` | exists | — |
| `index.js` | exists | 12 exports |

## Existing Layout Components (`resources\js\Components\layout\`)

| File | Status |
|------|--------|
| `Navbar.vue` | exists |
| `Sidebar.vue` | exists (per spec 002) |
| `SidebarGroup.vue` | exists |
| `SidebarItem.vue` | exists |

## Existing CSS Utility Classes (`resources\css\app.css`)

| Class | Lines | Purpose |
|-------|-------|---------|
| `.card` | 158-163 | Container |
| `.btn` | 192-205 | Button base |
| `.btn-primary` | 207-219 | Primary action |
| `.btn-secondary` | 221-228 | Secondary action |
| `.btn-danger` | 230-238 | Destructive action |
| `.btn-ghost` | 240-248 | Toolbar button |
| `.btn-icon` | 250-260 | Icon-only button |
| `.form-input` | 263-289 | Form field base |
| `.form-checkbox` | 292-303 | Checkbox |
| `select.form-input` | 306-319 | Select with arrow |
| `.badge` | 322-331 | Badge base |
| `.sidebar*` | 334-425 | Sidebar (per spec 002) |
| `.navbar` | 428-432 | Navbar |
| `.alert*` | 435-469 | Alerts |
| `.modal-overlay` | 472-477 | Modal backdrop |

## CSS Variables (current)

- `--font-sans`, `--font-monospace`, `--font-latin`
- `--color-primary`, `--color-primary-hover`, `--color-on-primary`
- `--color-canvas`, `--color-canvas-soft`, `--color-surface-1/2/3`
- `--color-hairline`, `--color-hairline-strong`
- `--color-ink`, `--color-ink-mute`, `--color-ink-faint`, `--color-ink-muted`, `--color-ink-disabled`
- `--color-teal-deep`, `--color-violet-soft`
- `--color-success`, `--color-warning`, `--color-danger`, `--color-info` (and bg variants)
- `--radius-sm/md/lg/xl/full`
- `--shadow-level-0/1/2/3/4`
- `--spacing-xs/sm/md/lg/xl/xxl/page`

## Pages Currently Using Raw HTML with CSS Classes

Sample (from `Pages/Companies/Index.vue`):
- `<Link class="btn btn-primary">` — should become `<Button variant="primary">`
- `<input class="form-input">` — should become `<FormInput>`
- `<select class="form-input">` — should become `<FormSelect>`

## Migration Targets (post-implementation)

| Old | New |
|-----|-----|
| `class="btn btn-primary"` | `<Button variant="primary">` |
| `class="btn btn-secondary"` | `<Button variant="secondary">` |
| `class="btn btn-ghost"` | `<Button variant="ghost">` |
| `class="btn btn-danger"` | `<Button variant="danger">` |
| `class="btn btn-icon"` | `<Button variant="icon">` |
| `class="card"` | `<Card>` |
| `<input class="form-input">` | `<FormInput>` |
| `<select class="form-input">` | `<FormSelect>` |
| `<textarea class="form-input">` | `<FormTextarea>` |
| `class="form-checkbox"` | `<FormCheckbox>` |

---

*Snapshot taken: 2026-07-15*
