# Data Model — 007 Aleppo Airport Subordination

**Feature:** 007-aleppo-airport-subordination
**Date:** 2026-07-20
**Spec:** [spec.md](./spec.md)

This document is the canonical reference for the schema changes, entity relationships, validation rules, and lifecycle states introduced by this feature. It is the contract between the database layer and the application layer.

---

## 1. Entity Relationship Diagram (textual)

```
┌──────────────────────────┐         ┌──────────────────────────┐
│  companies               │         │  branches                │
│  ─────────────           │  1   N  │  ─────────────           │
│  id  PK                  │◄────────┤  id  PK                  │
│  company_code  UQ        │         │  company_id  FK          │
│  company_name            │         │  branch_code             │
│  ...                     │         │  branch_name             │
│  (existing, no change)   │         │  ...                     │
└──────────────────────────┘         │  (existing, no change)   │
                                     └──────────────────────────┘

┌──────────────────────────┐         ┌──────────────────────────┐
│  subordinations  (NEW)   │         │  users  (modified)       │
│  ─────────────           │  1   N  │  ─────────────           │
│  id  PK                  │◄────────┤  id  PK                  │
│  code  UQ                │   NULL  │  subordination_id  FK *  │
│  name_ar                 │   ON    │  ...                     │
│  name_en                 │  DELETE │  (subordination_id is    │
│  description             │   SET   │   nullable, no cascade)  │
│  status                  │  NULL   │                          │
│  sort_order              │         │                          │
│  created_at, updated_at  │         │                          │
│  deleted_at              │         │                          │
└──────────────────────────┘         └──────────────────────────┘
```

*`subordination_id` is **nullable**, so the relation is effectively `0..1 → N` (zero-or-one subordination per user).*

---

## 2. New Table: `subordinations`

| Column | Type | Null | Default | Key | Description |
|--------|------|------|---------|-----|-------------|
| `id` | BIGINT UNSIGNED | NO | AUTO_INCREMENT | **PK** | Surrogate identifier |
| `code` | VARCHAR(50) | NO | — | **UQ** | Logical key, regex `^[A-Z0-9_-]+$` (e.g., `ALEPPO-AIRPORT`) |
| `name_ar` | VARCHAR(100) | NO | — | — | Display name in Arabic |
| `name_en` | VARCHAR(100) | YES | NULL | — | Display name in English (optional) |
| `description` | TEXT | YES | NULL | — | Optional free-text description |
| `status` | SMALLINT | NO | 1 | IDX(part of composite) | 1 = active, 0 = inactive |
| `sort_order` | INT | YES | 0 | IDX | Display order in dropdowns (lower = first) |
| `created_at` | TIMESTAMP | YES | NULL | — | Laravel timestamp |
| `updated_at` | TIMESTAMP | YES | NULL | — | Laravel timestamp |
| `deleted_at` | TIMESTAMP | YES | NULL | — | Soft delete marker |

### Indexes

| Name | Columns | Type | Purpose |
|------|---------|------|---------|
| PRIMARY | `id` | BTREE | Surrogate PK |
| `subordinations_code_unique` | `code` | UNIQUE BTREE | Logical key, used by seeder `updateOrCreate` |
| `subordinations_status_deleted_at_index` | `status`, `deleted_at` | BTREE | Hot query: "list active non-deleted" for FormSelect |
| `subordinations_sort_order_index` | `sort_order` | BTREE | Order by in `scopeOrdered` |

### Engine / Charset

- Engine: `InnoDB` (foreign-key support, transactions)
- Charset: `utf8mb4`
- Collation: `utf8mb4_unicode_ci`

### Sample Rows (after `SubordinationsDatabaseSeeder`)

| id | code | name_ar | name_en | status | sort_order |
|----|------|---------|---------|--------|------------|
| 1 | ALEPPO-AIRPORT | مطار حلب | Aleppo Airport | 1 | 1 |
| 2 | LATTAKIA-AIRPORT | مطار اللاذقية | Latakia Airport | 1 | 2 |

---

## 3. Modified Table: `users`

### New column

