# 007 — Aleppo Airport + Subordinations — خطة التنفيذ التقنية

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-20
**الحالة:** جاهز للتنفيذ (`/speckit.tasks` التالي)
**المواصفة:** [spec.md](./spec.md)
**البحث:** [research.md](./research.md)
**نموذج البيانات:** [data-model.md](./data-model.md)
**العقود:** [contracts/](./contracts/)
**الاختبار:** [quickstart.md](./quickstart.md)

---

## 1. Technical Context (السياق التقني)

> كل حقل مملوء بقيمة محددة. لا يوجد `NEEDS CLARIFICATION` متبقٍّ.

| البند | القيمة | المبرر |
|------|--------|--------|
| **لغة الخلفية** | PHP 8.3+ | مطلوب من Constitution § 0 + AGENTS.md |
| **إطار الخلفية** | Laravel 13 + `nwidart/laravel-modules` | معمارية الوحدات مفروضة من Constitution § II |
| **لغة الواجهة** | Vue 3 (Composition API) | Constitution § VII.7.1 |
| **التواصل بين الطبقات** | Inertia.js v2 (SPA) | Constitution § VII + AGENTS.md |
| **تنسيق الواجهة** | Tailwind CSS 4.3 | Constitution § VII.7.1 + AGENTS.md |
| **ORM** | Eloquent | مشروع Laravel — لا بديل |
| **قاعدة البيانات** | SQLite (تطوير) / MySQL (إنتاج) | Constitution § IV.4.1 |
| **نظام الصلاحيات** | Spatie\Permission | Constitution § V.5.1 + AGENTS.md |
| **التحقق من البيانات** | Laravel FormRequest + Service-layer validation | Constitution § XIV.1.4 |
| **معمارية الطبقات** | Controller → Service → Repository → Model (إلزامي) | Constitution § II + § XIV |
| **الترجمة** | Laravel `__()` + ملفات `lang/ar` و `lang/en` لكل وحدة | AGENTS.md + Constitution § VII.7.4 |
| **اتجاه الواجهة** | RTL افتراضياً مع دعم LTR | Constitution § VII.7.4 |
| **حذف ناعم** | Laravel `SoftDeletes` | Constitution § XIV.5 |
| **Eager Loading** | `defaultWith` في كل Repository | Constitution § VI.6.1.1 (no N+1) |
| **اختبار الـ validation** | `php artisan tinker` + `quickstart.md` يدوي | research.md § D-7.1 |
| **تنسيق الكود** | `php artisan pint` | Constitution § VIII.8.3 |
| **Node / Build** | Vite + npm | Constitution § VII.7.1 |

**القرارات المعمارية الرئيسية:** راجع [research.md](./research.md) (16 قرار موثّق، 0 أسئلة مفتوحة).

---

## 2. Constitution Check (فحص الدستور)

> يُطبَّق فحص البوابة من Constitution § II + § V + § VI + § VII + § X + § XIV. كل بند مُقيَّم مع الدليل.

### Gate 1 — المعمارية الطبقية (§ II.2.3 + § XIV.1.1)

| القاعدة | الحالة | الدليل |
|---------|--------|--------|
| كل ميزة تتبع Controller → Service → Repository → Model | ✅ PASS | research § D-3.1 + contracts/http-and-ui.md § 1.1 |
| Service يحتوي المنطق التجاري + validation logic | ✅ PASS | spec § 7.1 (SubordinationService::validateSubordinationData) |
| Repository يحتوي استعلامات Eloquent فقط | ✅ PASS | spec § 7.2 (SubordinationRepository) |
| Controller رفيع (HTTP ↔ Service فقط) | ✅ PASS | spec § 8.1 + contracts § 6.x |
| FormRequest منفصل لكل action (Store/Update) | ✅ PASS | spec § 9.1, § 9.2 |
| API Resource لتنسيق البيانات | ✅ PASS | spec § 10.1 (SubordinationResource) |

### Gate 2 — الأمان والصلاحيات (§ V)

