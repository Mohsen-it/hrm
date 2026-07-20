# تهيئة بيانات مطار حلب الدولي + فروع الطيران المدني والخطوط الجوية السورية + جدول التبعية - المواصفات

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-20
**الحالة:** مسودة
**الوحدات:** `Modules/Companies`, `Modules/Branches`, `Modules/Subordinations` (جديد), `Modules/Users`

---

## 1. نظرة عامة (Overview)

تقوم هذه الميزة بتجهيز البيانات التأسيسية اللازمة لتشغيل نظام HRM لشركة تعمل في قطاع الطيران/المطارات، وتحديداً لشركة **مطار حلب الدولي**، وذلك عبر ثلاثة محاور:

1. **تعبئة بيانات الشركات** (Companies): إضافة سجل شركة "مطار حلب الدولي" عبر `CompaniesDatabaseSeeder` حتى يكون للشركة سجل رسمي في النظام.
2. **تعبئة بيانات الفروع** (Branches): إضافة فرعين أساسيين تابعين لمطار حلب الدولي عبر `BranchesDatabaseSeeder`:
   - **الطيران المدني** (الهيئة العامة للطيران المدني السوري).
   - **الخطوط الجوية السورية** (الناقل الوطني).
3. **إنشاء وحدة "التبعية"** (Subordinations) الجديدة بالكامل: جدول مستقل لتخنيف الأماكن/المواقع الجغرافية أو التشغيلية التي يتبع لها الموظف إدارياً (مثل مطار حلب، مطار اللاذقية)، مع `SubordinationsDatabaseSeeder` يضيف سجلين: **مطار حلب** و**مطار اللاذقية**.
4. **ربط الموظفين بالتبعية**: إضافة عمود `subordination_id` إلى جدول `users`، وإظهار قائمة منسدلة (FormSelect) في نموذجَي **إضافة موظف** و**تعديل موظف** لاختيار التبعية، وحفظها عند الإرسال.

> النتيجة: عند تنفيذ `php artisan db:seed` بعد تطبيق الـ migrations، يكون النظام جاهزاً ببيانات واقعية لشركة مطار حلب الدولي، فروعها، ومواقعها الجغرافية، ويمكن للمدير إضافة موظف وربطه مباشرة بمطار محدد.

---

## 2. قصص المستخدمين (User Stories)

- [ ] كـ **مدير نظام**، بعد تنفيذ seeders النظام، أرى شركة "مطار حلب الدولي" في قائمة الشركات.
- [ ] كـ **مدير نظام**، أرى فرعي "الطيران المدني" و"الخطوط الجوية السورية" مربوطين بشركة مطار حلب الدولي.
- [ ] كـ **مدير نظام**، أرى سجلَي التبعية "مطار حلب" و"مطار اللاذقية" في قائمة منسدلة ضمن نموذج إضافة/تعديل الموظف.
- [ ] كـ **مدير نظام**، أستطيع اختيار تبعية لموظف جديد من القائمة المنسدلة وحفظها بنجاح.
- [ ] كـ **مدير نظام**، أستطيع تعديل تبعية موظف موجود وحفظ التغيير بنجاح.
- [ ] كـ **مدير موارد بشرية**، أرى تبعية الموظف ظاهرة في بطاقة عرض بياناته وفي DataTable الخاص بقائمة الموظفين.
- [ ] كـ **مدير نظام**، أستطيع تصفية قائمة الموظفين حسب التبعية (مستقبلي، كتحسين).
- [ ] كـ **مطوّر**، أستطيع تشغيل الـ seeders بشكل مستقل لكل وحدة: `CompaniesDatabaseSeeder`، `BranchesDatabaseSeeder`، `SubordinationsDatabaseSeeder`.

---

## 3. سيناريوهات الاستخدام (User Scenarios & Testing)

### السيناريو 1 — تشغيل الـ Seeders
**الفاعل:** مدير النظام (مطوّر / DevOps).
**التمهيد:** قاعدة بيانات جديدة (أو مهيأة للـ seed).
**الخطوات:**
1. تنفيذ `php artisan migrate`.
2. تنفيذ `php artisan db:seed --class=Modules\\Companies\\Database\\Seeders\\CompaniesDatabaseSeeder`.
3. تنفيذ `php artisan db:seed --class=Modules\\Branches\\Database\\Seeders\\BranchesDatabaseSeeder`.
4. تنفيذ `php artisan db:seed --class=Modules\\Subordinations\\Database\\Seeders\\SubordinationsDatabaseSeeder`.

**معايير القبول:**
- ✅ جدول `companies` يحتوي على سجل: `company_code = AIRPORT-ALEPPO`، `company_name = "مطار حلب الدولي"`.
- ✅ جدول `branches` يحتوي على سجلين مربوطين بـ `company_id` الخاص بمطار حلب:
  - `branch_code = CIVIL-AVIATION`، `branch_name = "الطيران المدني"`.
  - `branch_code = SYRIAN-AIR`، `branch_name = "الخطوط الجوية السورية"`.
- ✅ جدول `subordinations` يحتوي على سجلين:
  - `code = ALEPPO-AIRPORT`، `name_ar = "مطار حلب"`.
  - `code = LATTAKIA-AIRPORT`، `name_ar = "مطار اللاذقية"`.
- ✅ الـ seeders idempotent: تشغيلها مرتين لا يضيف سجلات مكررة (use `updateOrCreate` على الـ code).

