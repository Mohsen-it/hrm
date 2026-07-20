# Phase 0 — Research & Decision Log

**Feature:** 007 — Aleppo Airport + Subordinations Seeding
**Date:** 2026-07-20
**Spec:** [spec.md](./spec.md)
**Constitution:** [constitution.md](../../.specify/memory/constitution.md)

> This document records every architectural / technical decision resolved during planning, the alternatives considered, and the rationale. It serves as the source of truth for "why" decisions were made so that future agents and reviewers can audit the plan without re-deriving context.

---

## 0. Summary

| Category | # Decisions | Open Questions |
|----------|-------------|----------------|
| Module structure | 3 | 0 |
| Database design | 4 | 0 |
| Layer architecture | 3 | 0 |
| Frontend / UX | 2 | 0 |
| Permission system | 1 | 0 |
| Seeding strategy | 2 | 0 |
| Testing | 1 | 0 |
| **Total** | **16** | **0** |

**No `[NEEDS CLARIFICATION]` items remain** — the original spec was concrete and the project constitution provided strong defaults. All ambiguities were resolved with documented assumptions in `spec.md § 20` and `spec.md § 21`.

---

## 1. Module Structure Decisions

### D-1.1: New module `Subordinations` is created as a full `nwidart/laravel-modules` module

- **Decision:** Create `Modules/Subordinations/` with the full module skeleton (Providers, Http, Models, Services, Repositories, lang, database/migrations, database/seeders, config, routes, app.json, composer.json).
- **Rationale:**
  - The constitution (Article II.2.4 + Article XIV) requires every domain entity to be a self-contained module. Subordination is a first-class domain entity with its own lifecycle, CRUD UI, permissions, and translations.
  - Following the existing module pattern (e.g., `Zones`, `Departments`, `Holidays`) keeps symmetry across the codebase and lets the `modules_statuses.json` toggle work the same way.
  - Reusing the modular scaffolding (`nwidart/laravel-modules`) gives us auto-discovery of routes, migrations, translations, and views for free.
- **Alternatives considered:**
  - **(A) Add subordination to `Modules/Users`** — rejected: violates SRP and Single Responsibility (Article XIV.3). Users is already the central, most complex module; adding another domain entity there would bloat it.
  - **(B) Add subordination to `Modules/Companies` or `Modules/Branches`** — rejected: subordination is logically independent of organizational structure (it is a *location/site* dimension, not a *company* or *branch* dimension). Coupling them would create a forced hierarchy that the spec explicitly avoids.
  - **(C) Use existing `Modules/Zones`** — rejected: Zones is a geographic access-control concept tied to fingerprint devices. Reusing it would conflate two different domains.

### D-1.2: Subordinations does NOT depend on Companies or Branches (no FK)

- **Decision:** `subordinations` table has no FK to `companies` or `branches`. It is an independent lookup table.
- **Rationale:**
  - The spec defines subordination as "the place/site an employee is administratively attached to" (e.g., an airport). It is conceptually a *coordinate*, not a *legal entity*.
  - The seeded data (Aleppo Airport, Latakia Airport) is independent of the seeded company (Aleppo International Airport) — an employee can be attached to a subordination even if no company branch is at that location.
  - This makes the table future-proof for many-to-many subordination (e.g., employees serving multiple airports) without restructuring.
- **Alternatives considered:**
  - **(A) FK `subordinations.company_id` → `companies.id`** — rejected: forces a company for every subordination and complicates multi-company sites.
  - **(B) Polymorphic relation to companies/branches** — rejected: over-engineered for the current need; one-to-many is sufficient for v1.

### D-1.3: `users.subordination_id` is a direct FK (not a pivot)

- **Decision:** Single nullable FK `subordination_id` on `users`, not a pivot table.
- **Rationale:**
  - Spec § 20 A-3 explicitly states v1 is one-to-many only.
  - Simpler schema, simpler query, simpler form (single `<FormSelect>`).
  - Future many-to-many can be added without breaking the existing API (rename column → pivot migration).
- **Alternatives considered:**
  - **(A) Pivot table `subordination_user`** — rejected for v1: premature complexity (Article X.10.1 — simplicity first).

---

## 2. Database Design Decisions

### D-2.1: `subordinations` table uses a `code` column as logical key + unique index

- **Decision:** Add `code VARCHAR(50) UNIQUE NOT NULL` (e.g., `ALEPPO-AIRPORT`).
- **Rationale:**
  - Seeders use `updateOrCreate(['code' => '...'], [...])` for idempotency (spec § 4.1 BR-7, § 12).
  - Provides a stable identifier for cross-environment seeding (dev/staging/prod have the same `code` but different auto-increment `id`).
  - Human-readable for ops/debugging.