| القاعدة | الحالة | الدليل |
|---------|--------|--------|
| Spatie Permission للصلاحيات الجديدة | ✅ PASS | spec § 15 + research § D-5.1 |
| auth middleware على كل المسارات | ✅ PASS | contracts § 1.1 |
| permission middleware على كل action | ✅ PASS | contracts § 1.1 (4 middleware مختلفة) |
| لا secrets في الكود | ✅ PASS | لا توجد في الـ scope |
| CSRF على النماذج | ✅ PASS | Inertia يضيف CSRF تلقائياً |
| لا passwords في logs | ✅ PASS | لا logging في الـ scope |

### Gate 3 — الأداء (§ VI)

| القاعدة | الحالة | الدليل |
|---------|--------|--------|
| Eager loading لمنع N+1 | ✅ PASS | research § I-1 (subordination في UserRepository::$defaultWith) |
| Indexes على كل FK | ✅ PASS | data-model.md § 2 + § 3 (3 indexes) |
| Composite index للاستعلامات الشائعة | ✅ PASS | data-model.md § 2 (`status, deleted_at`) |
| `select only needed columns` | ✅ PASS | SubordinationOption في formOptions() (research § D-3.3) |
| لا DB داخل loop | ✅ PASS | Seeder uses `updateOrCreate` (loop-free) |
| Pagination على كل قائمة | ✅ PASS | spec § 7.1 (SubordinationService::getAllSubordinations) |
| لا `DB::raw()` بدون prepared | ✅ PASS | لا استعلامات raw في الـ scope |

### Gate 4 — الواجهة والمكونات (§ VII)

| القاعدة | الحالة | الدليل |
|---------|--------|--------|
| لا بناء UI من الصفر | ✅ PASS | spec § 13.5 — استخدام DataTable, FormInput, FormSelect, FormTextarea, FormSection, FormActions, ErrorSummary, PageHeader, Button, ConfirmDialog |
| `<table>` غير مسموح | ✅ PASS | DataTable فقط |
| `<input>` غير مسموح بدون FormInput | ✅ PASS | FormInput فقط |
| Modal مخصص ممنوع | ✅ PASS | FormModal / ConfirmDialog فقط |
| RTL افتراضي | ✅ PASS | spec § 13.5 |
| `useTranslations` composable | ✅ PASS | spec § 13.5 |
| تسجيل المكون الجديد في index.js | N/A | لا مكون جديد — استخدام الموجود فقط |

### Gate 5 — البساطة وعدم الإفراط (§ X)

| القاعدة | الحالة | الدليل |
|---------|--------|--------|
| لا مكتبات جديدة | ✅ PASS | لا composer/npm جديد |
| لا future-proofing | ✅ PASS | one-to-many فقط (research § D-1.3) |
| لا عزل زائد | ✅ PASS | استخدام FormRequest القياسي |
| لا تعقيد غير مبرر | ✅ PASS | FK مباشر بدلاً من polymorphic (research § D-1.2) |

### Gate 6 — قابلية التوسع (§ XIV)

| القاعدة | الحالة | الدليل |
|---------|--------|--------|
| Service stateless | ✅ PASS | spec § 7.1 — حقن Repository في الـ constructor |
| Dependency Injection | ✅ PASS | spec § 7.1 + spec § 8.2 (constructor injection) |
| Single Responsibility | ✅ PASS | كل class له غرض واحد |
| Interface Segregation | ✅ PASS | Resource/Request/Service منفصلين |
| Caching First | ⚠ DEFER | لا حاجة فعلية للـ cache (جداول صغيرة + بيانات مرجعية). قد يُضاف cache 24h على `getActiveSubordinations` في v1.1 إن ظهر bottleneck. |
| Lazy Loading (frontend) | ✅ PASS | Inertia pages تُحمّل lazily |
| Pagination | ✅ PASS | spec § 7.1 |
| Events for side effects | ✅ PASS | لا side effects حالياً (لا إيميلات/audit في v1) |
| Queue for heavy tasks | N/A | لا مهام ثقيلة في الـ scope |

**Constitution Check overall: ✅ PASS (all gates satisfied, one deferral documented).**

---

## 3. ملخص التغييرات (Summary of Changes)

### 3.1 قاعدة البيانات (Database)

**إنشاء جدول جديد:**

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

**تعديل جدول موجود (`users`):**