### السيناريو 2 — اختيار تبعية في نموذج إضافة موظف
**الفاعل:** مدير موارد بشرية (صلاحية `create-users`).
**التمهيد:** الـ seeders منفذة؛ يوجد موظف آخر في النظام لكنه لم تُحفظ له تبعية.
**الخطوات:**
1. فتح `/users/create`.
2. في قسم "المعلومات التنظيمية"، تظهر قائمة منسدلة جديدة **"التبعية"** إلى جانب "الشركة"، "الفرع"، إلخ.
3. المستخدم يفتح القائمة ويختار **"مطار حلب"**.
4. يضغط "حفظ".

**معايير القبول:**
- ✅ الـ FormSelect يعرض فقط سجلَي التبعية النشطين (`status = 1`).
- ✅ السجل يحفظ في `users.subordination_id`.
- ✅ تظهر رسالة "تم إنشاء الموظف بنجاح".

### السيناريو 3 — تعديل تبعية موظف موجود
**الفاعل:** مدير موارد بشرية (صلاحية `edit-users`).
**التمهيد:** موظف حالته `subordination_id = 1` (مطار حلب).
**الخطوات:**
1. فتح `/users/{id}/edit`.
2. القائمة المنسدلة "التبعية" تعرض "مطار حلب" كقيمة محفوظة.
3. المستخدم يختار **"مطار اللاذقية"**.
4. يضغط "حفظ".

**معايير القبول:**
- ✅ التحديث ينعكس في قاعدة البيانات (`subordination_id = 2`).
- ✅ صفحة عرض الموظف (`/users/{id}`) تعرض اسم التبعية الجديد.

### السيناريو 4 — تطابق تبعية الموظف مع تبعية الفرع (اختياري UX)
**الخطوات:**
1. المستخدم يختار الفرع "الطيران المدني" (التابع لمطار حلب).
2. النظام يقترح افتراضياً اختيار "مطار حلب" كقيمة ابتدائية للتبعية (لا يجبر، فقط اقتراح).

**معايير القبول:**
- ✅ عند اختيار فرع تابع لمطار، تختار التبعية المرتبطة تلقائياً (best-effort).

### السيناريو 5 — موظف بدون تبعية
**الخطوات:**
1. في نموذج إضافة موظف، المستخدم يترك حقل "التبعية" فارغاً.

**معايير القبول:**
- ✅ النموذج يقبل الحقل كـ `nullable`، ولا يحدث خطأ validation.

### السيناريو 6 — حذف تبعية لها موظفون مرتبطون (حالة حدّ)
**الخطوات:**
1. مدير يحاول حذف سجل تبعية "مطار حلب" (الذي له موظفون).

**معايير القبول:**
- ✅ `ON DELETE SET NULL` على FK: عند حذف سجل التبعية، `users.subordination_id` يصبح `NULL` بدلاً من فشل الحذف أو حذف الموظفين (cascade on users محظور — لا نحذف موظفين بسبب تبعية).

### السيناريو 7 — عرض التبعية في DataTable القائمة
**الخطوات:**
1. فتح `/users` (قائمة الموظفين).

**معايير القبول:**
- ✅ عمود "التبعية" ظاهر في الجدول يعرض اسم التبعية (أو `—` إن لم تُحدد).
- ✅ حقل `subordination_id` مُحمَّل eager-loaded لمنع N+1 (في `UserRepository::$defaultWith`).

---

## 4. المتطلبات الوظيفية (Functional Requirements)

### 4.1 Business Rules
1. **BR-1** شركة مطار حلب الدولي تُسجَّل مرة واحدة فقط (عبر `updateOrCreate` على `company_code`).
2. **BR-2** كل من فرعي "الطيران المدني" و"الخطوط الجوية السورية" ينتميان لشركة مطار حلب الدولي (لا يمكن إنشاء الفرع بدون الشركة).
3. **BR-3** سجلَا التبعية "مطار حلب" و"مطار اللاذقية" مستقلان عن الشركات والفروع (لا يوجد FK من `subordinations` على `companies` أو `branches`).
4. **BR-4** الموظف ينتمي لتبعية واحدة فقط (عمود `subordination_id` مفرد، ليس many-to-many).
5. **BR-5** `subordination_id` على `users` اختياري (`nullable`) — النظام يقبل موظفين بدون تبعية.
6. **BR-6** عند حذف سجل تبعية، جميع الموظفين المرتبطين يحصلون على `subordination_id = NULL` (FK: `SET NULL`).
7. **BR-7** الـ seeders يجب أن تكون **idempotent** (تشغيلها عدة مرات لا يضيف سجلات مكررة).
8. **BR-8** لا يُسمح بحذف سجل تبعية ما دام اسمه مستخدماً في قائمة عرض (يمكن حذفه ولكن الموظفين يفقدون الربط).
9. **BR-9** الكود `code` للتبعية unique (مفتاح فريد) ويستخدم كمعرّف منطقي في الـ seeders.

### 4.2 Validation Rules
1. **VR-1** عند إنشاء/تعديل سجل تبعية:
   - `name_ar` مطلوب، نص، حتى 100 حرف.
   - `name_en` اختياري، نص، حتى 100 حرف.
   - `code` مطلوب، فريد (unique)، نمط `[A-Z0-9_-]+`، حتى 50 حرف.
   - `status` اختياري، 0 أو 1 (افتراضي 1).
   - `description` اختياري، نص.
2. **VR-2** عند إنشاء/تعديل موظف:
   - `subordination_id` اختياري، integer، `exists:subordinations,id`.
3. **VR-3** عند تصفية قائمة الموظفين:
   - `subordination_id` كـ query string يقبل integer موجب ويُطبَّق على الاستعلام.