- **Alternatives considered:**
  - **(A) UUID** — rejected: adds complexity, not needed for an internal lookup table.
  - **(B) Use `id` only** — rejected: `id` is not stable across environments and depends on insertion order, breaking seeders.

### D-2.2: Soft deletes enabled on `subordinations`

- **Decision:** Use Laravel `SoftDeletes` trait on the `Subordination` model.
- **Rationale:**
  - Constitution Article XIV.5 mandates soft deletes for "main tables" — `subordinations` qualifies.
  - Allows recovering mistakenly deleted records without DB restore.
  - Preserves historical references (if an employee was attached to a subordination that is later "deleted", the FK becomes `NULL` but the data trail is intact via the soft-deleted record).
- **Alternatives considered:**
  - **(A) Hard delete only** — rejected: violates the constitution.
  - **(B) Audit log instead of soft delete** — rejected: too heavy for v1 (out of scope per § 21).

### D-2.3: FK `users.subordination_id` uses `ON DELETE SET NULL`

- **Decision:** Migration: `$table->foreignId('subordination_id')->nullable()->constrained('subordinations')->nullOnDelete();`.
- **Rationale:**
  - Deleting a subordination must not cascade-delete employees (a subordination is metadata, not a structural parent).
  - `SET NULL` allows the system to continue operating even if a subordination record is removed.
  - The user can see "no subordination" in the UI for affected employees and re-assign if needed.
- **Alternatives considered:**
  - **(A) `CASCADE`** — rejected: dangerous; deleting a subordination would delete all its employees.
  - **(B) `RESTRICT`** — rejected: blocks legitimate cleanup of orphan subordination records.

### D-2.4: Indexes on `subordinations(code)`, `subordinations(status, deleted_at)`, `users(subordination_id)`

- **Decision:** Three indexes as listed in spec § 5.1 and § 16.
- **Rationale:**
  - `code UNIQUE` — enforces logical-key constraint + speeds up seeder `updateOrCreate`.
  - `(status, deleted_at)` — composite index for the most common query: "list active non-deleted subordinations" (used by FormSelect).
  - `users.subordination_id` — speeds up filtering the employee list by subordination.
  - All FKs are indexed per Constitution Article VI.6.1.4.
- **Alternatives considered:**
  - **(A) Composite index `(status, deleted_at, sort_order)`** — rejected: extra column on a low-volume table; the existing `ORDER BY sort_order` will be done on a tiny in-memory result.

---

## 3. Layer Architecture Decisions

### D-3.1: Subordinations follows the full Controller → Service → Repository → Model stack

- **Decision:** All four layers are created. Validation is split: rule definitions live in `FormRequest` (HTTP contract) but the *enforcement* logic (e.g., `Rule::unique(...)->ignore(...)`) and any cross-field rules live in `SubordinationService::validateSubordinationData`.
- **Rationale:**
  - Constitution Article II.2.3 (mandatory) + Article XIV.1.1 (best-practice).
  - Service layer holds business logic (e.g., "before delete, warn if employees are attached") and is reusable from non-HTTP contexts (CLI, queue, API).
  - Repository layer isolates Eloquent so the service is testable with mocks and so the DB engine can change (SQLite/MySQL/PostgreSQL) without touching business code.
- **Alternatives considered:**
  - **(A) Fat-controller (Controller + Model only)** — rejected: violates Constitution Article XII.12.2 which is an open work item to fix, not to extend.
  - **(B) Skip Repository (Controller → Service → Model)** — rejected: Constitution Article XIV.1.2 is explicit: "every model must have a Repository".

### D-3.2: Permission check happens at **two** layers (Route middleware + FormRequest::authorize + Controller::authorize)

- **Decision:** Triple defence: route `permission:` middleware, FormRequest `authorize()` method, and controller `$this->authorize()` call.
- **Rationale:**
  - Defence-in-depth: a misconfiguration in one layer doesn't expose the endpoint.
  - Route middleware prevents the request from even hitting the controller (early exit, faster).
  - FormRequest `authorize()` ensures direct calls to the request validation are also guarded.
  - Controller `$this->authorize()` makes the permission check explicit in the code and visible to anyone reading the action.
- **Alternatives considered:**
  - **(A) Only route middleware** — rejected: FormRequests used outside HTTP (e.g., in tests) wouldn't be guarded.
  - **(B) Only controller** — rejected: route discovery (e.g., `route:list`) wouldn't show permission requirements clearly.

### D-3.3: No business logic in `SubordinationsController::formOptions()`

- **Decision:** `formOptions()` only calls `$this->subordinationService->getActiveSubordinations()` and maps. No filtering, no transformation logic.
- **Rationale:**
  - Constitution Article XIV.1.3: controller is "thin — only HTTP ↔ Service bridge".
  - Active/ordered logic lives in `Subordination::scopeActive()` and `scopeOrdered()` (Article II.2.3: relationships/scopes belong in the Model).
