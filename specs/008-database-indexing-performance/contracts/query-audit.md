# عقد مراجعة الاستعلامات (Query Audit Contract)

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-21

> يحدد هذا العقد **التغييرات الوحيدة** المسموح بها على مستوى الاستعلامات (Eloquent / Query Builder). لا تغيير في أي Repository public signature.

---

## 1. المبادئ (Principles)

| # | المبدأ | المصدر |
|---|--------|--------|
| Q-1 | لا تغيير في الـ output (نفس السجلات، نفس الترتيب) | BR-12 ضمني |
| Q-2 | لا تغيير في الـ Repository public signature | Backward compat |
| Q-3 | كل تعديل **يخدم** index جديد (موجود أو مُضاف) | D-1 |
| Q-4 | استخدام `select()` صريح (موجود فعلاً) | BR-8 |
| Q-5 | استخدام `with()` للعلاقات (موجود فعلاً) | BR-9 |
| Q-6 | استخدام `when()` للفلاتر (موجود فعلاً) | BR-10 |

---

## 2. الـ 7 تعديلات المسموح بها (The 7 Allowed Modifications)

### 2.1 `UserRepository::getAll()` — استبدال `latest()` بـ `orderBy('users.id', 'desc')`

**قبل:**
```php
->latest()
->paginate($perPage);
```

**بعد:**
```php
->orderBy('users.id', 'desc')
->paginate($perPage);
```

**المبرر (D-16):** `latest()` يستخدم `created_at` افتراضياً، وهو غير مفهرس. `id` PK يستخدم clustered index → أسرع بدون filesort. **نفس الترتيب الزمني** لأن `id` auto-increment.

**RISK:** صفر — `id` تزداد رتيباً مع `created_at`.

**اختبار:** `tests/Feature/UserRepositoryTest.php` → test `getAllOrdersByIdDesc` (للتأكد أن النتيجة لم تتغير).

### 2.2 إضافة Method جديدة `UserRepository::getActiveByCompany(int $companyId): Collection`

**قبل:** لا يوجد.

**بعد:**
```php
public function getActiveByCompany(int $companyId): Collection
{
    return $this->query()
        ->select(['id', 'employee_code', 'first_name', 'last_name', 'name', 'email', 'branch_id', 'department_id'])
        ->where('company_id', $companyId)
        ->where('status', 1)
        ->where('is_active_employee', true)
        ->orderBy('first_name')
        ->get();
}
```

**المبرر:** يستخدم `idx_users_company_status_active` الجديد. لا يكسر أي شيء — method جديد.

### 2.3-2.7: لا تغييرات أخرى

الاستعلامات الأخرى (`applyFilters`, `getByCompany`, `getByBranch`, ...) تستخدم الـ `where()` بشكل مثالي وتستفيد من الـ indexes الجديدة بدون تعديل.

---

## 3. الممنوع (Forbidden at Query Level)

❌ **ممنوع:**
- إضافة `DB::raw()` على قيم مدخلة من المستخدم
- استخدام `->pluck('id')->toArray()` ثم `whereIn()` (يخالف BR-12 anti-pattern)
- إضافة `withCount()` بدون استخدام النتيجة
- `LazyCollection` بدون chunk

✅ **مسموح:**
- `->select()` صريح
- `->with()` للعلاقات
- `->when()` شرطي
- `->whereHas()` للعلاقات
- `->withCount()` (لاستخدامه في Resource)
- `selectRaw('COUNT(*) as total')` (مع prepared)

---

## 4. Backward Compatibility Matrix

| Method | Signature Before | Signature After | Compatible? |
|--------|------------------|-----------------|-------------|
| `UserRepository::getAll` | `(array $filters = [], int $perPage = 20): LengthAwarePaginator` | **نفس** | ✅ |
| `UserRepository::findById` | `(int $id): ?User` | **نفس** | ✅ |
| `UserRepository::findByEmail` | `(string $email): ?User` | **نفس** | ✅ |
| `UserRepository::findByEmployeeCode` | `(string $code): ?User` | **نفس** | ✅ |
| `UserRepository::getByCompany` | `(int $companyId): Collection` | **نفس** | ✅ |
| `UserRepository::getActiveByCompany` | **غير موجود** | `(int $companyId): Collection` | ✅ (جديد) |
| `*Repository::query` | `(): Builder` | **نفس** | ✅ |
| `*Repository::create` | `(array $data): Model` | **نفس** | ✅ |
| `*Repository::update` | `(Model, array $data): Model` | **نفس** | ✅ |
| `*Repository::delete` | `(Model): bool` | **نفس** | ✅ |
| `*Service::*` | كل التواقيع | **نفس** | ✅ |
| `*Controller::*` | كل الـ actions | **نفس** | ✅ |
| `*Request::rules` | كل القواعد | **نفس** | ✅ |
| `*Resource::toArray` | `(): array` | **نفس** | ✅ |
| `routes/web.php` | كل المسارات | **نفس** | ✅ |

**النتيجة:** لا تغيير في الـ API. لا حاجة لتحديث الـ frontend.

---

## 5. Output Equivalence Contract

> **قاعدة مطلقة:** كل تعديل استعلام يجب ألا يُغيّر النتيجة (نفس السجلات، نفس الترتيب، نفس الأعمدة).

**اختبار (من spec § 9.4):**

```php
test('repository output unchanged after optimization', function () {
    $before = User::with($this->defaultWith)
        ->where('company_id', 1)
        ->paginate(20)
        ->toArray();

    artisan('migrate', ['--path' => 'database/migrations/2026_07_21_*']);

    $after = User::with($this->defaultWith)
        ->where('company_id', 1)
        ->paginate(20)
        ->toArray();

    expect($after)->toEqual($before);
});
```

---

*آخر تحديث: 2026-07-21*
*الإصدار: 1.0.0*