4. **VR-4** لا يُسمح بحذف سجل تبعية إذا كان `force=true` مطلوباً — يجب تأكيد بصراحة عبر Modal تأكيد (UX فقط، لا تقييد تقني صارم).

---

## 5. بنية البيانات (Data Model)

### 5.1 جدول `subordinations` (جديد)
| العمود | النوع | القيد | الوصف |
|--------|-------|------|-------|
| `id` | bigint unsigned | PK, auto-increment | المعرّف |
| `code` | varchar(50) | **UNIQUE**, NOT NULL | معرّف منطقي ثابت (مثل `ALEPPO-AIRPORT`) |
| `name_ar` | varchar(100) | NOT NULL | الاسم بالعربية |
| `name_en` | varchar(100) | nullable | الاسم بالإنجليزية |
| `description` | text | nullable | وصف اختياري |
| `status` | smallint | NOT NULL, default 1 | 1 = نشط، 0 = متوقف |
| `sort_order` | int | nullable, default 0 | ترتيب العرض |
| `created_at`, `updated_at` | timestamps | — | Laravel defaults |
| `deleted_at` | timestamp | nullable | Soft delete |

**الفهارس (Indexes):**
- `unique('code')`
- `index(['status', 'deleted_at'])`
- `index('sort_order')`

### 5.2 جدول `users` (تعديل)
| العمود الجديد | النوع | القيد | الوصف |
|---------------|-------|------|-------|
| `subordination_id` | bigint unsigned | nullable, FK → `subordinations.id` ON DELETE SET NULL | التبعية الإدارية للموظف |

**مكان الإدراج في الجدول:** بعد `grade_id` وقبل `manager_id` (لتجميع أعمدة الهيكل التنظيمي).

### 5.3 جدول `companies` (لا تغيير هيكلي)
- الـ schema الحالي كافٍ. لا حاجة لتعديل migration.
- التغيير الوحيد: تعبئة `CompaniesDatabaseSeeder` بسجل مطار حلب الدولي.

### 5.4 جدول `branches` (لا تغيير هيكلي)
- الـ schema الحالي كافٍ. لا حاجة لتعديل migration.
- التغيير الوحيد: تعبئة `BranchesDatabaseSeeder` بفرعَي الطيران المدني والخطوط الجوية السورية.

### 5.5 البيانات المُعبَّأة (Seed Data)

#### `companies`
| company_code | company_name | city | country | is_default | status |
|--------------|--------------|------|---------|------------|--------|
| AIRPORT-ALEPPO | مطار حلب الدولي | حلب | SY | true | 1 |

#### `branches` (مع `company_id` = id مطار حلب)
| branch_code | branch_name | city | country | is_main | status |
|------------|-------------|------|---------|---------|--------|
| CIVIL-AVIATION | الطيران المدني | حلب | SY | true | 1 |
| SYRIAN-AIR | الخطوط الجوية السورية | حلب | SY | false | 1 |

#### `subordinations`
| code | name_ar | name_en | status | sort_order |
|------|---------|---------|--------|------------|
| ALEPPO-AIRPORT | مطار حلب | Aleppo Airport | 1 | 1 |
| LATTAKIA-AIRPORT | مطار اللاذقية | Latakia Airport | 1 | 2 |

---

## 6. النماذج (Models)

### 6.1 `Modules\Subordinations\Models\Subordination` (جديد)
- يستخدم `HasFactory` و `SoftDeletes`.
- `$table = 'subordinations'`.
- `$fillable = ['code', 'name_ar', 'name_en', 'description', 'status', 'sort_order']`.
- `$casts`: `status` → integer, `sort_order` → integer.
- **العلاقات:**
  - `users(): HasMany<User>` — `hasMany(User::class, 'subordination_id')`.
- **Scopes:**
  - `scopeActive($q)` → `where('status', 1)`.
  - `scopeOrdered($q)` → `orderBy('sort_order')->orderBy('name_ar')`.
- **Accessors:**
  - `getDisplayNameAttribute()` → يرجّع `name_ar` (أو `name_en` كبديل).
- **الطرق الثابتة:**
  - `findByCode(string $code): ?self` للـ seeders والاختبارات.

### 6.2 `Modules\Users\Models\User` (تعديل)
- إضافة `subordination_id` إلى `$fillable`.
- **علاقة جديدة:**
  - `subordination(): BelongsTo<Subordination, $this>` → `belongsTo(Subordination::class, 'subordination_id')`.
- لا حاجة لتعديل `$casts` (العمود integer عادي).

### 6.3 `Modules\Companies\Models\Company` (لا تغيير)
- الـ model جاهز كما هو.

### 6.4 `Modules\Branches\Models\Branch` (لا تغيير)
- الـ model جاهز كما هو.

---

## 7. الخدمات والمستودعات (Services & Repositories)

### 7.1 `Modules\Subordinations\Services\SubordinationService` (جديد)
**يعتمد على:** `SubordinationRepository` عبر Constructor Injection (لا `app()` / `resolve()`).

| الطريقة | الوصف |
|---------|------|
| `getAllSubordinations(array $filters = []): LengthAwarePaginator` | قائمة مع pagination و filters (search, status). |
| `getActiveSubordinations(): Collection` | سجلات نشطة بدون pagination، مرتبة (`Ordered` scope). |
| `getSubordinationById(int $id): ?Subordination` | البحث بالمعرّف. |
| `getSubordinationByCode(string $code): ?Subordination` | البحث بالكود. |
| `createSubordination(array $data): Subordination` | إنشاء بعد التحقق. |
| `updateSubordination(Subordination $s, array $data): Subordination` | تحديث. |
| `deleteSubordination(Subordination $s): bool` | حذف ناعم. |
| `validateSubordinationData(array $data, ?int $ignoreId = null): array` | تطبيق قواعد validation (نقل من Request إلى Service). |