| Column | Type | Null | Default | Key | Description |
|--------|------|------|---------|-----|-------------|
| `subordination_id` | BIGINT UNSIGNED | YES | NULL | FK, IDX | Points to `subordinations.id`. Placed **after** `grade_id` and **before** `manager_id` to group organizational columns. |

### Foreign key constraint

```sql
ALTER TABLE users
  ADD CONSTRAINT users_subordination_id_foreign
  FOREIGN KEY (subordination_id) REFERENCES subordinations(id)
  ON DELETE SET NULL
  ON UPDATE CASCADE;
```

### Index

```sql
CREATE INDEX users_subordination_id_index ON users (subordination_id);
```

### Why `ON DELETE SET NULL`

- Spec § 4.1 BR-6, § 23 R-4: deleting a subordination must not cascade-delete employees.
- Spec § 3 Scenario 6 validates this behaviour.

### Eloquent relationship (user-side)

```php
public function subordination(): BelongsTo<Subordination, $this>
{
    return $this->belongsTo(Subordination::class, 'subordination_id');
}
```

---

## 4. Modified Table: `companies`

**No schema change.** Only the `CompaniesDatabaseSeeder` is modified to insert a new row.

### Sample row (after seed)

| id | company_code | company_name | city | country | is_default | status |
|----|--------------|--------------|------|---------|------------|--------|
| 1 | AIRPORT-ALEPPO | مطار حلب الدولي | حلب | SY | true | 1 |

---

## 5. Modified Table: `branches`

**No schema change.** Only the `BranchesDatabaseSeeder` is modified to insert two new rows (both referencing `companies.id` = 1, the airport).

### Sample rows (after seed)

| id | company_id | branch_code | branch_name | city | country | is_main | status |
|----|------------|-------------|-------------|------|---------|---------|--------|
| 1 | 1 | CIVIL-AVIATION | الطيران المدني | حلب | SY | true | 1 |
| 2 | 1 | SYRIAN-AIR | الخطوط الجوية السورية | حلب | SY | false | 1 |

---

## 6. Validation Rules

### 6.1 `Subordination` (Store)

| Field | Rule | Error Key (lang) |
|-------|------|------------------|
| `code` | `required, string, max:50, regex:/^[A-Z0-9_-]+$/, unique:subordinations,code` | `code_required`, `code_regex`, `code_max`, `code_unique` |
| `name_ar` | `required, string, max:100` | `name_ar_required`, `name_ar_max` |
| `name_en` | `nullable, string, max:100` | `name_en_max` |
| `description` | `nullable, string` | — |
| `status` | `nullable, integer, in:0,1` | `status_in` |
| `sort_order` | `nullable, integer, min:0` | `sort_order_min` |

### 6.2 `Subordination` (Update)

Same as Store, with `Rule::unique('subordinations', 'code')->ignore($subordination->id)`.

### 6.3 `User` (Store / Update) — new rules

| Field | Rule | Error Key (lang) |
|-------|------|------------------|
| `subordination_id` | `nullable, integer, exists:subordinations,id` | `subordination_id_exists` |

### 6.4 Cross-field Rules

None in v1.

---

## 7. Casts & Serialization

### 7.1 `Subordination` model

```php
protected $casts = [
    'status'     => 'integer',
    'sort_order' => 'integer',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    'deleted_at' => 'datetime',
];
```

### 7.2 `User` model

