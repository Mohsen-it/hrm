---
version: 1.0.0
name: HRM-Design-System
description: "A professional B2B HRM admin dashboard built for Arabic RTL. Combines Airtable's data-table density (for employee/attendance grids), Linear's clean card and button language (for dashboards and stats), Notion's sidebar navigation (for module hierarchy), and Supabase's admin panel clarity (for settings and forms). The system is white-canvas based (#ffffff) with a blue-primary accent (#2563eb) suitable for enterprise HR software in the Middle East."
sources:
  - "awesome-design-md-main/design-md/airtable"
  - "awesome-design-md-main/design-md/linear.app"
  - "awesome-design-md-main/design-md/notion"
  - "awesome-design-md-main/design-md/supabase"
---

# DESIGN.md — HRM System

## Design Language Overview

HRM is an enterprise-grade **admin dashboard** for Human Resource Management. It prioritizes:

- **Data density** — tables are the primary interface (employees, attendance, payroll)
- **Clarity** — Arabic RTL layout with clean typography and generous whitespace
- **Efficiency** — forms are compact, actions are one-click, navigation is always visible
- **Professionalism** — blue-primary (#2563eb), neutral grays, no decorative flourishes

### Design Mood

| Attribute | Value |
|-----------|-------|
| **Vibe** | Professional, enterprise, data-heavy |
| **Dominant element** | Data tables (Airtable-inspired) |
| **Navigation** | Persistent sidebar (Notion-inspired) |
| **Cards** | Clean, minimal, hairline-bordered (Linear-inspired) |
| **Forms** | Compact, inline-validation (Supabase-inspired) |
| **Language** | Arabic (RTL) primary, English secondary |

---

## Colors

### Brand & Accent

```yaml
primary: "#2563eb"           # Blue-600 — primary CTA, links, active tab
primary-hover: "#1d4ed8"     # Blue-700 — button hover
primary-light: "#dbeafe"     # Blue-100 — selection, highlight, badge bg
on-primary: "#ffffff"        # White text on primary
on-primary-light: "#1e3a5f"  # Dark text on light blue bg
```

### Surface

```yaml
canvas: "#ffffff"            # Page background
surface-1: "#f8fafc"         # Card background, table stripe
surface-2: "#f1f5f9"         # Sidebar, section header
surface-3: "#e2e8f0"         # Hover row, dropdown bg
hairline: "#e2e8f0"          # Table borders, card borders, dividers
hairline-strong: "#cbd5e1"   # Input focus border, strong dividers
```

### Text

```yaml
ink: "#0f172a"               # Primary text (headings, table cells)
ink-muted: "#475569"         # Body text, form labels
ink-subtle: "#94a3b8"        # Placeholder, helper text, metadata
ink-disabled: "#cbd5e1"      # Disabled text
inverse: "#ffffff"           # White text on dark surfaces
```

### Semantic

```yaml
success: "#16a34a"           # Green — active status, approved, check-in
success-bg: "#dcfce7"        # Green background — success badge
warning: "#d97706"           # Amber — pending, overtime
warning-bg: "#fef3c7"        # Amber background
danger: "#dc2626"            # Red — error, deletion, absent
danger-bg: "#fee2e2"         # Red background
info: "#2563eb"              # Blue — info badge
info-bg: "#dbeafe"           # Blue background
```

### Status Badge Colors (HRM-specific)

```yaml
status-active: "#16a34a"     # Active employee, present
status-inactive: "#94a3b8"   # Inactive, resigned
status-pending: "#d97706"    # Pending approval (vacation, overtime)
status-absent: "#dc2626"     # Absent, missing punch
status-overtime: "#7c3aed"   # Overtime (purple)
status-vacation: "#0891b2"   # On vacation (cyan)
```

---

## Typography

### Font Family

```yaml
font-primary: "'Tajawal', 'Cairo', 'Noto Sans Arabic', sans-serif"   # Arabic UI
font-monospace: "'IBM Plex Mono', 'Courier New', monospace"          # Code, IDs
font-latin: "'Inter', 'SF Pro Display', -apple-system, sans-serif"   # English fallback
```

### Hierarchy

```yaml
display-lg:
  fontFamily: "{font-primary}"
  fontSize: 32px
  fontWeight: 700
  lineHeight: 1.25
  usage: "Page titles (Index.vue headers)"

display-md:
  fontFamily: "{font-primary}"
  fontSize: 24px
  fontWeight: 600
  lineHeight: 1.30
  usage: "Section headers, Create/Edit page titles"

heading:
  fontFamily: "{font-primary}"
  fontSize: 18px
  fontWeight: 600
  lineHeight: 1.40
  usage: "Card titles, modal headers, sidebar section labels"

subheading:
  fontFamily: "{font-primary}"
  fontSize: 14px
  fontWeight: 600
  lineHeight: 1.50
  letterSpacing: 0.5px
  usage: "Table column headers, form section labels"

body:
  fontFamily: "{font-primary}"
  fontSize: 14px
  fontWeight: 400
  lineHeight: 1.50
  usage: "Table cells, form inputs, paragraph text"

body-sm:
  fontFamily: "{font-primary}"
  fontSize: 12px
  fontWeight: 400
  lineHeight: 1.40
  usage: "Metadata, timestamps, helper text, badge content"

button:
  fontFamily: "{font-primary}"
  fontSize: 14px
  fontWeight: 600
  lineHeight: 1.20
  usage: "All button labels"

caption:
  fontFamily: "{font-primary}"
  fontSize: 11px
  fontWeight: 400
  lineHeight: 1.30
  usage: "Small stats, footer text, form errors"
```

---

## RTL Layout System

### Direction Rules

```yaml
default-direction: "rtl"             # Arabic primary
default-language: "ar"               # Arabic language code
sidebar-position: "right"            # Sidebar on the RIGHT (RTL)
content-margin: "mr-64"              # Right margin for sidebar
text-align: "right"                  # All text right-aligned
```

### Spacing (RTL-aware)

```yaml
spacing-xs: 4px
spacing-sm: 8px
spacing-md: 16px
spacing-lg: 24px
spacing-xl: 32px
spacing-xxl: 48px
spacing-page: 24px                  # Page padding (left/right in LTR becomes right/left in RTL)
```

### Layout Mappings (LTR → RTL)

```css
/* Tailwind RTL equivalents */
mr-4 → ml-4     /* margin-right → margin-left */
pl-2 → pr-2     /* padding-left → padding-right */
left-0 → right-0
text-left → text-right
border-l → border-r
rounded-l → rounded-r
space-x-4 → space-x-reverse space-x-4
```

---

## Border Radius

```yaml
rounded-sm: 4px      # Inputs, small buttons, badges
rounded-md: 6px      # Primary buttons, cards, modals
rounded-lg: 8px      # DataTable container, large cards
rounded-xl: 12px     # Modals, dropdown menus
rounded-full: 9999px # Avatars, status dots, pill badges
```

---

## Elevation & Shadows

```yaml
level-0: "none"                                                # Flat cards, table rows
level-1: "0 1px 2px rgba(0,0,0,0.05)"                          # Default card, sidebar
level-2: "0 4px 6px -1px rgba(0,0,0,0.07)"                     # Dropdown, hover card
level-3: "0 10px 25px -3px rgba(0,0,0,0.10)"                   # Modal, dialog
level-4: "0 20px 50px -5px rgba(0,0,0,0.15)"                   # Full-screen modal
```

---

## Components

### Navigation

**`sidebar`** — Persistent right-side navigation (Notion-inspired)
- Width: 260px (on desktop), collapses to 64px (icon-only)
- Background: `{colors.surface-2}` (#f1f5f9)
- Border-left: 1px `{colors.hairline}` (since RTL)
- Menu items: 40px height, `{radius.md}` 6px on hover/active
- Active item: `{colors.primary-light}` bg + `{colors.primary}` text
- Top section: Logo + company name (32px padding)
- Bottom section: Language switch + user menu

**`navbar`** — Top horizontal bar
- Height: 56px
- Background: `{colors.canvas}` (#ffffff)
- Border-bottom: 1px `{colors.hairline}`
- Contains: Breadcrumb (right), Notifications + User avatar (left)

### Data Tables (Airtable-inspired — PRIMARY INTERFACE)

**`data-table`** — THE most used component in the HRM system

| Property | Value | RTL Note |
|----------|-------|----------|
| Background | `{colors.canvas}` | — |
| Header bg | `{colors.surface-1}` | — |
| Row height | 48px | — |
| Stripe | `{colors.surface-1}` on even rows | — |
| Hover | `{colors.surface-3}` | — |
| Border | 1px `{colors.hairline}` | — |
| Radius | `{rounded.lg}` 8px container | — |
| Header text | `{typography.subheading}` | `text-right` |
| Cell text | `{typography.body}` | `text-right` |
| Sort icon | After column name | Flips in RTL |
| Pagination | Bottom, right-aligned | RTL pagination |
| Checkbox | First column | Stays right in RTL |
| Actions | Last column | Stays leftmost in RTL |

**Empty state:** Centered `{component.empty-state}` with icon + text + optional CTA

### Forms (Supabase-inspired)

**`form-input`** — Standard text input
- Height: 40px
- Background: `{colors.canvas}`
- Border: 1px `{colors.hairline}`, `{rounded.sm}` 4px
- Focus: 2px `{colors.primary-light}` ring + `{colors.primary}` border
- Text: `{typography.body}`, `{colors.ink}`
- Placeholder: `{colors.ink-subtle}`, `{typography.body}`
- Label: Above input, `{typography.subheading}`, `{colors.ink-muted}`, `text-align: right`
- Error: Below input, `{typography.caption}`, `{colors.danger}`
- Disabled: `{colors.surface-1}` bg, `{colors.ink-disabled}` text
- RTL: `direction: rtl; text-align: right`

**`form-select`** — Dropdown select
- Same dimensions as `{component.form-input}`
- Chevron icon on the LEFT (RTL — flips from default)
- Options dropdown: `{level-2}` shadow

**`form-datepicker`** — Date picker (with Hijri/Gregorian support)
- Same dimensions as `{component.form-input}`
- Calendar opens on the RIGHT (RTL)
- Week starts Sunday (Arab world convention)

**`form-textarea`** — Multi-line input
- Same styling as `{component.form-input}`
- Min-height: 80px
- Resize: vertical only

### Buttons

**`button-primary`** — Default action button (Linear-inspired)
- Background: `{colors.primary}`, text: `{colors.on-primary}`
- Height: 36px, padding: 8px 16px
- Radius: `{rounded.md}` 6px
- Font: `{typography.button}`
- Hover: `{colors.primary-hover}`
- Disabled: 50% opacity, no hover

**`button-secondary`** — Secondary/outline button
- Background: `{colors.canvas}`, text: `{colors.ink}`
- Border: 1px `{colors.hairline-strong}`
- Same dimensions as primary

**`button-danger`** — Destructive action (delete)
- Background: `{colors.danger}`, text: `{colors.inverse}`
- Same dimensions as primary

**`button-ghost`** — Text-only button
- Background: transparent, text: `{colors.ink-muted}`
- Hover: `{colors.surface-2}` bg
- Padding: 6px

**`button-icon`** — Icon-only button (edit, delete, view)
- Size: 32x32px
- Radius: `{rounded.md}` 6px
- Background: transparent
- Hover: `{colors.surface-2}`

### Modals & Dialogs

**`form-modal`** — Primary modal for Create/Edit forms
- Width: 480px (sm), 640px (md), 800px (lg)
- Background: `{colors.canvas}`
- Radius: `{rounded.xl}` 12px
- Shadow: `{level-3}`
- Overlay: `rgba(0,0,0,0.50)` (RTL: overlay is uniform)
- Header: 16px padding, border-bottom, `{typography.heading}`
- Body: 24px padding
- Footer: 16px padding, border-top, buttons left-aligned (RTL: right-aligned)
- Close: X button top-left corner (RTL mirror)
- Animation: fade + scale (centered, no directional slide)

**`confirm-dialog`** — Delete confirmation
- Width: 400px
- Icon: Warning/trash icon centered at top
- Text: "Are you sure?" + description
- Actions: Cancel (secondary) + Confirm (danger) side by side

### Cards (StatCards — Linear-inspired)

**`stat-card`** — Dashboard statistics card
- Background: `{colors.canvas}`
- Border: 1px `{colors.hairline}`
- Radius: `{rounded.lg}` 8px
- Padding: 16px
- Shadow: `{level-1}`
- Content: Icon (top-right for RTL), Value (large), Label (small), Trend (optional)
- Width: 280px (fixed) or flex-grow in grid
- Grid: 4-up desktop, 2-up tablet, 1-up mobile

### Badges & Status

**`badge`** — Status indicator
- Height: 22px
- Padding: 2px 8px
- Radius: `{rounded-full}` (pill shape)
- Font: `{typography.body-sm}`
- Variants: `{colors.status-active}`, `{colors.status-inactive}`, `{colors.status-pending}`, etc.
- Dot variant: 8px circle before text (RTL: circle AFTER text when `flex-row-reverse`)

### Pagination

**`pagination`** — RTL page navigation
- Alignment: flex with `justify-center` (centered)
- Direction: RTL (page 1 is rightmost)
- Button: 36x36px, `{rounded.md}`, `{button-ghost}` style
- Active: `{colors.primary}` bg, white text
- Info: "Page X of Y" between buttons

### Alerts

**`alert`** — Success/Error/Warning/Info messages
- Padding: 12px 16px
- Radius: `{rounded.md}` 6px
- Border-left: 4px solid (RTL: `border-right: 4px solid`)
- Variants match semantic colors
- Dismiss: X button
- Position: Top of page, below navbar, centered

### Search

**`search-input`** — Global table search
- Width: 280px
- Height: 36px
- Icon: Search icon on the RIGHT (RTL)
- Same input styling as `{component.form-input}`
- Debounce: 300ms

### Empty State

**`empty-state`** — No data placeholder
- Centered content (vertically + horizontally)
- Icon: 64x64px, `{colors.ink-subtle}`
- Title: `{typography.heading}`, `{colors.ink-muted}`
- Description: `{typography.body}`, `{colors.ink-subtle}`
- CTA: Optional `{component.button-primary}` to create first record

---

## Page Layout Patterns

### Index Page (List View)
```
PageHeader (title + create button)
  ├── SearchInput + Filter bar
  ├── DataTable (with pagination)
  └── EmptyState (if no data)
```

### Create/Edit Page (Form View)
```
PageHeader (title + back button)
  ├── FormModal or FormPage
  │   ├── FormInput × N
  │   ├── FormSelect × N
  │   ├── FormDatepicker × N
  │   └── Submit + Cancel buttons
  └── Alert (on success/error)
```

### Dashboard
```
PageHeader (title)
  ├── StatCard grid (4-up)
  ├── DataTable (latest records)
  ├── Chart (optional — lazy loaded)
  └── Activity feed (list)
```

---

## Do's and Don'ts

### Do
- Use `{component.data-table}` for **ALL** lists — never write `<table>` manually
- Keep table columns dense but readable (48px row height)
- Use `{component.form-input}` for **ALL** inputs — never raw `<input>`
- Use `{component.form-modal}` for **ALL** modals — never custom `<div>` overlays
- Align ALL text to the right (`text-right` in Tailwind)
- Use `dir="rtl"` on ALL containers
- Flip margins/padding: `mr-*` → `ml-*`, `pl-*` → `pr-*`, `left-*` → `right-*`
- Flip icon directions: chevrons, arrows, sort indicators using `transform: scaleX(-1)`
- Use status badges for all stateful data (active/inactive/pending)
- Lazy-load charts and heavy components

### Don't
- Don't use pill-shaped buttons (radius is 6px, not 9999px)
- Don't add decorative gradients or background patterns
- Don't use atmospheric illustrations — data is the visual
- Don't bold body text — use `{typography.body}` weight 400
- Don't right-pad in RTL — use left-padding (spacing is mirrored)
- Don't put actions on the right side of table rows — they go on the LEFT in RTL
- Don't use full-page reloads — this is an SPA
- Don't build custom components when shared ones exist

---

## Responsive Behavior

### Breakpoints

```yaml
desktop: 1280px        # Full sidebar + 4-up stat grid
tablet: 1024px         # Sidebar collapses to icon-only, 2-up stats
mobile-lg: 768px       # Sidebar hidden (hamburger), 1-up stats
mobile: 480px          # Single column, compact tables
```

### Collapsing Strategy
- **Sidebar:** Full (260px) → Icons-only (64px) → Hidden (hamburger)
- **DataTable:** All columns → Hide non-essential cols → Scrollable horizontally
- **StatCard grid:** 4-up → 2-up → 1-up
- **Modals:** Centered → Full-screen sheet on mobile

### Touch Targets
- Buttons: minimum 36x36px (WCAG compliant in RTL context)
- Form inputs: 40px height minimum
- Pagination buttons: 36x36px minimum
- Icon buttons: 32x32px minimum

---

## Component Architecture (Vue)

```
All components live in resources/js/Components/ui/ and extend these design tokens.
Components are Composition API + <script setup> ONLY.
Every component accepts a "dir" prop (default: "rtl") for direction control.
```

### Shared Component Library

| Component | Pattern Source | Key Props |
|-----------|---------------|-----------|
| `<DataTable />` | Airtable | columns, data, filters, sortable, pagination |
| `<FormInput />` | Supabase | label, modelValue, error, placeholder, dir |
| `<FormSelect />` | Supabase | label, options, modelValue, error |
| `<FormModal />` | Linear | title, modelValue (v-model), size, footer |
| `<ConfirmDialog />` | Linear | message, confirmText, confirmVariant |
| `<PageHeader />` | Linear | title, description, actions (slot) |
| `<StatCard />` | Linear | label, value, icon, trend, color |
| `<Badge />` | Supabase | text, variant, dot |
| `<Pagination />` | Airtable | links, currentPage, dir="rtl" |
| `<SearchInput />` | Airtable | modelValue, placeholder, debounce |
| `<Alert />` | Supabase | type, message, dismissible |
| `<Breadcrumb />` | Notion | items (array of {label, route}) |
| `<Tabs />` | Linear | tabs, activeTab, onChange |
| `<Sidebar />` | Notion | collapsed, menuItems |
| `<Navbar />` | Notion | breadcrumb, notifications, user |

---

## Design Token References

Use these patterns throughout the codebase:

```vue
<!-- Tailwind classes matching design tokens -->
<div class="bg-[#f8fafc] border border-[#e2e8f0] rounded-lg p-4 text-right" dir="rtl">
  <h2 class="text-[#0f172a] text-lg font-semibold">عنوان البطاقة</h2>
  <p class="text-[#475569] text-sm">محتوى البطاقة</p>
</div>

<!-- Or use CSS custom properties (recommended) -->
:root {
  --color-primary: #2563eb;
  --color-hairline: #e2e8f0;
  --font-primary: 'Tajawal', 'Cairo', sans-serif;
  --radius-md: 6px;
}

[dir="rtl"] {
  --margin-start: margin-right;
  --margin-end: margin-left;
  --padding-start: padding-right;
  --padding-end: padding-left;
}
```

---

## Iteration Guide

1. **Start with the DataTable** — it's the most-used component. Get its styling right first.
2. **Build form components** — FormInput, FormSelect, FormDatepicker are the second-most-used.
3. **Add layout components** — Sidebar, Navbar, PageHeader frame every page.
4. **Add feedback components** — Alert, ConfirmDialog, LoadingSpinner, EmptyState.
5. **Build module pages** — Start with Companies (simplest CRUD), then Users (most complex).
6. **Verify RTL** — Every component must render correctly with `dir="rtl"`.
7. **Performance check** — Lazy-load, debounce search, paginate everything.

---

*Design system extracted from awesome-design-md-main sources: Airtable (data tables), Linear.app (cards/buttons), Notion (sidebar/nav), Supabase (forms/alerts). Adapted for Arabic RTL enterprise HRM.*