```sql
ALTER TABLE `users`
  ADD COLUMN `subordination_id` BIGINT UNSIGNED NULL AFTER `grade_id`,
  ADD CONSTRAINT `users_subordination_id_foreign`
    FOREIGN KEY (`subordination_id`) REFERENCES `subordinations` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  ADD INDEX `users_subordination_id_index` (`subordination_id`);
```

**الجداول غير المعدّلة هيكلياً (تعبئة بيانات فقط):** `companies`, `branches`.

### 3.2 النماذج (Models)

| النموذج | التغيير |
|---------|---------|
| `Modules\Subordinations\Models\Subordination` | **جديد** — HasFactory, SoftDeletes. العلاقات: `users()`. الأوسمة: `scopeActive`, `scopeOrdered`. الوصولات: `getDisplayNameAttribute`. طريقة ثابتة: `findByCode`. |
| `Modules\Users\Models\User` | **تعديل** — إضافة `subordination_id` إلى `$fillable` + علاقة `subordination(): BelongsTo<Subordination>`. |
| `Modules\Companies\Models\Company` | لا تغيير |
| `Modules\Branches\Models\Branch` | لا تغيير |

### 3.3 الخدمات (Services)

| الخدمة | التغيير |
|--------|---------|
| `Modules\Subordinations\Services\SubordinationService` | **جديد** — DI للـ SubordinationRepository. 8 طرق (CRUD + getActive + getByCode + validation). |
| `Modules\Users\Services\UserService` | **تعديل** — لا تغيير في التواقيع. `subordination_id` يُمرَّر عبر `$validated` (الـ fillable الجديد يتولّى الحفظ). |
| `Modules\Companies\Services\CompanyService` | لا تغيير |
| `Modules\Branches\Services\BranchService` | لا تغيير |

### 3.4 المستودعات (Repositories)

| المستودع | التغيير |
|---------|---------|
| `Modules\Subordinations\Repositories\SubordinationRepository` | **جديد** — query(), getAll(), findById, findByCode, getActive, create, update, delete, applyFilters. |
| `Modules\Users\Repositories\UserRepository` | **تعديل** — إضافة `'subordination'` إلى `$defaultWith` + دعم `subordination_id` filter في `applyFilters`. |
| `Modules\Companies\Repositories\CompanyRepository` | لا تغيير |
| `Modules\Branches\Repositories\BranchRepository` | لا تغيير |

### 3.5 المتحكمات (Controllers)

| المتحكم | التغيير |
|---------|---------|
| `Modules\Subordinations\Http\Controllers\SubordinationsController` | **جديد** — 7 actions (index, create, store, show, edit, update, destroy). كل action محمي بـ `$this->authorize()` + middleware. |
| `Modules\Users\Http\Controllers\UsersController` | **تعديل** — حقن `SubordinationService` في الـ constructor. إضافة `subordinations` إلى `formOptions()`. |
| `Modules\Companies\Http\Controllers\CompaniesController` | لا تغيير |
| `Modules\Branches\Http\Controllers\BranchesController` | لا تغيير |

### 3.6 FormRequests

| الملف | التغيير |
|------|---------|
| `StoreSubordinationRequest` | **جديد** — قواعد `code`, `name_ar`, `name_en`, `description`, `status`, `sort_order`. authorize = `create-subordinations`. |
| `UpdateSubordinationRequest` | **جديد** — نفس Store مع `Rule::unique` يتجاهل المعرّف الحالي. authorize = `edit-subordinations`. |
| `StoreUserRequest` | **تعديل** — إضافة قاعدة `subordination_id`. رسالة `subordination_id_exists`. |
| `UpdateUserRequest` | **تعديل** — نفس الإضافة. |

### 3.7 Resources

| الملف | التغيير |
|------|---------|
| `SubordinationResource` | **جديد** — يُعيد الحقول الـ 10 (id, code, name_ar, name_en, display_name, description, status, sort_order, created_at, updated_at). |
| `UserResource` | **تعديل** — إضافة `subordination_id` (scalar) + `subordination` (whenLoaded object). |

### 3.8 المسارات (Routes)

| الملف | التغيير |
|------|---------|
| `Modules/Subordinations/routes/web.php` | **جديد** — 7 routes resource-style مع 4 permission middleware مختلفة. |
| `Modules/Users/routes/web.php` | لا تغيير |
| `database/seeders/DatabaseSeeder.php` (الجذر) | **تعديل** — إضافة 3 seeder calls بالترتيب الصحيح. |