- **Alternatives considered:** None — this is non-negotiable per the constitution.

---

## 4. Frontend / UX Decisions

### D-4.1: Subordinations uses the existing shared component library — no new UI primitives

- **Decision:** `Index/Create/Edit/Show.vue` for Subordinations use: `DataTable`, `FormInput`, `FormSelect`, `FormTextarea`, `FormSection`, `FormActions`, `ErrorSummary`, `PageHeader`, `Button`, `ConfirmDialog`.
- **Rationale:**
  - Constitution Article VII.7.2 (mandatory): never rebuild the same UI from scratch.
  - Consistency: Subordinations looks identical to Companies / Branches / Departments.
  - Free RTL + i18n + accessibility (handled by the shared components).
- **Alternatives considered:**
  - **(A) Custom modal-based CRUD** — rejected: explicitly forbidden by the constitution (Article VII — "no `<FormModal>` custom build").
  - **(B) Single-page inline editor** — rejected: not consistent with the rest of the app.

### D-4.2: Auto-suggest subordination based on selected company/branch (optional UX)

- **Decision:** Implement a `watch` in `Users/Create.vue` and `Users/Edit.vue`: when `form.company_id` changes to `AIRPORT-ALEPPO`, auto-set `form.subordination_id = id_of('ALEPPO-AIRPORT')`. **Best-effort, not enforced.**
- **Rationale:**
  - The seeded data is small (1 company, 2 branches, 2 subordinations) — the user can see the relationship.
  - Reduces clicks for the common case (most Aleppo Airport employees are administratively attached to Aleppo Airport).
  - Spec § 3 Scenario 4 and § 13.1 mark this as optional — if implementation time is tight, this is the first thing to defer.
- **Alternatives considered:**
  - **(A) Hard-enforce (require subordination when branch is from a known airport)** — rejected: too rigid; users may have valid reasons to differ.
  - **(B) No suggestion at all** — fallback: just include the FormSelect without auto-fill.

---

## 5. Permission System Decisions

### D-5.1: Four new granular permissions, auto-granted to `super-admin` only

- **Decision:** Permissions: `view-subordinations`, `create-subordinations`, `edit-subordinations`, `delete-subordinations`. They are created via a `PermissionSeeder` and granted to the `super-admin` role.
- **Rationale:**
  - Constitution Article V.5.1 mandates Spatie Permission.
  - Granularity matches the existing pattern (e.g., `view-companies`, `create-companies`).
  - Other roles (e.g., HR managers) can be granted `view-subordinations` independently if needed (not auto-granted).
  - Auto-grant to `super-admin` only — we don't want to surprise role-seeding by giving every role the new permissions.
- **Alternatives considered:**
  - **(A) Single permission `manage-subordinations`** — rejected: violates the action-module pattern (Constitution Article V).
  - **(B) Auto-grant to all existing roles** — rejected: security-by-default; explicit grant is safer.

---

## 6. Seeding Strategy Decisions

### D-6.1: All three seeders use `updateOrCreate` on logical keys (idempotency)

- **Decision:**
  - `CompaniesDatabaseSeeder`: `Company::updateOrCreate(['company_code' => 'AIRPORT-ALEPPO'], [...])`.
  - `BranchesDatabaseSeeder`: `Branch::updateOrCreate(['company_id' => $id, 'branch_code' => 'CIVIL-AVIATION'], [...])`.
  - `SubordinationsDatabaseSeeder`: `Subordination::updateOrCreate(['code' => 'ALEPPO-AIRPORT'], [...])`.
- **Rationale:**
  - Spec § 4.1 BR-7 mandates idempotency.
  - Allows re-running seeders safely in any environment (dev, staging, prod, CI).
  - Avoids unique-constraint exceptions on re-run.
- **Alternatives considered:**
  - **(A) `firstOrCreate` + manual `update`** — rejected: two queries vs. one with `updateOrCreate`.
  - **(B) `insert()` with try/catch** — rejected: hides bugs (e.g., silent overwrite of manually-edited data).

### D-6.2: Seeder execution order enforced in the root `DatabaseSeeder`

- **Decision:** Update `database/seeders/DatabaseSeeder.php` to call the three seeders in order: Companies → Branches → Subordinations → Users.
- **Rationale:**
  - Branches depend on Companies (FK); Subordinations have no FK but logically come after the org structure is seeded.
  - The Branches seeder has a defensive check (log warning + exit gracefully) if the Company is missing, so out-of-order execution does not crash — but the canonical order is explicit.