The new `subordination_id` column is a plain integer — no special cast required (Laravel's default behaviour is sufficient).

---

## 8. Eloquent Scopes

### 8.1 `Subordination`

| Scope | SQL Effect | Use Case |
|-------|-----------|----------|
| `scopeActive` | `WHERE status = 1` | Filter dropdowns to active records only |
| `scopeOrdered` | `ORDER BY sort_order ASC, name_ar ASC` | Display dropdowns in the configured order |
| `scopeNotDeleted` (implicit via SoftDeletes) | `WHERE deleted_at IS NULL` | Standard Eloquent behaviour |

Combined usage in `SubordinationRepository::getActive()`:
```php
return Subordination::query()->active()->ordered()->get();
```

### 8.2 `User` (no new scope, but the existing `scopeActive` is unchanged)

The `subordination` relation is *not* auto-included in `scopeActive`; the consumer is expected to eager-load it when needed.

---

## 9. Accessors

### 9.1 `Subordination::getDisplayNameAttribute()`

```php
public function getDisplayNameAttribute(): string
{
    return $this->name_ar ?: $this->name_en ?: $this->code;
}
```

- Used by Vue `FormSelect` to render option labels.
- Returns Arabic name if available, else English, else the code (last-resort identifier).

### 9.2 No new accessors on `User` model

The `subordination` relation is exposed via standard `whenLoaded` in the Resource.

---

## 10. Lifecycle States

### 10.1 `Subordination` lifecycle

```
              ┌─────────────┐
              │   (none)    │   ← row does not exist
              └──────┬──────┘
                     │ create
                     ▼
              ┌─────────────┐
   ┌─────────►│   Active    │◄────── update (status=1)
   │          │  status=1   │
   │          └──────┬──────┘
   │  update (status=0)
   │                 ▼
   │          ┌─────────────┐
   │          │  Inactive   │
   │          │  status=0   │
   │          └──────┬──────┘
   │                 │ delete (soft)
   │                 ▼
   │          ┌─────────────┐
   │          │  Soft       │
   │          │  Deleted    │
   │          │ deleted_at  │
   │          └──────┬──────┘
   │                 │ restore
   └─────────────────┘  (re-enters Active if status=1, Inactive if status=0)
```

**State transitions and their authorization:**

| Transition | Permission Required | Side Effect |
|------------|---------------------|-------------|
| `none → Active` | `create-subordinations` | New row inserted, default `status=1` |
| `Active → Inactive` | `edit-subordinations` | Row stays; dropdowns hide it |
| `Inactive → Active` | `edit-subordinations` | Row re-appears in dropdowns |
| `Active/Inactive → Soft Deleted` | `delete-subordinations` | All `users.subordination_id` references become `NULL` (via FK) |
| `Soft Deleted → Active/Inactive` | `edit-subordinations` | Row is restored; `users.subordination_id` references are **not** automatically re-attached (those had been nulled by `ON DELETE SET NULL`) |

### 10.2 `User.subordination_id` lifecycle

The column is a free-form FK with three logical states:

| State | `subordination_id` value | Meaning |
|-------|--------------------------|---------|
| Unassigned | `NULL` | Employee has no subordination (default for legacy rows) |
| Assigned | `<id>` | Employee is attached to that subordination |
| Detached-by-FK | `NULL` (after a `Subordination` row was deleted) | Same as unassigned; UI shows "—" |

No explicit transition logic is needed; the state is simply what the column currently holds.

---

## 11. Default Values & Bootstrap Data

### 11.1 Default column values (set by migration)

| Table.Column | Default |
|--------------|---------|
| `subordinations.status` | `1` |
| `subordinations.sort_order` | `0` |
| `subordinations.deleted_at` | `NULL` |
| `users.subordination_id` | `NULL` |

### 11.2 Bootstrap data (inserted by `SubordinationsDatabaseSeeder`)

```php
Subordination::updateOrCreate(['code' => 'ALEPPO-AIRPORT'], [
    'name_ar'    => 'مطار حلب',
    'name_en'    => 'Aleppo Airport',
    'sort_order' => 1,
    'status'     => 1,
]);

Subordination::updateOrCreate(['code' => 'LATTAKIA-AIRPORT'], [
    'name_ar'    => 'مطار اللاذقية',
    'name_en'    => 'Latakia Airport',
    'sort_order' => 2,
    'status'     => 1,
]);
```

### 11.3 Bootstrap data (inserted by `CompaniesDatabaseSeeder`)

```php
Company::updateOrCreate(['company_code' => 'AIRPORT-ALEPPO'], [
    'company_name' => 'مطار حلب الدولي',
    'city'         => 'حلب',
    'country'      => 'SY',
    'is_default'   => true,
    'status'       => 1,
    'description'  => 'المطار الدولي في مدينة حلب',
]);
```

### 11.4 Bootstrap data (inserted by `BranchesDatabaseSeeder`)

```php
$company = Company::where('company_code', 'AIRPORT-ALEPPO')->first();

if (! $company) {
    $this->command?->warn('CompaniesDatabaseSeeder must run before BranchesDatabaseSeeder. Skipping.');
    return;
}

Branch::updateOrCreate(
    ['company_id' => $company->id, 'branch_code' => 'CIVIL-AVIATION'],
    [
        'branch_name' => 'الطيران المدني',
        'city'        => 'حلب',
        'country'     => 'SY',
        'is_main'     => true,
        'status'      => 1,
    ]
);

Branch::updateOrCreate(
    ['company_id' => $company->id, 'branch_code' => 'SYRIAN-AIR'],
    [
        'branch_name' => 'الخطوط الجوية السورية',
        'city'        => 'حلب',
        'country'     => 'SY',
        'is_main'     => false,
        'status'      => 1,
    ]
);
```

---

## 12. Migration SQL Reference (for reviewer)

### 12.1 `create_subordinations_table` (forward)

```sql
CREATE TABLE `subordinations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(50) NOT NULL,
  `name_ar` VARCHAR(100) NOT NULL,
  `name_en` VARCHAR(100) NULL,
  `description` TEXT NULL,
  `status` SMALLINT NOT NULL DEFAULT 1,
  `sort_order` INT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  `deleted_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subordinations_code_unique` (`code`),
  KEY `subordinations_status_deleted_at_index` (`status`, `deleted_at`),
  KEY `subordinations_sort_order_index` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 12.2 `add_subordination_id_to_users_table` (forward)

```sql
ALTER TABLE `users`
  ADD COLUMN `subordination_id` BIGINT UNSIGNED NULL AFTER `grade_id`,
  ADD CONSTRAINT `users_subordination_id_foreign`
    FOREIGN KEY (`subordination_id`) REFERENCES `subordinations` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  ADD INDEX `users_subordination_id_index` (`subordination_id`);
```

### 12.3 `add_subordination_id_to_users_table` (reverse)

```sql
ALTER TABLE `users`
  DROP FOREIGN KEY `users_subordination_id_foreign`,
  DROP INDEX `users_subordination_id_index`,
  DROP COLUMN `subordination_id`;
```

### 12.4 `create_subordinations_table` (reverse)

```sql
DROP TABLE IF EXISTS `subordinations`;
```

---

## 13. Data Integrity Invariants

These are enforced at the DB and application layers and must hold at all times.

1. **INV-1:** `subordinations.code` is unique across all rows (including soft-deleted rows for safety — depends on DB; the migration does not use a partial unique index; if a row is soft-deleted and the code is reused, a new `unique` violation will occur. This is intentional: re-using a code is treated as a duplicate).
2. **INV-2:** Every non-NULL `users.subordination_id` references an existing `subordinations.id` (enforced by FK).
3. **INV-3:** A `Subordination` row can be soft-deleted even if employees are attached to it (FK uses `SET NULL`, not `RESTRICT`).
4. **INV-4:** `subordinations.status` is always 0 or 1 (application-enforced; DB column is just `SMALLINT`).
5. **INV-5:** Seeders never duplicate rows — they use `updateOrCreate` on a logical key (`code` for Subordinations, `company_code` for Companies, `[company_id, branch_code]` for Branches).

---

## 14. Index & Performance Notes

| Query | Index Used | Expected Cost (10K users, 5 subordinations) |
|-------|------------|---------------------------------------------|
| `SELECT * FROM subordinations WHERE status = 1 AND deleted_at IS NULL ORDER BY sort_order, name_ar` | `subordinations_status_deleted_at_index` + `subordinations_sort_order_index` | < 1ms (5 rows) |
| `SELECT * FROM users WHERE subordination_id = ? AND status = 1` | `users_subordination_id_index` + `users_status_deleted_at_index` (composite existing) | < 5ms |
| `SELECT u.*, s.name_ar FROM users u LEFT JOIN subordinations s ON u.subordination_id = s.id` | both indexes (nested-loop join) | < 10ms for 10K rows |
| `SELECT * FROM subordinations WHERE code = ?` | `subordinations_code_unique` | < 1ms (unique lookup) |

---

*Last updated: 2026-07-20*