### 3.9 Seeders

| الـ Seeder | التغيير |
|----------|---------|
| `Modules/Companies/database/seeders/CompaniesDatabaseSeeder` | **تعديل** — `updateOrCreate` لـ `AIRPORT-ALEPPO`. |
| `Modules/Branches/database/seeders/BranchesDatabaseSeeder` | **تعديل** — `updateOrCreate` لـ `CIVIL-AVIATION` و `SYRIAN-AIR` (مرتبطان بمطار حلب). |
| `Modules/Subordinations/database/seeders/SubordinationsDatabaseSeeder` | **جديد** — `updateOrCreate` لـ `ALEPPO-AIRPORT` و `LATTAKIA-AIRPORT`. |

### 3.10 الواجهة الأمامية (Vue)

| الصفحة | التغيير |
|--------|---------|
| `resources/js/Pages/Subordinations/Index.vue` | **جديد** — DataTable + بحث + pagination + actions. |
| `resources/js/Pages/Subordinations/Create.vue` | **جديد** — نموذج إنشاء. |
| `resources/js/Pages/Subordinations/Edit.vue` | **جديد** — نموذج تعديل. |
| `resources/js/Pages/Subordinations/Show.vue` | **جديد** — عرض تفاصيل. |
| `resources/js/Pages/Users/Create.vue` | **تعديل** — prop `subordinations` + FormSelect + `subordination_id` في الـ form. |
| `resources/js/Pages/Users/Edit.vue` | **تعديل** — نفس الإضافة + تهيئة من `props.user.subordination_id`. |
| `resources/js/Pages/Users/Index.vue` | **تعديل** — عمود "التبعية" + filter FormSelect. |
| `resources/js/Pages/Users/Show.vue` | **تعديل** — عرض `user.subordination?.name_ar`. |
| `resources/js/Components/layout/Sidebar.vue` | **تعديل** — رابط "التبعية" جديد. |

### 3.11 ملفات الترجمة

| الملف | التغيير |
|------|---------|
| `Modules/Subordinations/lang/ar/subordinations.php` | **جديد** — 30+ مفتاح. |
| `Modules/Subordinations/lang/en/subordinations.php` | **جديد** — نظير بالإنجليزية. |
| `Modules/Users/lang/ar/users.php` | **تعديل** — 3 مفاتيح جديدة. |
| `Modules/Users/lang/en/users.php` | **تعديل** — نظير بالإنجليزية. |

### 3.12 ServiceProvider

| الملف | التغيير |
|------|---------|
| `Modules/Subordinations/Providers/SubordinationsServiceProvider` | **جديد** — تسجيل routes, migrations, translations, views. |
| `Modules/Subordinations/Providers/RouteServiceProvider` | **جديد** (مع الـ module). |
| `modules_statuses.json` | **تعديل** — `"Subordinations": true`. |

### 3.13 الصلاحيات

| المفتاح | النوع |
|--------|------|
| `view-subordinations` | جديد — مرتبط بدور `super-admin` |
| `create-subordinations` | جديد — مرتبط بدور `super-admin` |
| `edit-subordinations` | جديد — مرتبط بدور `super-admin` |
| `delete-subordinations` | جديد — مرتبط بدور `super-admin` |

**التسجيل:** عبر `PermissionSeeder` (أو seeder منفصل) + التأكد من منحها تلقائياً لـ `super-admin`.

---

## 4. ترتيب التنفيذ (Implementation Order)

> نفس ترتيب Constitution § XI.11.1 + يضمن FK dependencies.

1. **البيئة:**
   1.1. إنشاء `Modules/Subordinations/` skeleton (composer.json, module.json, app.json, providers, config).
   1.2. تفعيل الوحدة في `modules_statuses.json`.

2. **قاعدة البيانات:**
   2.1. Migration: `create_subordinations_table`.
   2.2. Migration: `add_subordination_id_to_users_table`.
   2.3. تشغيل `php artisan migrate` والتحقق من الـ schema.