### 7.2 `Modules\Subordinations\Repositories\SubordinationRepository` (جديد)
| الطريقة | الوصف |
|---------|------|
| `query(): Builder` | new query. |
| `getAll(array $filters, int $perPage): LengthAwarePaginator` | مع eager-load + filters. |
| `findById(int $id): ?Subordination` | — |
| `findByCode(string $code): ?Subordination` | — |
| `getActive(): Collection` | `active()->ordered()->get()`. |
| `create(array $data): Subordination` | `Subordination::create($data)`. |
| `update(Subordination $s, array $data): Subordination` | `update + fresh()`. |
| `delete(Subordination $s): bool` | `$s->delete()`. |
| `applyFilters(Builder $q, array $filters): Builder` | search (`code`, `name_ar`, `name_en`) + status. |

### 7.3 `Modules\Users\Services\UserService` (تعديل)
- لا تغيير في التواقيع العامة. فقط تمرير `subordination_id` ضمن `$validated` في `createUser` و `updateUser` (سيتم حفظه تلقائياً عبر `User::create` / `$user->update` لأن العمود في `$fillable`).

### 7.4 `Modules\Users\Repositories\UserRepository` (تعديل)
- إضافة `'subordination'` إلى `$defaultWith` لمنع N+1.
- إضافة `applyFilters`: دعم `subordination_id` filter.

---

## 8. المتحكمات (Controllers)

### 8.1 `Modules\Subordinations\Http\Controllers\SubordinationsController` (جديد)
- يتبع النمط: Controller → Service → Repository.
- Actions: `index, create, store, show, edit, update, destroy`.
- كل action محمي بـ `$this->authorize('view|create|edit|delete-subordinations')`.
- يستخدم `Inertia::render('Subordinations/{Index,Create,Edit,Show}')`.
- `formOptions()` يعيد `statusOptions` فقط (لا يحتاج قوائم أخرى).

### 8.2 `Modules\Users\Http\Controllers\UsersController` (تعديل)
- في `formOptions()`: إضافة `'subordinations' => fn () => $this->subordinationService->getActiveSubordinations()->map(...)`.
- يحتاج constructor injection جديد: `SubordinationService`.

---

## 9. طلبات النموذج (Form Requests)

### 9.1 `Modules\Subordinations\Http\Requests\StoreSubordinationRequest` (جديد)
- `authorize`: `auth()->user()->hasPermissionTo('create-subordinations')`.
- `rules`:
  ```php
  return [
      'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', 'unique:subordinations,code'],
      'name_ar' => ['required', 'string', 'max:100'],
      'name_en' => ['nullable', 'string', 'max:100'],
      'description' => ['nullable', 'string'],
      'status' => ['nullable', 'integer', 'in:0,1'],
      'sort_order' => ['nullable', 'integer', 'min:0'],
  ];
  ```
- `messages`: ترجمة عربية لكل قاعدة.

### 9.2 `Modules\Subordinations\Http\Requests\UpdateSubordinationRequest` (جديد)
- نفس قواعد Store مع `Rule::unique` يتجاهل المعرّف الحالي.

### 9.3 `Modules\Users\Http\Requests\StoreUserRequest` (تعديل)
- إضافة قاعدة: `'subordination_id' => ['nullable', 'integer', 'exists:subordinations,id']`.
- إضافة رسالة: `'subordination_id.exists' => __('users.subordination_id_exists')`.

### 9.4 `Modules\Users\Http\Requests\UpdateUserRequest` (تعديل)
- نفس الإضافة.

---

## 10. موارد API (Resources)

### 10.1 `Modules\Subordinations\Http\Resources\SubordinationResource` (جديد)
- يحوي: `id, code, name_ar, name_en, display_name, description, status, sort_order, created_at, updated_at`.

### 10.2 `Modules\Users\Http\Resources\UserResource` (تعديل)
- إضافة الحقول:
  - `'subordination_id' => $this->subordination_id`
  - `'subordination' => $this->whenLoaded('subordination', fn() => $this->subordination ? ['id' => ..., 'name_ar' => ..., 'name_en' => ...] : null)`.

---

## 11. المسارات (Routes)

### 11.1 `Modules/Subordinations/routes/web.php` (جديد)
```php
Route::middleware(['auth'])->prefix('subordinations')->name('subordinations.')->group(function () {
    Route::middleware('permission:view-subordinations')->group(function () {
        Route::get('/', [SubordinationsController::class, 'index'])->name('index');
        Route::get('/{subordination}', [SubordinationsController::class, 'show'])->name('show');
    });
    Route::middleware('permission:create-subordinations')->group(function () {
        Route::get('/create', [SubordinationsController::class, 'create'])->name('create');
        Route::post('/', [SubordinationsController::class, 'store'])->name('store');
    });
    Route::middleware('permission:edit-subordinations')->group(function () {
        Route::get('/{subordination}/edit', [SubordinationsController::class, 'edit'])->name('edit');
        Route::put('/{subordination}', [SubordinationsController::class, 'update'])->name('update');
    });
    Route::middleware('permission:delete-subordinations')->delete('/{subordination}', [SubordinationsController::class, 'destroy'])->name('destroy');
});
```

### 11.2 `Modules/Users/routes/web.php` (لا تغيير)
- الـ routes الموجودة كافية (Resource users).

---

## 12. الـ Seeders

