# Phase 0 — Research & Decisions

**Feature:** 003-mistral-design-system
**Date:** 2026-07-15
**Reference:** `mistral.ai\DESIGN.md`

---

## 1. Current State Audit

### 1.1 Existing Tokens (from `resources\css\app.css`)

The project currently uses **CSS variables** defined inside the Tailwind 4 `@theme` directive. Despite the comment claiming "Superhuman-inspired", the actual values are a hybrid of Airtable (tables) + Linear (cards/buttons) + Notion (sidebar) + Supabase (forms).

| Current Variable | Current Value | Mistral Token | Notes |
|------------------|---------------|---------------|-------|
| `--color-primary` | `#1b1938` (indigo navy) | `{colors.primary}` = `#fa520f` (orange) | **MUST change** |
| `--color-primary-hover` | `#0e0c1f` | `{colors.primary-deep}` = `#cc3a05` | **MUST change** |
| `--color-on-primary` | `#ffffff` | `{colors.on-primary}` = `#ffffff` | ✅ same |
| `--color-canvas` | `#ffffff` | `{colors.canvas}` = `#ffffff` | ✅ same |
| `--color-canvas-soft` | `#fafaf8` | `{colors.surface}` = `#fafafa` | close, semantic rename |
| `--color-hairline` | `#e2e8f0` | `{colors.hairline}` = `#e5e5e5` | **MUST change** |
| `--color-hairline-strong` | `#cbd5e1` | `{colors.hairline-strong}` = `#c7c7c7` | **MUST change** |
| `--color-ink` | `#292827` | `{colors.ink}` = `#1f1f1f` | **MUST change** |
| `--color-ink-mute` | `#73706d` | `{colors.steel}` = `#6a6a6a` | **MUST change** + rename |
| `--color-ink-faint` | `#9a9794` | `{colors.stone}` = `#8a8a8a` | **MUST change** + rename |
| `--color-teal-deep` | `#0e3030` | not present in Mistral palette | **REMOVE** from brand |
| `--color-violet-soft` | `#c9b4fa` | not present | **REMOVE** |
| `--color-surface-1/2/3` | `#f8fafc/#f1f5f9/#e2e8f0` | `{colors.surface}` / `{colors.surface-cream}` | **MUST refactor** |

### 1.2 Existing Components

| Component | File | Status | Action |
|-----------|------|--------|--------|
| `Alert.vue` | `resources\js\Components\ui\Alert.vue` | exists, uses CSS classes | Update to use Mistral tokens |
| `Badge.vue` | `resources\js\Components\ui\Badge.vue` | exists, uses CSS vars | Update variants to Mistral palette |
| `ConfirmDialog.vue` | `resources\js\Components\ui\ConfirmDialog.vue` | exists | Restyle buttons to Mistral |
| `DataTable.vue` | `resources\js\Components\ui\DataTable.vue` | exists | Update to Mistral tokens |
| `EmptyState.vue` | `resources\js\Components\ui\EmptyState.vue` | exists | Restyle |
| `FormInput.vue` | `resources\js\Components\ui\FormInput.vue` | exists, uses CSS vars | Update to Mistral |
| `FormModal.vue` | `resources\js\Components\ui\FormModal.vue` | exists | Restyle |
| `FormSelect.vue` | `resources\js\Components\ui\FormSelect.vue` | exists | Update to Mistral |
| `FormTextarea.vue` | `resources\js\Components\ui\FormTextarea.vue` | exists | Update to Mistral |
| `LoadingSpinner.vue` | `resources\js\Components\ui\LoadingSpinner.vue` | exists | Restyle |
| `PageHeader.vue` | `resources\js\Components\ui\PageHeader.vue` | exists | Update typography tokens |
| `SearchInput.vue` | `resources\js\Components\ui\SearchInput.vue` | exists | Update to Mistral |
| `Sidebar.vue` | `resources\js\Components\layout\Sidebar.vue` | exists | **Refactor per spec 002** |
| `SidebarGroup.vue` | `resources\js\Components\layout\SidebarGroup.vue` | exists | **Refactor per spec 002** |
| `SidebarItem.vue` | `resources\js\Components\layout\SidebarItem.vue` | exists | **Refactor per spec 002** |
| `AppLayout.vue` | `resources\js\Layouts\AppLayout.vue` | exists | Add Sunset Stripe Band |
| `Navbar.vue` | `resources\js\Components\layout\Navbar.vue` | exists | Restyle |
| **`Button.vue`** | **MISSING** | — | **CREATE** |
| **`Card.vue`** | **MISSING** | — | **CREATE** |
| **`FormCheckbox.vue`** | **MISSING** | — | **CREATE** |
| **`FormRadio.vue`** | **MISSING** | — | **CREATE** |
| **`FormSwitch.vue`** | **MISSING** | — | **CREATE** |
| **`Tabs.vue`** | **MISSING** | — | **CREATE** |
| **`StatCard.vue`** | **MISSING** | — | **CREATE** |
| **`SunsetStripeBand.vue`** | **MISSING** | — | **CREATE** |
| **`Breadcrumb.vue`** | **MISSING** | — | **CREATE** |
| **`Tabs.vue`** | **MISSING** | — | **CREATE** |