3. **النماذج:**
   3.1. `Subordination` Model.
   3.2. تعديل `User` Model (fillable + relation).
   3.3. تعديل `UserResource`.

4. **المستودعات:**
   4.1. `SubordinationRepository`.
   4.2. تعديل `UserRepository` (eager-load + filter).

5. **الخدمات:**
   5.1. `SubordinationService`.
   5.2. تعديل `UserService` (تمرير `subordination_id`).

6. **FormRequests و Resources:**
   6.1. `StoreSubordinationRequest`.
   6.2. `UpdateSubordinationRequest`.
   6.3. `SubordinationResource`.
   6.4. تعديل `StoreUserRequest` و `UpdateUserRequest`.

7. **المتحكمات:**
   7.1. `SubordinationsController`.
   7.2. تعديل `UsersController` (حقن + formOptions).

8. **المسارات والـ Providers:**
   8.1. `Subordinations/routes/web.php`.
   8.2. `SubordinationsServiceProvider` + `RouteServiceProvider`.

9. **الصلاحيات:**
   9.1. Seeder لإنشاء 4 صلاحيات + منحها لـ `super-admin`.
   9.2. تشغيل الـ seeder والتحقق.

10. **الـ Seeders:**
    10.1. `SubordinationsDatabaseSeeder`.
    10.2. تعديل `CompaniesDatabaseSeeder`.
    10.3. تعديل `BranchesDatabaseSeeder`.
    10.4. تعديل `DatabaseSeeder` الجذر.
    10.5. تشغيل `migrate:fresh --seed` والتحقق من الـ counts (1/2/2).

11. **الواجهة (Vue):**
    11.1. `Subordinations/Index.vue`.
    11.2. `Subordinations/Create.vue`.
    11.3. `Subordinations/Edit.vue`.
    11.4. `Subordinations/Show.vue`.
    11.5. تعديل `Users/Create.vue` (إضافة FormSelect + watch اختياري).
    11.6. تعديل `Users/Edit.vue` (نفس الشيء + تهيئة).
    11.7. تعديل `Users/Index.vue` (عمود + filter).
    11.8. تعديل `Users/Show.vue` (عرض).
    11.9. تعديل `Sidebar.vue` (رابط جديد).
    11.10. `npm run build` للتحقق من البناء.

12. **الترجمة:**
    12.1. `Modules/Subordinations/lang/ar/subordinations.php`.
    12.2. `Modules/Subordinations/lang/en/subordinations.php`.
    12.3. تعديل `Modules/Users/lang/{ar,en}/users.php`.

13. **التحقق النهائي:**
    13.1. `php artisan pint`.
    13.2. `php artisan test`.
    13.3. تنفيذ `quickstart.md` كاملاً.
    13.4. تحديث `AGENTS.md` لإضافة وحدة Subordinations.

---

## 5. الاعتبارات (Considerations)

### 5.1 الأمان (Security)
- ✅ كل routes محمية بـ `auth` + `permission:` middleware.
- ✅ FormRequest::authorize() يطبّق check ثانٍ.
- ✅ Controller::authorize() check ثالث.
- ✅ لا secrets في الكود.
- ✅ FK `SET NULL` آمن عند حذف تبعية.

### 5.2 الأداء (Performance)
- ✅ Eager loading على `subordination` في `UserRepository::$defaultWith`.
- ✅ Composite index `(status, deleted_at)` على `subordinations`.
- ✅ Index على `users.subordination_id`.
- ✅ SubordinationOption (شكل خفيف) في `formOptions()` لتقليل payload.
- ✅ Soft deletes + indexes تجعل استعلام "active + non-deleted" سريعاً.
- ⚠ **DEFER:** إضافة `Cache::tags(['subordinations'])->remember(...)` على `getActiveSubordinations` (24h TTL). يضاف إذا ظهر bottleneck في profiling. الجدول صغير جداً (< 10 صفوف متوقعة) — الإضافة قد لا تكون ضرورية في v1.

### 5.3 قابلية التوسع (Scalability)
- ✅ كل Service stateless ويعتمد على DI.
- ✅ Repository pattern يسمح بتبديل Eloquent بـ QueryBuilder أو raw SQL لاحقاً.
- ✅ Seeders idempotent تسمح بإعادة التشغيل بأمان.
- ✅ الكود المنطقي (`code`) مستقر عبر البيئات.