- **Alternatives considered:**
  - **(A) Self-detecting order via dependency graph** — rejected: over-engineered for 3 seeders.
  - **(B) Each seeder runs in isolation, document the order in a README** — rejected: too error-prone.

---

## 7. Testing Decisions

### D-7.1: No new automated tests added in v1; verification is via the manual `quickstart.md` script

- **Decision:** The feature ships without PHPUnit/Pest test cases for v1. The `quickstart.md` (Phase 1 artifact) provides a runnable end-to-end verification checklist. Test addition is left for a follow-up.
- **Rationale:**
  - The existing module test directories (`Modules/{Name}/tests/`) are empty (per the file-listing). Adding tests for Subordinations would set a precedent that the rest of the modules don't follow, creating inconsistency.
  - Manual `php artisan migrate:fresh --seed` + UI walkthrough is a strong verification for a data-seeding + dropdown-addition feature.
  - The `quickstart.md` artifact makes the manual test script version-controlled and repeatable.
  - Out of explicit scope in the spec (no tests mentioned in § 18 acceptance criteria).
- **Alternatives considered:**
  - **(A) Add `SubordinationTest.php` (Feature + Unit)** — deferred: better added as a batch when the whole Subordinations module is also being unit-tested for business logic (not just data).
  - **(B) Add only an integration test for the seeder** — possible: the seeder is the highest-risk piece (idempotency, FK order). Could be a one-liner. Decision: defer to a follow-up.

---

## 8. Integration with Existing Code

### I-1: `UserRepository::$defaultWith` updated to include `subordination`

- **Decision:** Add `'subordination'` to the `$defaultWith` array.
- **Rationale:** Without this, every `User::with(...)` call in the codebase would trigger an N+1 when iterating employees to display their subordination. Constitution Article VI.6.1.1 forbids N+1.

### I-2: `UserResource` exposes `subordination_id` (scalar) + `subordination` (object, whenLoaded)

- **Decision:** Both fields are emitted. The scalar is always present (for forms); the object only when explicitly eager-loaded.
- **Rationale:** Forms need the scalar to initialize `<FormSelect v-model="form.subordination_id">`. Show pages benefit from the object to avoid a second request.

### I-3: `UsersController::formOptions()` injects `SubordinationService`

- **Decision:** Add `SubordinationService` to the constructor. The existing 9 injections stay.
- **Rationale:** Constructor injection only (Constitution Article XIV.3 — no `app()` / `resolve()`). The list is long but consistent with how `BranchService`, `DepartmentService`, etc. are already injected.

### I-4: `Sidebar` gets a new entry under "الهيكل التنظيمي" (Organizational Structure)

- **Decision:** Add `<NavLink href="subordinations.index" :can="'view-subordinations'">التبعية</NavLink>` next to the existing Companies and Branches links.
- **Rationale:** Matches the existing navigation hierarchy. Permission-gated so users without the permission don't see the link.

---

## 9. Performance Budget

| Operation | Target | Constitution Reference |
|-----------|--------|------------------------|
| `users.index` Inertia request (20 rows) | < 300ms | VI.6.2 |
| DB queries on `users.index` (with subordination eager-loaded) | ≤ 5 | VI.6.1.1 (no N+1) |
| `subordinations.index` Inertia request (small dataset) | < 200ms | VI.6.2 |
| JS bundle delta (new Subordinations page) | < 30KB (lazy) | VI.6.2.3 |
| Migration on existing data (add `subordination_id` to `users`) | < 5s for 10K rows | practical |
| Seeder run (3 seeders, idempotent) | < 2s | practical |

These targets are met by:
- Single composite index on `(status, deleted_at)` for Subordinations.
- `subordination` in `UserRepository::$defaultWith`.
- Lazy-loaded Inertia pages for the Subordinations CRUD.

---

## 10. Security & Privacy

- No PII in the new table.
- All endpoints are `auth` + `permission:` gated.
- No file uploads involved.
- `code` regex `^[A-Z0-9_-]+$` prevents injection of control characters.
- No external API integrations.

---

## 11. Open Questions (none)

All questions raised during planning were resolved. None remain.

| # | Question | Resolution |
|---|----------|------------|
| — | — | — |

---

## 12. References

- Spec: [spec.md](./spec.md)
- Constitution: [constitution.md](../../.specify/memory/constitution.md)
- AGENTS.md (project conventions): [AGENTS.md](../../AGENTS.md)
- Plan template: [plan-template.md](../../.specify/templates/plan-template.md)
- Previous specs for pattern reference:
  - [001-shift-schedule-management](../001-shift-schedule-management/spec.md)
  - [005-employee-group-schedule-attendance](../005-employee-group-schedule-attendance/spec.md)
  - [006-bidirectional-device-sync](../006-bidirectional-device-sync/spec.md)