### 1.3 Existing CSS Classes (used in pages)

Pages (e.g. `Pages\Companies\Index.vue`) currently use **CSS classes directly**, not Vue components:
- `btn`, `btn-primary`, `btn-secondary`, `btn-ghost`, `btn-danger`, `btn-icon`
- `card`, `form-input`, `form-checkbox`
- `alert`, `alert-success`, `alert-danger`, `alert-warning`, `alert-info`
- `sidebar`, `sidebar-item`, `sidebar-item-active`, `sidebar-item-badge`
- `navbar`, `modal-overlay`, `table-row`

The constitution (Article VII) mandates: "all forms must use `<FormInput />`, not raw `<input>`". The current pages **violate this rule** because they use `<Link class="btn btn-primary">` instead of `<Button>` Vue component. This gap is closed by creating `Button.vue`.

---

## 2. Resolved Unknowns

### UN-001: Where do new components live?

**Decision:** New components in `resources\js\Components\ui\` (alongside existing). Layout-specific (Sidebar, Navbar, AppLayout, SunsetStripeBand) in `resources\js\Components\layout\`.

**Rationale:** Matches existing folder structure; matches Constitution Article VII.

**Alternatives considered:**
- `resources\js\Design\` (new folder) — rejected, would split design system across two folders.
- `resources\js\Components\mistral\` — rejected, design system should not be brand-fragmented.

### UN-002: Keep existing CSS variable names OR rename to Mistral?

**Decision:** **Rename to Mistral names** as the canonical names, but **add backward-compat aliases** for one release cycle.

| Old name (current) | New name (canonical) | Alias kept? |
|--------------------|----------------------|-------------|
| `--color-primary` | `--color-mistral-primary` | `--color-primary` aliased for 1 cycle |
| `--color-ink-mute` | `--color-mistral-steel` | `--color-ink-mute` aliased |
| `--color-ink-faint` | `--color-mistral-stone` | `--color-ink-faint` aliased |
| `--color-hairline` | `--color-mistral-hairline` | `--color-hairline` aliased |

**Rationale:** Renaming makes the design system self-documenting (`--color-mistral-primary` clearly means "the orange primary from Mistral"). Aliases prevent regression in pages that still reference old names. Aliases are scheduled for removal in spec 004 (cleanup).

**Alternatives considered:**
- Keep `--color-primary` but change value to orange — **rejected** because spec 002 sidebar relies on its semantic meaning (sidebar accent). Renaming avoids semantic confusion.
- Hard rename with no aliases — rejected, would break 002 sidebar and all current pages in a single commit.

### UN-003: How to handle the 002 Sidebar spec which uses Superhuman tokens?

**Decision:** Update `002-sidebar-ui-redesign\spec.md` to reference the **new Mistral token names** (after the design system migration lands). The 002 sidebar plan keeps the same component structure but swaps `{colors.surface-teal-deep}` for `{colors.primary}` orange.

**Rationale:** The 002 sidebar was planned against the old (Superhuman) tokens. Once the design system is unified under Mistral, the sidebar naturally uses the new orange primary. Updating 002 prevents token-drift between specs.

**Implementation:** A **follow-up commit** updates 002's spec to point at Mistral tokens. Done in same release, not in this spec.

### UN-004: PP Editorial Old font availability?

**Decision:** Use **`Georgia`** (system fallback) for the Editorial display style in the **HRM app** for now. Defer the actual PP Editorial Old font license to a separate work item.

**Rationale:** PP Editorial Old is a paid typeface. The fallback to Georgia preserves the "near-serif editorial" character for hero displays in the HRM marketing surfaces (if any are added later). For internal HRM pages, `{typography.heading-1}` uses Inter at large size — the editorial character is not required.

**Alternatives considered:**
- Use `Tajawal` (already loaded) for display Arabic — chosen as the Arabic display family since it has serif-style variants.
- License PP Editorial Old — out of scope for this design system spec.

### UN-005: Sunset Stripe Band — apply to internal HRM pages or only marketing?

**Decision:** Apply to **every authenticated page** AND **auth pages** (login, register, password reset).

**Rationale:** The sunset stripe is the brand recognizer. It must appear consistently across all HRM pages, not just marketing. The spec is for the entire HRM product, so consistency wins.

**Implementation:** Add to `AppLayout.vue` as a 4px element fixed at the bottom of the viewport, on top of all content. Auth pages have a minimal layout that also includes the stripe.

### UN-006: Hover states — Mistral says "no hover" but the project uses hover everywhere

**Decision:** **Keep hover states** for backwards compatibility and improved discoverability. Mistral's "no hover" rule is for their marketing site; HRM is a productivity tool where hover helps users.

**Rationale:** Constitution Article XIV.2.2 requires `v-memo` and computed values for performance, not the removal of UX feedback. The button "pressed" state is kept; "hover" is added at a subtle level (5–8% darker background).

**Implementation:** Override the "no hover" rule from Mistral for interactive components. Document this in the design system as a deliberate HRM-specific decision.

### UN-007: How to handle the existing `.btn-primary` CSS class?

**Decision:** **Refactor** pages to use the new `<Button>` Vue component, then **delete** the `.btn-*` CSS classes in favor of component-scoped styles.

**Rationale:** Constitution Article VII.2 forbids building the same UI twice. CSS-only buttons are de-facto a parallel implementation of the button concept. Replacing them with `<Button variant="primary">` unifies the codebase.

**Migration path:**
1. Create `Button.vue` with the same visual outcome.
2. Replace `class="btn btn-primary"` usages with `<Button variant="primary">` in pages (via `grep` automation).
3. Delete `.btn-primary`/`.btn-secondary`/`.btn-ghost`/`.btn-danger`/`.btn-icon` from `app.css`.

**Exception:** Keep `.btn` for one release as a backward-compat shim, marked `@deprecated`.

### UN-008: Form Checkbox — native or custom-styled?

**Decision:** **Custom-styled** checkbox using a wrapper element with `accent-color: var(--color-mistral-primary)`.

**Rationale:** The constitution requires RTL-aware icons. The native checkbox on Safari/older browsers does not reflect `accent-color`. The wrapper-based approach uses Font Awesome 6 (already loaded) for the checkmark icon, ensuring consistent visual across browsers.

**Alternatives considered:**
- Native `<input type="checkbox" class="form-checkbox">` (current) — kept as a fallback in the shim, replaced by `FormCheckbox.vue`.

### UN-009: How to verify WCAG AA contrast on every combination?

**Decision:** Add a **CI step** (Laravel Pint + custom lint) that checks every `bg-*/text-*` combination against a contrast lookup table. Failed combinations block the merge.

**Rationale:** Catching contrast issues at PR time is cheaper than at QA time. The lookup table contains the 12 high-traffic combos from the design system (orange/white, orange/ink, cream/ink, etc.).

**Tooling:** Write a Node script `tools/contrast-lint.mjs` that runs on `resources\**\*.{vue,css}`. Outputs errors for any non-allowlisted combination.

### UN-010: Migration order — what gets updated first?

**Decision:** Three-phase migration, each phase is a mergeable PR.

| Phase | Scope | Risk | Files |
|-------|-------|------|-------|
| **P1: Tokens** | Update `app.css` with all Mistral tokens + aliases | Low (additive) | 1 file |
| **P2: Components** | Create new components (`Button`, `Card`, `FormCheckbox`, `FormRadio`, `FormSwitch`, `Tabs`, `StatCard`, `SunsetStripeBand`, `Breadcrumb`); update existing 13 components | Medium (touches all UI) | ~22 files |
| **P3: Pages** | Migrate `Pages/**` and `Components/layout/**` to use the new Vue components | High (touches 13 modules × multiple pages) | 50+ files |
| **P4: Cleanup** | Remove deprecated aliases and `.btn-*` classes | Low (one-shot) | 1 file |

---

## 3. Best-Practices Researched

### 3.1 Tailwind 4 `@theme` pattern

Reference: https://tailwindcss.com/docs/theme
- CSS variables defined in `@theme {}` become both utility classes (`bg-primary`) AND CSS variables (`var(--color-primary)`).
- Tokens are scoped to `:root` by default, so they're globally available.
- **Insight:** Adding new tokens does not require recompiling Tailwind — just edit `app.css`.

### 3.2 RTL + CSS variables

Reference: https://rtlstyling.com/posts/rtl-styling
- Logical properties (`margin-inline-start`, `padding-inline-end`) flip automatically with `dir="rtl"`.
- **Insight:** Use logical properties in all new components. The current codebase mixes physical (`mr-4`) and logical (`ms-2`) — standardize on logical.

### 3.3 Component reusability pattern (Constitution Article VII.2)

Reference: Constitution (already in project).
- "If you find yourself repeating the same pattern 3+ times → create a new shared component."
- **Insight:** The current pages have 6+ raw `<input class="form-input">` calls — this is already a violation. `FormInput` exists, so the gap is adoption, not creation. The migration must enforce component usage.

### 3.4 WCAG AA contrast for orange/white

- `#fa520f` on `#ffffff` → contrast ratio **4.51:1** (AA Large/AA UI only, fails AA Normal for text < 18pt)
- **Mitigation:** For orange text on white, restrict to bold 18px+ or use as background only (white text on orange = 4.51:1, fails AA Normal).
- **Better choice:** Use `primary-deep` (`#cc3a05`) for body text on white. Document this in tokens.

### 3.5 Vue 3 + Inertia form pattern

- Inertia v3 uses `<Link>` and `router` for navigation, not `<a>`.
- Form submission uses `router.post/put/delete` with `useForm` composable.
- **Insight:** `FormInput` already supports `v-model`; `FormCheckbox` must do the same for consistency.

---

## 4. Decisions Summary

| ID | Decision | Affects |
|----|----------|---------|
| D-01 | New components in `resources\js\Components\ui\` and `layout\` | P2 |
| D-02 | Rename tokens to Mistral names; keep old as aliases for 1 release | P1, P4 |
| D-03 | Update spec 002 to use Mistral tokens (follow-up commit) | Post-P3 |
| D-04 | Georgia fallback for PP Editorial Old; Tajawal for Arabic display | P1 |
| D-05 | Sunset Stripe Band on every page (auth + authenticated) | P2 |
| D-06 | Keep hover states (HRM-specific deviation from Mistral) | P2 |
| D-07 | Replace `.btn-*` classes with `<Button>` Vue component; deprecate shim | P2, P3, P4 |
| D-08 | Custom-styled `FormCheckbox` using wrapper + FA icon | P2 |
| D-09 | CI contrast lint for high-traffic combos | Post-P3 |
| D-10 | 4-phase migration (Tokens → Components → Pages → Cleanup) | All phases |

---

*Last updated: 2026-07-15*