### 5.4 التراجع (Rollback)
- ✅ Migrations كاملة (forward + reverse).
- ✅ Seeder idempotent = يمكن إفراغ الجدول والعودة.
- ✅ إضافة العمود nullable = لا تأثير على بيانات قديمة.
- ✅ لا حذف لجداول موجودة.

### 5.5 UX
- ✅ استخدام المكونات المشتركة (Consistency).
- ✅ RTL أصلي (مكونات تدعمه).
- ✅ i18n عبر `useTranslations`.
- ✅ رسائل خطأ واضحة (validation messages).
- ✅ Placeholder "— اختر التبعية —" واضح.
- ✅ Auto-suggest (اختياري) عند اختيار شركة مطار حلب.

### 5.6 الاختبار
- ⚠ لا اختبارات PHP جديدة في v1 (research § D-7.1).
- ✅ `quickstart.md` يوفر 30+ خطوة تحقق يدوية قابلة للتنفيذ.
- ✅ كل خطوة لها معيار نجاح محدد.

### 5.7 التوثيق
- ✅ تحديث `AGENTS.md` لإضافة وحدة `Subordinations` لقائمة الوحدات الـ 13.
- ✅ تحديث Constitution § II.2.4 (14 وحدة → 15 وحدة).
- ✅ Spec.md + research.md + data-model.md + contracts/ + quickstart.md = 5 وثائق فنية.

---

## 6. قائمة المهام المتوقعة (Expected Task List Outline)

> هذه معاينة لما سيولّده `/speckit.tasks`. لا تفصيل هنا.

| # | المهمة | الحجم | الملفات الرئيسية |
|---|--------|-------|------------------|
| T-01 | إنشاء skeleton لوحدة Subordinations | M | `Modules/Subordinations/{composer.json, module.json, app.json, providers/}` |
| T-02 | Migration: create_subordinations_table | S | `database/migrations/*_create_subordinations_table.php` |
| T-03 | Migration: add_subordination_id_to_users_table | S | `database/migrations/*_add_subordination_id_to_users_table.php` |
| T-04 | Subordination Model + relations + scopes + accessors | S | `Modules/Subordinations/app/Models/Subordination.php` |
| T-05 | SubordinationRepository | S | `Modules/Subordinations/app/Repositories/SubordinationRepository.php` |
| T-06 | SubordinationService | M | `Modules/Subordinations/app/Services/SubordinationService.php` |
| T-07 | SubordinationResource | S | `Modules/Subordinations/app/Http/Resources/SubordinationResource.php` |
| T-08 | StoreSubordinationRequest + UpdateSubordinationRequest | S | `Modules/Subordinations/app/Http/Requests/*` |
| T-09 | SubordinationsController | M | `Modules/Subordinations/app/Http/Controllers/SubordinationsController.php` |
| T-10 | Subordinations routes | S | `Modules/Subordinations/routes/web.php` |
| T-11 | SubordinationsServiceProvider + RouteServiceProvider | S | `Modules/Subordinations/app/Providers/*` |
| T-12 | modules_statuses.json: إضافة Subordinations | XS | `modules_statuses.json` |
| T-13 | Subordinations lang/ar + lang/en | S | `Modules/Subordinations/lang/{ar,en}/subordinations.php` |
| T-14 | تعديل User Model: fillable + relation subordination | XS | `Modules/Users/app/Models/User.php` |
| T-15 | تعديل UserResource: subordination_id + subordination | XS | `Modules/Users/app/Http/Resources/UserResource.php` |
| T-16 | تعديل UserRepository: defaultWith + applyFilters | S | `Modules/Users/app/Repositories/UserRepository.php` |
| T-17 | تعديل StoreUserRequest + UpdateUserRequest: subordination_id rule | XS | `Modules/Users/app/Http/Requests/*` |
| T-18 | تعديل UsersController: حقن + formOptions | S | `Modules/Users/app/Http/Controllers/UsersController.php` |
| T-19 | تعديل Users/lang/{ar,en}/users.php | XS | `Modules/Users/lang/{ar,en}/users.php` |
| T-20 | تعديل Users/Create.vue: FormSelect + watch | S | `resources/js/Pages/Users/Create.vue` |
| T-21 | تعديل Users/Edit.vue: FormSelect + initial value | S | `resources/js/Pages/Users/Edit.vue` |
| T-22 | تعديل Users/Index.vue: عمود + filter | M | `resources/js/Pages/Users/Index.vue` |
| T-23 | تعديل Users/Show.vue: عرض | XS | `resources/js/Pages/Users/Show.vue` |
| T-24 | Subordinations/Index.vue (DataTable) | M | `resources/js/Pages/Subordinations/Index.vue` |
| T-25 | Subordinations/Create.vue | M | `resources/js/Pages/Subordinations/Create.vue` |
| T-26 | Subordinations/Edit.vue | M | `resources/js/Pages/Subordinations/Edit.vue` |
| T-27 | Subordinations/Show.vue | S | `resources/js/Pages/Subordinations/Show.vue` |
| T-28 | تعديل Sidebar.vue: رابط التبعية | XS | `resources/js/Components/layout/Sidebar.vue` |
| T-29 | Permission seeder: 4 صلاحيات + super-admin grant | S | `database/seeders/SubordinationPermissionsSeeder.php` (جديد) |
| T-30 | SubordinationsDatabaseSeeder | S | `Modules/Subordinations/database/seeders/SubordinationsDatabaseSeeder.php` |
| T-31 | تعديل CompaniesDatabaseSeeder: AIRPORT-ALEPPO | XS | `Modules/Companies/database/seeders/CompaniesDatabaseSeeder.php` |
| T-32 | تعديل BranchesDatabaseSeeder: CIVIL-AVIATION + SYRIAN-AIR | S | `Modules/Branches/database/seeders/BranchesDatabaseSeeder.php` |
| T-33 | تعديل DatabaseSeeder الجذر: ترتيب الاستدعاءات | XS | `database/seeders/DatabaseSeeder.php` |
| T-34 | تنفيذ migrate:fresh --seed والتحقق | S | (CLI step) |
| T-35 | php artisan pint | XS | (CLI step) |
| T-36 | تنفيذ quickstart.md | M | (manual) |
| T-37 | تحديث AGENTS.md | XS | `AGENTS.md` |
| T-38 | تحديث Constitution § II.2.4 | XS | `.specify/memory/constitution.md` |