### 12.1 `Modules/Companies/database/seeders/CompaniesDatabaseSeeder` (تعديل)
- استخدام `Company::updateOrCreate(['company_code' => 'AIRPORT-ALEPPO'], [...])`.
- تعبئة الحقول: `company_name = "مطار حلب الدولي"`, `city = "حلب"`, `country = "SY"`, `is_default = true`, `status = 1`, `description = "المطار الدولي في مدينة حلب"`.

### 12.2 `Modules/Branches/database/seeders/BranchesDatabaseSeeder` (تعديل)
- البحث عن `company_id` لشركة مطار حلب (بالكود `AIRPORT-ALEPPO`).
- إذا لم تُوجد الشركة، الـ seeder يُسجّل warning ويتوقف برشاقة (لا يفشل).
- إنشاء فرعَي:
  - `branch_code = "CIVIL-AVIATION"`, `branch_name = "الطيران المدني"`, `is_main = true`, `city = "حلب"`.
  - `branch_code = "SYRIAN-AIR"`, `branch_name = "الخطوط الجوية السورية"`, `is_main = false`, `city = "حلب"`.
- استخدام `updateOrCreate` على `['company_id', 'branch_code']`.

### 12.3 `Modules/Subordinations/database/seeders/SubordinationsDatabaseSeeder` (جديد)
- استخدام `Subordination::updateOrCreate(['code' => 'ALEPPO-AIRPORT'], ['name_ar' => 'مطار حلب', 'name_en' => 'Aleppo Airport', 'sort_order' => 1, 'status' => 1])`.
- استخدام `Subordination::updateOrCreate(['code' => 'LATTAKIA-AIRPORT'], ['name_ar' => 'مطار اللاذقية', 'name_en' => 'Latakia Airport', 'sort_order' => 2, 'status' => 1])`.

### 12.4 الترتيب في `DatabaseSeeder` (الجذر)
- ضمان تشغيل الـ seeders بالترتيب:
  1. `Modules\Companies\Database\Seeders\CompaniesDatabaseSeeder` (يجب أن يسبق الفروع).
  2. `Modules\Branches\Database\Seeders\BranchesDatabaseSeeder`.
  3. `Modules\Subordinations\Database\Seeders\SubordinationsDatabaseSeeder`.
  4. `Modules\Users\Database\Seeders\UsersDatabaseSeeder`.

---

## 13. الواجهة الأمامية (Frontend / Vue)

### 13.1 `resources/js/Pages/Users/Create.vue` (تعديل)
- إضافة `subordinations: { type: Array, default: () => [] }` إلى `defineProps`.
- إضافة `subordination_id: ''` إلى `form` reactive.
- داخل `<FormSection title="organizational_info">`: إضافة `<FormSelect>` للتبعية:
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
- (اختياري UX) `watch` على `form.company_id`: إذا تطابق الفرع المختار مع شركة مطار حلب، يُقترح `subordination_id = id_of('ALEPPO-AIRPORT')`.

### 13.2 `resources/js/Pages/Users/Edit.vue` (تعديل)
- نفس الإضافات.
- `subordination_id: props.user.subordination_id || ''` لتهيئة القيمة.

### 13.3 `resources/js/Pages/Users/Index.vue` (تعديل)
- إضافة عمود "التبعية" إلى DataTable columns.
- تمرير `subordinations` كـ prop من Controller (`UsersController::index`).
- دعم filter `subordination_id` في الـ query.

### 13.4 `resources/js/Pages/Users/Show.vue` (تعديل)
- عرض `user.subordination?.name_ar` إن وُجد.

### 13.5 `resources/js/Pages/Subordinations/*` (جديد - أساسي)
- `Index.vue` → DataTable للسجلات.
- `Create.vue`, `Edit.vue` → نموذج بسيط.
- (اختياري) `Show.vue`.
- يجب استخدام المكونات المشتركة: `DataTable`, `FormInput`, `FormSelect`, `FormTextarea`, `FormModal`, `ConfirmDialog`, `PageHeader`, `Button`, `Card`, `FormSection`, `FormActions`, `ErrorSummary`.
- دعم RTL كامل.
- استخدام `useTranslations` composable.

### 13.6 تحديث Sidebar / Navigation
- إضافة رابط "التبعية" تحت قسم "الهيكل التنظيمي" في الـ Sidebar (بجانب الشركات والفروع).
- الـ route name: `subordinations.index`.

---

## 14. ملفات الترجمة (Lang)

### 14.1 `Modules/Subordinations/lang/ar/subordinations.php` (جديد)
```php
return [
    'title' => 'التبعية',
    'code' => 'الرمز',
    'name_ar' => 'الاسم بالعربية',
    'name_en' => 'الاسم بالإنجليزية',
    'display_name' => 'الاسم المعروض',
    'description' => 'الوصف',
    'status' => 'الحالة',
    'sort_order' => 'ترتيب العرض',
    'add_new' => 'إضافة تبعية',
    'edit_subordination' => 'تعديل تبعية',
    'view_subordination' => 'عرض التبعية',
    'index_description' => 'إدارة الأماكن/المواقع التي يتبع لها الموظفون',
    'create_description' => 'أدخل بيانات التبعية الجديدة',
    'select_subordination' => '— اختر التبعية —',
    'created_successfully' => 'تم إنشاء التبعية بنجاح',
    'updated_successfully' => 'تم تحديث التبعية بنجاح',
    'deleted_successfully' => 'تم حذف التبعية بنجاح',
    'code_unique' => 'رمز التبعية مستخدم من قبل',
    'code_regex' => 'يجب أن يحتوي الرمز على حروف لاتينية كبيرة وأرقام وشرطة فقط',
    'name_ar_required' => 'الاسم بالعربية مطلوب',
    'name_ar_max' => 'الاسم بالعربية يجب ألا يتجاوز 100 حرف',
    'code_required' => 'الرمز مطلوب',
    'code_max' => 'الرمز يجب ألا يتجاوز 50 حرفاً',
    'delete_confirm_title' => 'تأكيد الحذف',
    'delete_confirm_message' => 'هل أنت متأكد من حذف التبعية ":name"؟ سيتم إلغاء ربط جميع الموظفين بها.',
];
```

