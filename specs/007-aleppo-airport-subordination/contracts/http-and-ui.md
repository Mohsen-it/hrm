# Contracts — 007 Aleppo Airport Subordination

**Feature:** 007-aleppo-airport-subordination
**Date:** 2026-07-20
**Spec:** [spec.md](./spec.md)

This document defines the **interface contracts** between the system layers and external actors:

1. **HTTP contracts** — REST-style routes + payloads exchanged with the Inertia/Vue frontend.
2. **JSON resource contracts** — the shape of data returned to the frontend via Laravel API Resources.
3. **Seeding contract** — the idempotency contract the seeders must obey.
4. **Permission contract** — the four new permission keys the system exposes.
5. **UI component contract** — the props/events each Vue page expects from the backend.

These are the "frozen" interfaces that downstream code (frontend, integrations, tests) can rely on. Any change requires a deprecation cycle.

---

## 1. HTTP Route Map

All routes are prefixed by the module (the module's own `routes/web.php`); no global `web.php` modification is needed.

### 1.1 Subordinations Resource Routes

| Method | URI | Name | Middleware | Controller Action |
|--------|-----|------|------------|-------------------|
| `GET` | `/subordinations` | `subordinations.index` | `auth`, `permission:view-subordinations` | `SubordinationsController@index` |
| `GET` | `/subordinations/create` | `subordinations.create` | `auth`, `permission:create-subordinations` | `SubordinationsController@create` |
| `POST` | `/subordinations` | `subordinations.store` | `auth`, `permission:create-subordinations` | `SubordinationsController@store` |
| `GET` | `/subordinations/{subordination}` | `subordinations.show` | `auth`, `permission:view-subordinations` | `SubordinationsController@show` |
| `GET` | `/subordinations/{subordination}/edit` | `subordinations.edit` | `auth`, `permission:edit-subordinations` | `SubordinationsController@edit` |
| `PUT` | `/subordinations/{subordination}` | `subordinations.update` | `auth`, `permission:edit-subordinations` | `SubordinationsController@update` |
| `DELETE` | `/subordinations/{subordination}` | `subordinations.destroy` | `auth`, `permission:delete-subordinations` | `SubordinationsController@destroy` |

**Route model binding:** `{subordination}` resolves to `Modules\Subordinations\Models\Subordination` by `id`. 404 on missing or soft-deleted.

### 1.2 Users Resource Routes (no change, contract documented for completeness)

The existing `Modules/Users/routes/web.php` provides the standard resource routes for `users`. The new `subordination_id` field is part of the standard `users.store` and `users.update` payloads (see § 2.2 below).

### 1.3 Inertia Page Routes (frontend)

| Method | URI | Page Component | Returns |
|--------|-----|----------------|---------|
| `GET` | `/subordinations` | `Subordinations/Index.vue` | Inertia props: `{ subordinations: SubordinationResource[], filters: {search, status}, pagination }` |
| `GET` | `/subordinations/create` | `Subordinations/Create.vue` | Inertia props: `{ statusOptions: [{value,label}] }` |
| `GET` | `/subordinations/{id}/edit` | `Subordinations/Edit.vue` | Inertia props: `{ subordination: SubordinationResource, statusOptions }` |
| `GET` | `/subordinations/{id}` | `Subordinations/Show.vue` | Inertia props: `{ subordination: SubordinationResource }` |
| `GET` | `/users/create` | `Users/Create.vue` | Inertia props: `{ ..., subordinations: SubordinationOption[] }` (new) |
| `GET` | `/users/{id}/edit` | `Users/Edit.vue` | Inertia props: `{ ..., subordinations: SubordinationOption[] }` (new) |
| `GET` | `/users` | `Users/Index.vue` | Inertia props: `{ ..., subordinations: SubordinationOption[] }` (new for filter dropdown) |

---

## 2. JSON Resource Contracts

### 2.1 `SubordinationResource` (collection + single)

Returned by the `subordinations` index/show/edit endpoints and the `formOptions()` helper.

```jsonc
{
  "id": 1,
  "code": "ALEPPO-AIRPORT",
  "name_ar": "مطار حلب",
  "name_en": "Aleppo Airport",
  "display_name": "مطار حلب",         // accessor: name_ar ?? name_en ?? code
  "description": null,
  "status": 1,
  "sort_order": 1,
  "created_at": "2026-07-20 10:59:00",
  "updated_at": "2026-07-20 10:59:00"
}
```

**Field contracts:**

| Field | Type | Nullable | Notes |
|-------|------|----------|-------|
| `id` | int | no | Auto-increment surrogate |
| `code` | string | no | Uppercase, `[A-Z0-9_-]+`, max 50 |
| `name_ar` | string | no | Arabic display name, max 100 |
| `name_en` | string | yes | English display name, max 100 |
| `display_name` | string | no | Derived: `name_ar` else `name_en` else `code` |
| `description` | string | yes | Free text |
| `status` | int (0\|1) | no | 1 = active |
| `sort_order` | int | yes | Display order |
| `created_at` | string (Y-m-d H:i:s) | yes | UTC, server time |
| `updated_at` | string (Y-m-d H:i:s) | yes | UTC, server time |

### 2.2 `UserResource` (modified — only new fields documented)

Two new fields are added to the existing `UserResource`:

```jsonc
{
  // ... existing UserResource fields ...
  "subordination_id": 1,                     // scalar FK
  "subordination": {                          // object, only when eager-loaded
    "id": 1,
    "name_ar": "مطار حلب",
    "name_en": "Aleppo Airport"
  }
}
```

| Field | Type | Nullable | When present |
|-------|------|----------|--------------|
| `subordination_id` | int | yes | Always (matches DB column) |
| `subordination` | object | yes | Only when the relation was eager-loaded (e.g., `User::with('subordination')`) |

If `subordination_id` is set but the relation was not loaded, `subordination` is omitted (no extra request is issued — this is the "whenLoaded" contract).

### 2.3 `SubordinationOption` (lightweight — used in `formOptions()`)

For the user-form dropdown, the controller returns a slimmer shape to minimise payload:

```jsonc
[
  { "id": 1, "display_name": "مطار حلب",     "code": "ALEPPO-AIRPORT" },
  { "id": 2, "display_name": "مطار اللاذقية", "code": "LATTAKIA-AIRPORT" }
]
```

Shape contract: `{ id: int, display_name: string, code: string }[]`. Always sorted by `(sort_order ASC, name_ar ASC)` and only includes `status = 1` rows.

---

## 3. Form Submission Payloads

### 3.1 `POST /subordinations` (Store)

**Request (form-encoded or JSON):**

```jsonc
{
  "code": "ALEPPO-AIRPORT",
  "name_ar": "مطار حلب",
  "name_en": "Aleppo Airport",          // optional
  "description": "...",                  // optional
  "status": 1,                           // optional, default 1
  "sort_order": 1                        // optional, default 0
}
```

**Validation errors (`422`):**

```jsonc
{
  "errors": {
    "code": ["الرمز مطلوب"],
    "code": ["رمز التبعية مستخدم من قبل"],
    "name_ar": ["الاسم بالعربية مطلوب"]
  }
}
```

**Success:** `302 Redirect` to `subordinations.index` with flash `success: "تم إنشاء التبعية بنجاح"`.

### 3.2 `PUT /subordinations/{id}` (Update)

Same payload shape as Store, all fields are optional in the update request. The `code` uniqueness rule ignores the current record.

### 3.3 `POST /users` (Store) — with new subordination field

```jsonc
{
  // ... existing user fields ...
  "company_id": 1,
  "branch_id": 1,
  "department_id": null,
  "subordination_id": 1,                  // NEW — nullable
  "position_id": null,
  "grade_id": null,
  "shift_id": null,
  "manager_id": null,
  "roles": [],
  "permissions": []
}
```

**Validation:** `subordination_id` must be `nullable|integer|exists:subordinations,id` (error key `subordination_id_exists`).

### 3.4 `PUT /users/{id}` (Update) — with new subordination field

Same as Store. `subordination_id` can be `null` to detach the user from a subordination (use case: employee moved off-site).

---

## 4. Seeding Contract (idempotency)

### 4.1 `SubordinationsDatabaseSeeder`

| Property | Contract |
|----------|----------|
| **Class FQN** | `Modules\Subordinations\Database\Seeders\SubordinationsDatabaseSeeder` |
| **Entry point** | `php artisan db:seed --class="Modules\\Subordinations\\Database\\Seeders\\SubordinationsDatabaseSeeder"` |
| **Side effect on first run** | Inserts 2 rows into `subordinations`: `ALEPPO-AIRPORT`, `LATTAKIA-AIRPORT` |
| **Side effect on re-run** | No-op (no insert, no update of existing fields, no error) |
| **Pre-conditions** | The `subordinations` table must exist (run `migrate` first) |
| **Post-conditions** | The 2 rows above exist with `status=1`, `sort_order` in {1, 2} |
| **Failure modes** | None expected; if `migrate` was not run, the seeder will throw a SQL error (acceptable — caller must run migrations first) |

### 4.2 `CompaniesDatabaseSeeder` (modified)

| Property | Contract |
|----------|----------|
| **Entry point** | `php artisan db:seed --class="Modules\\Companies\\Database\\Seeders\\CompaniesDatabaseSeeder"` |
| **Side effect on first run** | Inserts 1 row: `AIRPORT-ALEPPO` → "مطار حلب الدولي" |
| **Side effect on re-run** | No-op |
| **Pre-conditions** | The `companies` table must exist |
| **Post-conditions** | The row above exists with `is_default=true, status=1` |

### 4.3 `BranchesDatabaseSeeder` (modified)

| Property | Contract |
|----------|----------|
| **Entry point** | `php artisan db:seed --class="Modules\\Branches\\Database\\Seeders\\BranchesDatabaseSeeder"` |
| **Side effect on first run** | Inserts 2 rows: `CIVIL-AVIATION`, `SYRIAN-AIR` — both linked to `companies.id` of the `AIRPORT-ALEPPO` row |
| **Side effect on re-run** | No-op |
| **Pre-conditions** | The `branches` table must exist AND the `AIRPORT-ALEPPO` company must exist (else the seeder logs a warning and exits gracefully) |
| **Post-conditions** | The 2 rows above exist with `is_main` = {true, false} respectively |

### 4.4 Seeding Order Contract

The `database/seeders/DatabaseSeeder.php` (root) MUST call seeders in this order to satisfy FK dependencies:

```
1. Modules\Companies\Database\Seeders\CompaniesDatabaseSeeder
2. Modules\Branches\Database\Seeders\BranchesDatabaseSeeder
3. Modules\Subordinations\Database\Seeders\SubordinationsDatabaseSeeder
4. Modules\Users\Database\Seeders\UsersDatabaseSeeder (existing)
```

Out-of-order execution is **not** guaranteed to succeed (Branches seeder has a defensive check; Users seeder is the caller's responsibility).

---

## 5. Permission Contract

### 5.1 New Permission Keys

Four new permission strings are added to the `permissions` table (via `Spatie\Permission`):

| Key | Type | Granted by default to |
|-----|------|------------------------|
| `view-subordinations` | string | `super-admin` role only |
| `create-subordinations` | string | `super-admin` role only |
| `edit-subordinations` | string | `super-admin` role only |
| `delete-subordinations` | string | `super-admin` role only |

### 5.2 Permission Seeding

A dedicated seeder (or a check in the existing permission seeder, if present) MUST:

1. Create the 4 permissions if they don't exist.
2. Attach them to the `super-admin` role if it exists.

This seeder runs **after** the Spatie roles/permissions migration and is idempotent.

### 5.3 Enforcement Points

| Layer | Mechanism |
|-------|-----------|
| Routes | `middleware('permission:view-subordinations')` etc. |
| FormRequest | `authorize(): bool { return $this->user()->hasPermissionTo('create-subordinations'); }` |
| Controller | `$this->authorize('view-subordinations')` |
| Vue (sidebar) | `<NavLink :can="'view-subordinations'">` (the link is hidden if the user lacks the permission) |
| Vue (forms) | Buttons are conditionally rendered; if the user navigates directly, the backend rejects the request |

### 5.4 Public API (for other modules)

```php
$user->hasPermissionTo('view-subordinations');     // bool
$user->can('view-subordinations');                  // bool (Spatie's Gate)
auth()->user()->hasPermissionTo('create-subordinations');
```

---

## 6. Vue Component Contracts (UI)

### 6.1 `Subordinations/Index.vue`

**Props (from Controller):**

```ts
defineProps<{
  subordinations: SubordinationResource[];   // paginated collection
  filters: { search?: string; status?: 0|1 };
}>();
```

**Emits:** none (read-only page; actions go through router).

**Components used (mandatory per Constitution § VII.7.2):**
- `AppLayout`, `PageHeader`, `DataTable`, `SearchInput`, `FormSelect` (status filter), `Button`, `ConfirmDialog`, `EmptyState`, `Alert`, `Pagination`.

### 6.2 `Subordinations/Create.vue` & `Subordinations/Edit.vue`

**Props:**

```ts
// Create
defineProps<{ statusOptions: { value: 0|1; label: string }[] }>();

// Edit
defineProps<{
  subordination: SubordinationResource;
  statusOptions: { value: 0|1; label: string }[];
}>();
```

**Form state (Create):**

```ts
const form = reactive({
  code: '',
  name_ar: '',
  name_en: '',
  description: '',
  status: 1,
  sort_order: 0,
});
```

**Form state (Edit):** pre-filled from `props.subordination`. Uses `_method: 'PUT'` for spoofing.

**Submission:**

```ts
router.post(route('subordinations.store'), form, { forceFormData: true, ... });
// or for Edit:
router.post(route('subordinations.update', props.subordination.id), form, { ... });
```

**Components used:** `FormInput`, `FormTextarea`, `FormSelect`, `FormSection`, `FormActions`, `ErrorSummary`, `Button`, `PageHeader`.

### 6.3 `Users/Create.vue` & `Users/Edit.vue` (modified)

**New prop:**

```ts
subordinations: { id: number; display_name: string; code: string }[];
```

**New form field:**

```ts
subordination_id: string | number;   // '' for unselected, number for selected
```

**New UI element (inside the "organizational_info" FormSection):**

```vue
<FormSelect
    v-model="form.subordination_id"
    :label="t('users.subordination')"
    name="subordination_id"
    :options="subordinations.map((s) => ({ value: s.id, label: s.display_name }))"
    :placeholder="t('users.select_subordination')"
    :error="errorFor('subordination_id')"
/>
```

**Optional UX (deferrable):** a `watch` on `form.company_id` that auto-fills `form.subordination_id` to `id_of('ALEPPO-AIRPORT')` when the selected company is `AIRPORT-ALEPPO`. Implementation:

```ts
watch(() => form.company_id, (newCompanyId) => {
  const company = props.companies.find((c) => c.id === newCompanyId);
  if (company?.company_code === 'AIRPORT-ALEPPO') {
    const aleppo = props.subordinations.find((s) => s.code === 'ALEPPO-AIRPORT');
    if (aleppo) form.subordination_id = aleppo.id;
  }
});
```

### 6.4 `Users/Index.vue` (modified)

**New prop:** `subordinations: SubordinationOption[]`

**New filter prop:** `filters.subordination_id?: number`

**New column** added to `DataTable` `columns` array:

```ts
{
  key: 'subordination',
  label: t('users.subordination'),
  format: (row) => row.subordination?.name_ar ?? '—',
  sortable: false,
}
```

**New filter UI element** (above the table):

```vue
<FormSelect
    v-model="filters.subordination_id"
    :options="subordinations.map((s) => ({ value: s.id, label: s.display_name }))"
    :placeholder="t('users.select_subordination')"
    @change="applyFilters"
/>
```

### 6.5 `Users/Show.vue` (modified)

**New display block** in the "organizational_info" section:

```vue
<div class="info-row">
    <span class="label">{{ t('users.subordination') }}</span>
    <span class="value">{{ user.subordination?.name_ar ?? '—' }}</span>
</div>
```

### 6.6 `Sidebar.vue` (modified)

**New entry** under the "الهيكل التنظيمي" group:

```vue
<NavLink :href="route('subordinations.index')" :can="'view-subordinations'" :active="route().current('subordinations.*')">
    <i class="fas fa-map-marker-alt"></i>
    <span>{{ t('subordinations.title') }}</span>
</NavLink>
```

The `:can` prop hides the link if the user lacks `view-subordinations`.

---

## 7. Error & Failure Mode Contracts

| Scenario | HTTP Status | Response Body | UX |
|----------|-------------|---------------|----|
| Validation failure (Store/Update) | 422 | `{ errors: { field: [messages] } }` | Inertia populates `errors.value`; FormInput shows the message |
| Permission denied | 403 | HTML error page (default Laravel) | Redirect to login or show 403 page |
| Route model not found (e.g., `subordinations/999`) | 404 | HTML error page | `abort(404)` in controller |
| FK violation on `subordination_id` (race condition) | 422 | `{ errors: { subordination_id: [exists] } }` | FormSelect shows the message |
| Subordination row deleted while user is editing | 422 | `{ errors: { subordination_id: [exists] } }` | User must pick a different value |
| Seeder run before migration | SQLSTATE exception | n/a (CLI) | Caller error — document in `quickstart.md` |
| Seeder run twice | n/a (idempotent) | n/a | Same state as single run |

---

## 8. Backwards Compatibility

| Existing API/UI | Change | Compatibility |
|-----------------|--------|---------------|
| `GET /users/create` | Adds `subordinations` prop | **Backward-compatible** — Vue prop has a `default: () => []` |
| `GET /users/{id}/edit` | Adds `subordinations` prop + `user.subordination` field | **Backward-compatible** — both are new fields; existing consumers ignore them |
| `UserResource` JSON | Adds `subordination_id` (top-level scalar) and `subordination` (object, when loaded) | **Backward-compatible** — additive only |
| `users.store` / `users.update` payloads | `subordination_id` is now an accepted (optional) field | **Backward-compatible** — omitting it behaves as before (`NULL`) |
| `companies` table | No schema change | **Fully backward-compatible** |
| `branches` table | No schema change | **Fully backward-compatible** |
| `users` table | Adds `subordination_id` nullable column | **Backward-compatible** — `NULL` is the default; existing rows are not affected |
| Existing `users` rows | After migration, `subordination_id = NULL` for all rows | **Expected** — no historical data is re-mapped |
| Sidebar | New "التبعية" entry | **Additive** — existing entries unchanged |
| Permission system | 4 new permission keys | **Additive** — no existing permission changes |

**No breaking changes** are introduced by this feature.

---

## 9. Versioning & Deprecation

- This feature is tagged `1.0.0` (initial release of the Subordinations module).
- The contract is **frozen** for the 1.x line.
- Any breaking change to the contract requires:
  1. Bumping the major version.
  2. Documenting the deprecation in `CHANGELOG.md` (if present) or `AGENTS.md`.
  3. Providing a migration path (e.g., add new field, deprecate old, remove in v2).

---

*Last updated: 2026-07-20*