**المجموع:** 38 مهمة (XS: 12, S: 14, M: 12) — تقدير إجمالي ~6-8 ساعات عمل.

---

## 7. معايير النجاح (Success Criteria — من spec § 19)

| ID | المعيار | كيف يُقاس |
|----|---------|-----------|
| SC-1 | جاهزية البيانات | `migrate:fresh --seed` → 1 شركة + 2 فرع + 2 تبعية |
| SC-2 | Idempotency | تشغيل seeders مرتين → نفس الأعداد |
| SC-3 | أداء تحميل /users | < 300ms (20 موظف) |
| SC-4 | لا N+1 | استعلامات DB على /users مع subordination ≤ 5 |
| SC-5 | FK SET NULL | حذف تبعية → users.subordination_id = NULL |
| SC-6 | RTL | كل UI يدعم RTL افتراضياً |
| SC-7 | i18n | مفاتيح ar/en متطابقة 100% |
| SC-8 | UX flow | 3 نقرات لإضافة موظف + تبعية |
| SC-9 | FK integrity | كل subordination_id يشير لسجل موجود |

---

## 8. Done When (متى تعتبر الخطة منتهية)

- [x] Technical Context مملوء بدون `NEEDS CLARIFICATION`.
- [x] Constitution Check يجتاز كل البوابات.
- [x] ملخص التغييرات يغطي كل الطبقات (DB → Frontend).
- [x] ترتيب التنفيذ يحترم الـ FK dependencies.
- [x] الاعتبارات تغطي الأمان + الأداء + قابلية التوسع + UX.
- [x] قائمة المهام المتوقعة مرجع لـ `/speckit.tasks`.
- [x] معايير النجاح مرجع للقياس.

**الحالة الحالية:** ✅ جاهز لـ `/speckit.tasks`.

---

*آخر تحديث: 2026-07-20*
*الإصدار: 1.0.0*