### 14.2 `Modules/Subordinations/lang/en/subordinations.php` (جديد)
- نظير بالإنجليزية.

### 14.3 `Modules/Users/lang/ar/users.php` (تعديل)
- إضافة: `'subordination' => 'التبعية'`، `'select_subordination' => '— اختر التبعية —'`، `'subordination_id_exists' => 'التبعية المختارة غير موجودة'`.
- `'subordination_id_required' => 'التبعية مطلوبة'` (لو طُلب لاحقاً).

### 14.4 `Modules/Users/lang/en/users.php` (تعديل)
- نظير بالإنجليزية.

---

## 15. الصلاحيات (Permissions)

### 15.1 صلاحيات جديدة (تُضاف عبر `Spatie\Permission`)
| الصلاحية | الوصف |
|---------|------|
| `view-subordinations` | عرض قائمة/تفاصيل التبعية |
| `create-subordinations` | إضافة تبعية |
| `edit-subordinations` | تعديل تبعية |
| `delete-subordinations` | حذف تبعية |

### 15.2 تسجيل الصلاحيات
- إضافة إلى `config/permissions.php` (إن وُجد) أو عبر seeder/Seed مخصص.
- ربطها بدور `super-admin` تلقائياً (الـ role الأعلى).

### 15.3 استخدام الصلاحيات
- في `SubordinationsController` (authorize).
- في `StoreSubordinationRequest` / `UpdateSubordinationRequest` (authorize).
- في الـ routes (middleware `permission:`).
- في الـ Sidebar: إخفاء رابط "التبعية" إن لم يكن لدى المستخدم `view-subordinations`.

---

## 16. الـ Migrations

### 16.1 `Modules/Subordinations/database/migrations/YYYY_MM_DD_HHMMSS_create_subordinations_table.php` (جديد)
- اسم الملف يتبع `snake_case` + timestamp.
- `Schema::create('subordinations', ...)` كما في § 5.1.
- `down()`: `Schema::dropIfExists('subordinations')`.

### 16.2 `database/migrations/YYYY_MM_DD_HHMMSS_add_subordination_id_to_users_table.php` (جديد)
- `Schema::table('users', function (Blueprint $table) { $table->foreignId('subordination_id')->nullable()->after('grade_id')->constrained('subordinations')->nullOnDelete(); $table->index('subordination_id'); });`.
- `down()`: `dropForeign + dropColumn + dropIndex`.

---

## 17. Service Provider والربط (Wiring)

### 17.1 `Modules/Subordinations/Providers/SubordinationsServiceProvider` (جديد)
- تسجيل RouteServiceProvider (للـ routes).
- تسجيل `SubordinationRepository`، `SubordinationService` كـ singletons (أو تركها لـ auto-resolution).
- تحميل ملفات الترجمة: `loadTranslationsFrom(__DIR__ . '/../lang', 'subordinations')`.
- تحميل الـ migrations: `loadMigrationsFrom(__DIR__ . '/../database/migrations')`.
- تحميل الـ views (إن وُجدت Blade).
- تسجيل الـ Seeder في `DatabaseSeeder` الجذر (في `module.json` أو يدوياً في `database/seeders/DatabaseSeeder.php`).

### 17.2 `modules_statuses.json` (تعديل)
- إضافة `"Subordinations": true` لتفعيل الوحدة.

---

## 18. اختبارات القبول (Acceptance Criteria)

- [ ] `php artisan migrate` ينجح ويُنشئ جدول `subordinations` ويضيف العمود `subordination_id` إلى `users`.
- [ ] `php artisan db:seed --class=Modules\\Subordinations\\Database\\Seeders\\SubordinationsDatabaseSeeder` ينجح ويُضيف سجلَي مطار حلب ومطار اللاذقية.
- [ ] `php artisan db:seed --class=Modules\\Companies\\Database\\Seeders\\CompaniesDatabaseSeeder` ينجح ويُضيف سجل مطار حلب الدولي.
- [ ] `php artisan db:seed --class=Modules\\Branches\\Database\\Seeders\\BranchesDatabaseSeeder` ينجح ويُضيف فرعي الطيران المدني والخطوط الجوية السورية (مرتبطين بالشركة).
- [ ] تشغيل كل seeder مرتين متتاليتين لا يُنشئ سجلات مكررة.
- [ ] فتح `/subordinations` يعرض القائمة (للمستخدم بصلاحية `view-subordinations`).
- [ ] فتح `/users/create` يعرض حقل "التبعية" في قسم المعلومات التنظيمية.
- [ ] اختيار تبعية وحفظ النموذج يحفظ القيمة في `users.subordination_id`.
- [ ] فتح `/users/{id}/edit` يعرض التبعية الحالية محفوظة في القائمة المنسدلة.
- [ ] تعديل التبعية ينعكس في قاعدة البيانات وصفحة العرض.
- [ ] `php artisan pint` لا يبلّغ عن أخطاء تنسيق.
- [ ] `php artisan test` يجتاز أي اختبار موجود + الاختبارات الجديدة (إن أُضيفت).

---

## 19. معايير النجاح (Success Criteria)

| المعيار | القياس | الهدف |
|---------|--------|------|
| **SC-1**: جاهزية البيانات | تنفيذ كل الـ seeders بعد `migrate:fresh --seed` | 100% من السجلات (1 شركة + 2 فرع + 2 تبعية) موجودة في الجداول |
| **SC-2**: Idempotency | تشغيل كل seeder مرتين متتاليتين | عدد السجلات يبقى ثابتاً (لا ازدواج) |
| **SC-3**: أداء تحميل قائمة الموظفين | وقت استجابة Inertia request لصفحة `/users` (20 موظف) | أقل من 300ms (معيار الدستور VI) |
| **SC-4**: عدم وجود N+1 | عدد استعلامات DB عند تحميل قائمة موظفين مع التبعية | ≤ 10 (مع eager loading) |
| **SC-5**: تكامل البيانات | عند حذف سجل تبعية، موظفوها يحصلون على `subordination_id = NULL` | 0 موظفين يحذفون (cascade محظور) |
| **SC-6**: دعم RTL | جميع نماذج ومكونات وحدة Subordinations | تظهر `dir="rtl"` افتراضياً |
| **SC-7**: دعم ثنائي اللغة | كل النصوص في `subordinations.php` لها مقابل en | 100% مفاتيح مترجمة |
| **SC-8**: تجربة المستخدم | مستخدم HR يستطيع إضافة موظف وتعديل تبعيته بدون مساعدة تقنية | 3 نقرات أو أقل (Create → Organizational → Select → Save) |
| **SC-9**: اتساق الـ FK | كل قيمة `users.subordination_id` تشير لسجل موجود في `subordinations.id` | 100% (يُضمن عبر `exists:subordinations,id` validation) |

---

## 20. الافتراضات (Assumptions)

1. **A-1** الترميز الموحد للمعرّفات المنطقية (`code`) للحاقنات: حروف لاتينية كبيرة + أرقام + `_` + `-` فقط (نمط `^[A-Z0-9_-]+$`).
2. **A-2** لا توجد وحدة "مواقع جغرافية" منفصلة (Modules/Zones) يجب ربطها بالتبعية — التبعية مستقلة تماماً.
3. **A-3** الإصدار 1.0 من التبعية يدعم علاقة one-to-many فقط (موظف → تبعية واحدة). دعم many-to-many ليس مطلوباً في هذه المرحلة.
4. **A-4** لا حاجة لصلاحيات منفصلة لـ "view" التبعية في نموذج الموظف — أي مستخدم بصلاحية `create-users` / `edit-users` يرى ويختار التبعية.
5. **A-5** لا حاجة لتدقيق (Audit Log) على تغييرات التبعية في هذه المرحلة (قد يُضاف لاحقاً).
6. **A-6** الـ super-admin (id = 10000) قد لا يكون له تبعية (الـ scope الحالي `withoutSuperAdmin` يطبَّق كما هو).
7. **A-7** الإصدار 1.0 يستخدم `subordination_id` integer FK. لا UUID ولا soft FK.

---

## 21. خارج النطاق (Out of Scope)

- ❌ دعم many-to-many لموظف ↔ تبعية (علاقة متعددة).
- ❌ شجرة هرمية للتبعيات (parent/child التبعية).
- ❌ تاريخ تغييرات التبعية (effective_from / effective_to).
- ❌ ربط التبعية بأجهزة البصمة أو الورديات مباشرة.
- ❌ تصدير/استيراد سجلات التبعية من/إلى Excel/CSV.
❌ API خارجي للوصول للتبعيات (Module Mobile).
- ❌ صلاحيات على مستوى سجل (record-level permissions).
- ❌ سجل تدقيق (Audit Trail) لتغييرات التبعية.
- ❌ واجهة CRUD لربط الموظف بأكثر من تبعية في وقت واحد.

---

## 22. الاعتماديات (Dependencies)

### 22.1 يعتمد على
- **Modules/Companies** (موجود): الـ seeder يضيف سجلاً في `companies`.
- **Modules/Branches** (موجود): الـ seeder يضيف سجلات في `branches` (مرتبطة بـ `companies.id`).
- **Modules/Users** (موجود): جدول `users` يستقبل العمود الجديد.
- **Spatie\Permission** (موجود): لإضافة الصلاحيات الجديدة.
- **Inertia.js** (موجود): للـ SPA.
- **Vue 3 + Tailwind 4** (موجود): للواجهة.

### 22.2 مطلوب لـ
- (مستقبلي) وحدة تقارير HR التي قد تُصفّي الموظفين حسب التبعية.
- (مستقبلي) وحدة الرواتب التي قد تربط الرواتب بالتبعية.
- (مستقبلي) وحدة Mobile التي قد تعرض "المطار الحالي" للموظف.

---

## 23. المخاطر والتخفيف (Risks & Mitigation)

| المخاطرة | الاحتمال | الأثر | التخفيف |
|---------|---------|------|--------|
| **R-1**: تعارض تسمية "Subordinations" مع "subordinates" (الموظفون التابعون لمدرب) | متوسط | متوسط | توضيح في التوثيق: Subordination = التبعية الإدارية/الجغرافية، Subordinates = الموظفون تحت إدارة موظف آخر. النمذجة واضحة. |
| **R-2**: تشغيل `BranchesDatabaseSeeder` قبل `CompaniesDatabaseSeeder` يُسبب فشل FK | منخفض | عالي | ترتيب صريح في `DatabaseSeeder` الجذر + حماية في الـ seeder (يتوقف عن تسجيل warning بدلاً من throw). |
| **R-3**: تكرار سجلات بسبب عدم استخدام `updateOrCreate` | متوسط | متوسط | كل الـ seeders تستخدم `updateOrCreate` على مفاتيح منطقية (`code`, `company_code`, `[company_id, branch_code]`). |
| **R-4**: حذف تبعية بكثرة يُسبب فقد ربط لموظفين | منخفض | متوسط | FK `SET NULL` (لا cascade) + رسالة تحذير واضحة في واجهة الحذف. |
| **R-5**: نسيان إضافة الصلاحيات الجديدة لدور `super-admin` | منخفض | عالي | ربط تلقائي في seeder الصلاحيات + اختبار route middleware. |
| **R-6**: ازدحام الـ Sidebar بالروابط | منخفض | منخفض | التبعية تظهر تحت قسم "الهيكل التنظيمي" مع باقي الروابط. |

---

## 24. ملاحظات تنفيذية (Implementation Notes)

- الـ migration `add_subordination_id_to_users_table` يجب أن يسبقها (لفهم الـ FK) إنشاء جدول `subordinations` — اعتمد على ترتيب timestamps للـ migrations.
- `UserRepository::$defaultWith` يجب أن يحدّث ليضم `'subordination'` لتفادي N+1.
- `User::SUPER_ADMIN_ID = 10000` لا يحتاج تبعية، لكن النظام لا يمنع تعيين واحدة له.
- عند تصفية الموظفين في `Users/Index.vue`، إضافة عمود تصفية بـ `FormSelect` للتبعية (اختياري UX لكن مستحسن).
- `Subordination::findByCode` مفيد للـ seeders (البحث بالكود بدلاً من الـ id لتجنّب الاعتماد على ترتيب الـ seed).
- يجب أن يلتزم التطبيق بـ **الفصل الصارم للطبقات** (Constitution § II & § XIV): Controller لا يحتوي منطق، Service يحتوي validation، Repository يحتوي استعلامات فقط.

---

## 25. خطة التنفيذ المقترحة (Implementation Plan Summary)

> هذا ملخص تسلسلي للمهام المتوقعة في `/speckit.tasks`. لا تفصيل تقني هنا — `/speckit.tasks` سيُولّدها.

1. إنشاء `Modules/Subordinations/` (الهيكل الكامل: Config, ServiceProvider, lang/, database/, app/).
2. إضافة migration لـ `subordinations` + migration لإضافة `subordination_id` إلى `users`.
3. تنفيذ `php artisan migrate`.
4. إنشاء `Subordination` Model + العلاقات + Scopes.
5. إنشاء `SubordinationRepository` + `SubordinationService`.
6. إنشاء `StoreSubordinationRequest` + `UpdateSubordinationRequest` + `SubordinationResource`.
7. إنشاء `SubordinationsController` + routes + تسجيل في `SubordinationsServiceProvider`.
8. تفعيل الوحدة في `modules_statuses.json`.
9. إضافة الصلاحيات (`view/create/edit/delete-subordinations`) عبر seeder.
10. تعديل `Modules/Users/Models/User`: `$fillable` + علاقة `subordination()`.
11. تعديل `Modules/Users/Http/Resources/UserResource`: `subordination_id` + `subordination`.
12. تعديل `Modules/Users/Http/Requests/{Store,Update}UserRequest`: قاعدة `subordination_id`.
13. تعديل `Modules/Users/Repositories/UserRepository`: eager load + filter.
14. تعديل `Modules/Users/Http/Controllers/UsersController`: حقن `SubordinationService` + `formOptions()` يمرر `subordinations`.
15. تعديل `resources/js/Pages/Users/{Create,Edit,Index,Show}.vue`: إضافة `subordination` prop + FormSelect / عمود / عرض.
16. إنشاء `resources/js/Pages/Subordinations/{Index,Create,Edit,Show}.vue`.
17. تحديث `resources/js/Components/layout/Sidebar.vue` لإضافة رابط "التبعية".
18. تحديث `Modules/Subordinations/lang/{ar,en}/subordinations.php`.
19. تحديث `Modules/Users/lang/{ar,en}/users.php` لإضافة مفاتيح `subordination` و `select_subordination` و `subordination_id_exists`.
20. تعديل `Modules/Companies/database/seeders/CompaniesDatabaseSeeder` لإضافة مطار حلب الدولي.
21. إنشاء `Modules/Subordinations/database/seeders/SubordinationsDatabaseSeeder`.
22. تعديل `Modules/Branches/database/seeders/BranchesDatabaseSeeder` لإضافة الطيران المدني والخطوط الجوية السورية.
23. تحديث `database/seeders/DatabaseSeeder.php` الجذر للترتيب الصحيح.
24. تشغيل `php artisan migrate:fresh --seed` للتحقق.
25. تشغيل `php artisan pint` + `php artisan test`.

---

## 26. Done When (متى تعتبر الميزة منتهية)

- [ ] كل البنود في § 18 (Acceptance Criteria) محققة.
- [ ] كل المعايير في § 19 (Success Criteria) محققة ومُقاسة.
- [ ] لا تحذيرات أو أخطاء في `php artisan pint`.
- [ ] لا أخطاء في `php artisan test`.
- [ ] لا استعلامات N+1 (تحقّق يدوي أو عبر `debugbar`).
- [ ] كل النصوص مترجمة عربي/إنجليزي في `lang/`.
- [ ] الـ Sidebar محدّث والرابط يعمل.
- [ ] التوثيق محدّث في `AGENTS.md` لإضافة وحدة Subordinations.

---

*آخر تحديث: 2026-07-20*
*الإصدار: 1.0.0*
