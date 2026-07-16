# دستور مشروع HRM - Human Resource Management
# Project Constitution - HRM System

**تاريخ الإنشاء:** 2026-07-08
**آخر تحديث:** 2026-07-13
**الإصدار:** 3.0.0
**الحالة:** نشط

---

## المادة I: المبادئ العامة

### 1.1 الغرض من النظام
نظام إدارة موارد بشرية (HRM) مبني بـ Laravel 13 لإدارة شركات متعددة مع ربط كامل بين الهيكل التنظيمي وأجهزة البصمة ونظام الحضور.

### 1.2 المبادئ الأساسية
- **البساطة أولاً:** البدء بالحل البسيط ثم التعقيد عند الحاجة فقط
- **اتباع أنماط Laravel:** اتباع أفضل ممارسات Laravel و أنماط التصميم
- **التوثيق المستمر:** كل كود جديد يجب أن يكون موثقاً
- **الاختبار:** كل ميزة جديدة يجب أن يكون لها اختبار

---

## المادة II: بنية الوحدات (Modular Architecture)

### 2.1 البنية الأساسية
```
Module/
├── app/
│   ├── Http/Controllers/
│   ├── Models/
│   ├── Services/
│   ├── Repositories/
│   └── Providers/
├── config/
├── database/migrations/
├── resources/views/
└── routes/
```

### 2.2 تدفق البيانات (Data Flow)
```
Controller → Service → Repository → Model
```

### 2.3 قواعد البنية
- **كل وحدة** يجب أن تتبع نمط: Controller → Service → Repository → Model
- **لا يُسمح** بالتفاعل المباشر مع Model في Controller (مثل Zones حالياً - يجب إصلاحه)
- **Validation** يجب أن يكون في Service layer لا في Controller
- **Relationships** يجب أن تكون في Model لا في Service

### 2.4 الوحدات الموجودة (22 وحدة)

#### الوحدات الأساسية (Core - 14 وحدة)
1. **Companies** - إدارة الشركات
2. **Branches** - إدارة الفروع
3. **Departments** - إدارة الأقسام
4. **Positions** - إدارة المواقع الوظيفية
5. **Grades** - إدارة الدرجات
6. **Shifts** - إدارة المناوبات
7. **ShiftRotation** - تناوب المناوبات
8. **Users** - إدارة المستخدمين/الموظفين ← **الوحدة المركزية**
9. **Attendance** - نظام الحضور والانصراف ← **الأكثر تعقيداً (11 Service, 7 Controller)**
10. **FingerprintDevices** - إدارة أجهزة البصمة ZKTeco
11. **Holidays** - إدارة العطل
12. **Vacations** - إدارة الإجازات
13. **Settings** - الإعدادات العامة
14. **Zones** - إدارة المناطق

#### الوحدات الإضافية (8 وحدات)
15. **Payroll** - الرواتب والأجور
16. **Visitor** - إدارة الزوار
17. **Meeting** - إدارة الاجتماعات
18. **AccessControl** - التحكم بالوصول
19. **Workflow** - محرك سير العمل
20. **Mobile** - API للتطبيق الجوال
21. **Sync** - مزامنة البيانات
22. **Fingerprints** - إدارة البصمات

---

## المادة III: التسمية والبنية

### 3.1 تسمية الملفات
| العنصر | التسمية | المثال |
|---|---|---|
| Models | مفرد | Company, Branch, Department |
| Services | مفرد | CompanyService, BranchService |
| Controllers | جمع | CompaniesController, BranchesController |
| Repositories | مفرد | CompanyRepository, BranchRepository |
| Migrations | snake_case | create_companies_table |
| Views | kebab-case | company-index, branch-create |

### 3.2 الفضاءات (Namespaces)
```
Modules\{ModuleName}\Models\{ModelName}
Modules\{ModuleName}\Services\{ServiceName}
Modules\{ModuleName}\Repositories\{RepositoryName}
Modules\{ModuleName}\Http\Controllers\{ControllerName}
```

### 3.3 أنماط التسمية للجداول
| الجدول | المثال |
|---|---|
| Companies | companies |
| Branches | branches |
| Pivot Tables | user_shifts, user_zone |
| Settings | settings |

---

## المادة IV: قاعدة البيانات

### 4.1 قواعد قاعدة البيانات
- **SQLite** للتطوير المحلي
- **PostgreSQL** للإنتاج (النظام الأصلي يستخدم PostgreSQL)
- **MySQL 8.0+** كبديل للإنتاج
- **لا نحذف** جداول بدون ترحيل (migration)
- **Foreign Keys** مع ON DELETE CASCADE أو SET NULL حسب الحالة
- **100+ جدول** عبر 22 وحدة

### 4.2 تسمية الجداول
| الوحدة | البادئة | مثال |
|--------|---------|------|
| الهيكل التنظيمي | `personnel_` | personnel_employee |
| الحضور | `att_` | att_attshift |
| أجهزة البصمة | `iclock_` | iclock_terminal |
| الرواتب | `payroll_` | payroll_salarystructure |
| سير العمل | `workflow_` | workflow_workflowengine |
| الزوار | `visitor_` | visitor_visitor |
| الاجتماعات | `meeting_` | meeting_meetingroom |
| التحكم بالوصول | `acc_` | acc_accgroups |
| الجوال | `mobile_` | mobile_gpslocation |
| المزامنة | `sync_` | sync_employee |
| الإعدادات | `base_` | base_systemsetting |
| الصلاحيات | `auth_` | auth_permission |

### 4.3 الفهارس
- فهارس الأداء للبحث المتكرر
- Unique constraints حيثما يلزم
- Composite indexes للبحث المعقد

### 4.4 المحتوى الافتراضي
- المستخدم النظامي رقم **10000** يجب أن يكون مستبعداً من جميع العلاقات
- الحالة الافتراضية للمستخدمين: **active**
- الحالة الافتراضية للشركات: **active**

---

## المادة V: الأمان

### 5.1 الصلاحيات
- استخدام **Spatie Permission** للأدوار والصلاحيات
- جميع المسارات العامة يجب أن تكون محمية بـ auth middleware
- الصلاحيات معرّفة في `config/permissions.php`

### 5.2 حماية البيانات
- لا تسجيل **secrets** أو **keys** في الكود
- لا تسجيل **passwords** في logs
- استخدام **Hash::make** لكلمة المرور
- **CSRF** protection على جميع النماذج

### 5.3 Validation
```php
// في Service layer
public function createCompany(array $data): Company
{
    $validated = $this->validateCompanyData($data);
    return $this->repository->create($validated);
}

// لا في Controller
public function store(Request $request)
{
    $company = $this->service->createCompany($request->all());
    // ...
}
```

### 5.4 middleware المخصصة
- `device.access` - للوصول للأجهزة
- `device.connection` - لاختبار الاتصال (rate limiting)
- `device.type.access` - لأنواع الأجهزة

---

## المادة VI: الأداء والسرعة (Performance)

### 6.1 Backend Performance - إلزامي
#### 6.1.1 N+1 Query Prevention
```php
// ✅ صحيح - Eager Loading
User::with('company', 'branch', 'department')->paginate(20);

// ❌ خطأ - N+1
$users = User::all(); // كل user يعمل query إضافي
```

#### 6.1.2 Query Optimization
- **Select only needed columns**: `User::select('id', 'name', 'email')` بدلاً من `User::all()`
- **Use chunks for large datasets**: `User::chunk(100, fn($users) => ...)`
- **Avoid N+1 in loops**: لا تستدعي DB داخل foreach أبداً
- **Use `when()` for conditional queries**: `->when($search, fn($q) => $q->where('name', 'like', "%$search%"))`
- **Use `whereIn` بدلاً من loop of `where`**: جمع الشروط في استعلام واحد

#### 6.1.3 Caching Strategy
| البيانات | مدة التخزين | متى يُمسح |
|----------|-------------|-----------|
| Settings | 1 hour | بعد تعديل أي setting |
| Permissions | 24 hours | بعد تعديل صلاحية |
| Companies/Branches tree | 1 hour | بعد إضافة/تعديل شركة |
| Attendance stats | 5 minutes | عند تسجيل حضور جديد |
| User shifts | 12 hours | عند تعديل مناوبة |
| Dashboard stats | 10 minutes | لا يُمسح تلقائياً (وقت فقط) |

```php
// ✅ استخدام Cache مع Tagging
Cache::tags(['settings'])->remember('app_settings', 3600, fn() => 
    Setting::pluck('value', 'key')
);

// ❌ استعلام مباشر دون cache
$settings = Setting::pluck('value', 'key'); // استعلام كل مرة
```

#### 6.1.4 Indexing Rules
- **كل** `foreign_key` يجب أن يكون indexed
- **كل** حقل يستخدم في `WHERE` أو `ORDER BY` بكثرة يجب أن يكون indexed
- **Composite indexes** للاستعلامات المعقدة: `$table->index(['company_id', 'branch_id'])`
- **Full-text indexes** للبحث في النصوص الطويلة (الاسم, الوصف)

#### 6.1.5 Queue for Heavy Operations
المعاملات التالية يجب أن تكون في **Queue**:
- معالجة بصمات الحضور الجماعي
- إرسال إشعارات البريد الإلكتروني
- تقارير PDF/Excel الثقيلة
- مزامنة البيانات مع أجهزة البصمة
- تصدير واستيراد البيانات

### 6.2 Frontend Performance - إلزامي
#### 6.2.1 Lazy Loading
```vue
// ✅ صحيح - تحميل المكونات فقط عند الحاجة
const HeavyChart = defineAsyncComponent(() => import('./HeavyChart.vue'));

// ✅ صفحات Inertia تُحمّل lazily تلقائياً
const Page = defineAsyncComponent(() => 
    page(`./Pages/${resolvedPage}.vue`)
);
```

#### 6.2.2 Component Memoization
- استخدام `computed` لكل قيمة مشتقة (لا تحسب في كل re-render)
- استخدام `v-memo` للقوائم الثابتة الكبيرة
- تجنب `watch` غير الضروري
- استخدام `shallowRef` للبيانات الكبيرة التي لا تحتاج reactivity عميق

```vue
// ✅ صحيح - computed + شروط تحميل
const filteredList = computed(() => 
    props.items.filter(item => item.active)
);

// ✅ استخدام v-memo للجداول الكبيرة
<tr v-for="item in items" :key="item.id" v-memo="[item.updated_at]">
```

#### 6.2.3 Bundle Size Optimization
- **لا** import كامل مكتبة - استخدم tree-shaking
- **فصل** المكونات الثقيلة (charts, maps) إلى chunks منفصلة
- **Font Awesome** - import فقط الأيقونات المستخدمة
- **Tailwind** - purge للملفات غير المستخدمة

#### 6.2.4 Data Fetching
- **لا** تجلب البيانات أكثر من مرة - استخدام Inertia props
- **Pagination** للقوائم الكبيرة (أبداً لا تجلب 10000 سجل)
- **Debounce** للبحث (300ms)
- **Throttle** للأحداث المتكررة (resize, scroll)

### 6.3 Database Performance
- **SQLite** للتطوير فقط
- **MySQL 8.0+** للإنتاج مع:
  - `innodb_buffer_pool_size` = 70% من RAM
  - `query_cache_type = 0` (لا حاجة له مع InnoDB)
  - `max_connections` مناسب لعدد المستخدمين
- Connection pooling عبر Laravel config

### 6.4 Monitoring
- تسجيل الاستعلامات البطيئة (>100ms) في log
- مراقبة استخدام الذاكرة للـ Queue jobs
- مراقبة وقت استجابة الـ API (Inertia requests)

---

## المادة VII: مكونات الواجهة (Reusable Components)

### 7.1 التقنيات
- **Vue 3** (Composition API) - إطار العمل الأمامي
- **Inertia.js** - bridge بين Laravel و Vue (SPA)
- **Tailwind CSS 4.3** - التنسيق
- **Vite** - البناء
- **Font Awesome 6** - الأيقونات

### 7.2 ⚠️ قانون المكونات القابلة لإعادة الاستخدام (إلزامي)
**لا يجوز أبداً** بناء نفس العنصر UI من الصفر أكثر من مرة. يجب استخدام المكونات المشتركة في كل مكان.

#### المكونات الإلزامية (Shared Components)

| المكون | الاستخدام | لا يُسمح ببناء بديل |
|--------|-----------|---------------------|
| **`<DataTable />`** | جميع الجداول (sort, search, pagination, RTL) | كتابة `<table>` يدويًا |
| **`<FormInput />`** | جميع حقول الإدخال النصية | `<input>` مباشر بدون غلاف |
| **`<FormSelect />`** | جميع القوائم المنسدلة | `<select>` مباشر |
| **`<FormDatepicker />`** | جميع حقول التاريخ | `<input type="date">` مباشر |
| **`<FormTextarea />`** | جميع حقول النصوص الطويلة | `<textarea>` مباشر |
| **`<FormModal />`** | جميع النوافذ المنبثقة | كتابة modal من الصفر |
| **`<ConfirmDialog />`** | تأكيد الحذف/الإجراءات | `confirm()` أو modal مخصص |
| **`<PageHeader />`** | عنوان الصفحة + أزرار الإجراءات | كتابة header يدويًا |
| **`<StatCard />`** | بطاقات الإحصائيات في Dashboard | `<div>` مع تنسيق يدوي |
| **`<Badge />`** | حالة العناصر (نشط/غير نشط) | `<span>` يدوي |
| **`<Pagination />`** | ترقيم الصفحات (RTL) | كتابة pagination يدويًا |
| **`<SearchInput />`** | البحث في الجداول | `<input>` منفصل |
| **`<LoadingSpinner />`** | التحميل والانتظار | كتابة spinner يدويًا |
| **`<EmptyState />`** | عند عدم وجود بيانات | `<p>لا توجد بيانات</p>` |
| **`<Alert />`** | رسائل نجاح/خطأ/تحذير | `<div class="alert">` يدوي |
| **`<Breadcrumb />`** | مسار التنقل (RTL) | كتابة breadcrumb يدويًا |
| **`<Tabs />`** | تبويبات داخل الصفحة | `<div>` مع أزرار يدوي |
| **`<Sidebar />`** | القائمة الجانبية (ثابت - RTL) | كتابة sidebar في كل layout |
| **`<Navbar />`** | الشريط العلوي | كتابة navbar يدويًا |
| **`<FormGroup />`** | مجموعة حقل + label + validation error | تكرار هيكل label/input/error |

#### هيكل المكونات المشتركة
```
resources/js/Components/
├── ui/                              ← مكونات UI أساسية
│   ├── DataTable.vue                ← جدول مع sort/search/pagination
│   ├── FormInput.vue                ← حقل إدخال مع label + error
│   ├── FormSelect.vue               ← قائمة منسدلة مع label + error
│   ├── FormDatepicker.vue           ← منتقي تاريخ
│   ├── FormTextarea.vue             ← حقل نص طويل
│   ├── FormModal.vue                ← نافذة منبثقة
│   ├── FormGroup.vue                ← مجموعة حقل كاملة
│   ├── ConfirmDialog.vue            ← تأكيد حذف/إجراء
│   ├── PageHeader.vue               ← عنوان الصفحة
│   ├── Badge.vue                    ← حالة (نشط/غير نشط)
│   ├── Pagination.vue               ← ترقيم RTL
│   ├── SearchInput.vue              ← بحث
│   ├── LoadingSpinner.vue           ← تحميل
│   ├── EmptyState.vue               ← لا توجد بيانات
│   ├── Alert.vue                    ← رسائل
│   ├── Breadcrumb.vue               ← مسار التنقل
│   └── Tabs.vue                     ← تبويبات
├── layout/
│   ├── AppLayout.vue                ← التخطيط الرئيسي
│   ├── Sidebar.vue                  ← القائمة الجانبية
│   └── Navbar.vue                   ← الشريط العلوي
└── LanguageSwitcher.vue             ← تبديل اللغة
```

#### مثال: استخدام DataTable (وليس `<table>` يدوي)
```vue
<!-- ✅ صحيح - استخدام المكون المشترك -->
<template>
  <DataTable :columns="columns" :data="companies" :filters="true" />
</template>

<!-- ❌ خطأ - كتابة الجدول يدويًا -->
<template>
  <table dir="rtl">
    <thead><tr><th>الاسم</th></tr></thead>
    <tbody>
      <tr v-for="c in companies"><td>{{ c.name }}</td></tr>
    </tbody>
  </table>
</template>
```

#### مثال: استخدام FormModal (وليس modal مخصص)
```vue
<!-- ✅ صحيح -->
<FormModal v-model="showModal" title="إضافة شركة">
  <FormInput v-model="form.name" label="الاسم" />
  <FormInput v-model="form.email" label="البريد" />
</FormModal>

<!-- ❌ خطأ - modal مخصص -->
<div v-if="showModal" class="fixed inset-0 bg-black/50">
  <div class="bg-white rounded-lg">...</div>
</div>
```

### 7.3 إضافة مكون جديد
- إذا وجدت أنك تكرر نفس النمط 3+ مرات → أنشئ مكوناً مشتركاً جديداً
- المكون الجديد يجب أن يدعم **RTL** افتراضياً
- المكون الجديد يجب أن يدعم **Customization** (slots, props)
- المكون الجديد يجب أن يُضاف إلى قائمة المكونات الإلزامية أعلاه
- المكون الجديد يجب أن يُسجّل في `resources/js/Components/ui/index.js` للتصدير المركزي

### 7.4 دعم اللغات و RTL في المكونات
- جميع المكونات يجب أن تحتوي على `dir="rtl"` افتراضياً
- استخدام `useTranslations()` composable للترجمة داخل المكونات
- الأيقونات تستخدم `rtl-flip` class لعكس الاتجاه في RTL
- المكونات ترث الاتجاه من `AppLayout.vue` عبر provide/inject

---

## المادة VIII: الاختبار

### 8.1 أنواع الاختبارات
- **Unit Tests** للخدمات
- **Feature Tests** للـ Controllers
- **Browser Tests** للواجهة (اختياري)

### 8.2 تشغيل الاختبارات
```bash
php artisan test
```

### 8.3 تنسيق الكود
```bash
php artisan pint
```

---

## المادة IX: التوثيق

### 9.1 PHPDoc
```php
/**
 * Create a new company.
 *
 * @param array $data Company data
 * @return Company
 * @throws ValidationException
 */
public function createCompany(array $data): Company
```

### 9.2 تعليقات
- تعليقات بالإنجليزية على الكود العام
- تعليقات بالعربية على الكود المعقد فقط
- لا تعليقات على الكود الواضح

### 9.3 مواصفات الميزات
- كل ميزة جديدة يجب أن يكون لها مواصفات في `specs/`
- استخدام تنسيق `spec.md` لتوثيق الميزات

---

## المادة X: البساطة (Anti-Over-Engineering)

### 10.1 قواعد البساطة
- **لا مكتبات** غير ضرورية
- **لا تعقيد** غير مبرر
- **لا future-proofing** - الحل البسيط أولاً
- **لا عزل زائد** - استخدام ميزات Framework مباشرة

### 10.2 التحقق من البساطة
قبل إضافة أي تعقيد، اسأل:
1. هل هذا ضروري الآن؟
2. هل هناك حل أبسط؟
3. هل هذا يcomplexify الكود دون فائدة؟

---

## المادة XI: سير العمل للميزات الجديدة

### 11.1 الخطوات الإلزامية
```
1. أنشئ المواصفات: /speckit.specify [وصف الميزة]
2. وضح النقاط: /speckit.clarify
3. أنشئ خطة: /speckit.plan [المتطلبات التقنية]
4. أنشئ مهام: /speckit.tasks
5. نفّذ: /speckit.implement
6. اختبر: php artisan test
7. نظّف: php artisan pint
```

### 11.2 فرع Git
```
feat/001-feature-name
fix/002-bug-fix
docs/003-documentation
```

---

## المادة XII: إصلاح الأخطاء الموجودة

### 12.1 أولويات الإصلاح
1. **Zones module** - نقل Model من `Entities/` إلى `Models/`
2. **Validation** - نقل الـ validation من Controllers إلى Services (خاص Companies, Branches)
3. **Positions** - إضافة Service و Repository (موجود Controller فقط)
4. **Zones** - إضافة Service و Repository (غير موجودة)
5. **Route naming** - توحيد تسمية المسارات لجميع الوحدات
6. **Auth middleware** - إضافة auth إلى جميع المسارات العامة
7. **توثيق الوحدات الإضافية** - AGENTS.md و specs تحتاج تحديث لـ 22 وحدة

### 12.2 قائمة الإصلاحات
- [ ] Zones: نقل Zone.php من Entities إلى Models
- [ ] Companies: نقل validation من Controller إلى Service
- [ ] Branches: نقل validation من Controller إلى Service
- [ ] Positions: إضافة Service و Repository
- [ ] Zones: إضافة Service و Repository
- [ ] جميع الوحدات: توحيد Route naming
- [ ] جميع المسارات: إضافة auth middleware
- [ ] توثيق جميع الوحدات الـ 22 في AGENTS.md
- [ ] تحديث specs لتشمل الوحدات الإضافية

---

## المادة XIII: التعديل على الدستور

### 13.1 عملية التعديل
- التعديلات تتطلب **توثيق المبرر**
- مراجعة وموافقة **المشرف**
- تقييم **التوافق مع الكود الموجود**

### 13.2 تاريخ التعديلات
| التاريخ | المادة | التعديل | المبرر |
|---|---|---|---|---|
| 2026-07-08 | جميعها | الإنشاء الأولي | تأسيس المشروع |
| 2026-07-13 | II, XII, XIII | تحديث لـ 22 وحدة + إصلاحات جديدة | توثيق جميع الوحدات الموجودة فعلياً + مشاكل إضافية |
| 2026-07-13 | VI, VII, XIV | إعادة هيكلة الأداء + SPA + المكونات المشتركة + قابلية التوسع | تحويل إلى SPA كامل + ضمان أفضل أداء وقابلية توسع |

---

---

## المادة XIV: قابلية التوسع والهندسة الاحترافية (Scalability & Architecture)

### 14.1 Backend Architecture - المبادئ الإلزامية

#### 14.1.1 Service Layer (Business Logic)
```php
// ✅ صحيح - Service يحتوي على كل المنطق
class CompanyService
{
    public function __construct(
        private CompanyRepository $repository,
        private CompanyValidationService $validation,
        private CompanyFilterService $filter
    ) {}

    public function createCompany(array $data): Company
    {
        $validated = $this->validation->validateCreate($data);
        $company = $this->repository->create($validated);
        Cache::tags(['companies'])->flush();
        return $company;
    }

    public function getAllCompanies(Request $request): LengthAwarePaginator
    {
        return $this->filter->applyFilters(
            $this->repository->query()->with('branches'),
            $request
        )->paginate($request->per_page ?? 20);
    }
}

// ❌ خطأ - منطق في Controller
class CompaniesController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([...]); // خطأ: validation في Controller
        Company::create($request->all()); // خطأ: DB مباشر
    }
}
```

#### 14.1.2 Repository Layer (Data Access)
- كل موديل يجب أن يكون له **Repository**
- Repository يتعامل مع **Eloquent فقط** - لا منطق أعمال
- Repository يعيد **Collection, Model, Paginator** - لا arrays خام
- Repository يدعم **Filtering/Sorting** عبر parameters

```php
class CompanyRepository
{
    public function query(): Builder
    {
        return Company::query();
    }

    public function create(array $data): Company
    {
        return Company::create($data);
    }

    public function update(Company $company, array $data): Company
    {
        $company->update($data);
        return $company->fresh();
    }

    public function delete(Company $company): bool
    {
        return $company->delete();
    }
}
```

#### 14.1.3 Controller Layer (HTTP - Thin)
```php
// ✅ Controller نحيف - فقط ربط HTTP بالـ Service
class CompaniesController extends Controller
{
    public function __construct(
        private CompanyService $companyService
    ) {}

    public function index()
    {
        $this->authorize('view-companies');
        return Inertia::render('Companies/Index', [
            'companies' => fn() => $this->companyService
                ->getAllCompanies(request())
                ->through(fn($c) => CompanyResource::make($c)),
        ]);
    }

    public function store(StoreCompanyRequest $request)
    {
        $this->authorize('create-companies');
        $company = $this->companyService
            ->createCompany($request->validated());
        return redirect()->route('companies.index')
            ->with('success', __('companies.created'));
    }
}
```

#### 14.1.4 Form Requests (Validation)
```php
// ✅ كل validation في FormRequest منفصل
class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('create-companies');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'unique:companies,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('validation.required', ['attribute' => __('companies.name')]),
            'email.unique' => __('validation.unique', ['attribute' => __('companies.email')]),
        ];
    }
}
```

#### 14.1.5 API Resources (Transformers)
```php
// ✅ Resource لتنسيق البيانات للواجهة
class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'is_active' => $this->is_active,
            'branches_count' => $this->whenCounted('branches'),
            'employees_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at->format('Y-m-d'),
        ];
    }
}
```

### 14.2 Frontend Architecture - المبادئ الإلزامية

#### 14.2.1 Composition API بشكل صارم
```vue
// ✅ Composition API + script setup
<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useTranslations } from '@/composables/useTranslations'
import DataTable from '@/Components/ui/DataTable.vue'

const { t } = useTranslations()
const page = usePage()

const props = defineProps({
    companies: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
})

// لا Options API في المكونات الجديدة
</script>
```

#### 14.2.2 Composables لكل منطق قابل لإعادة الاستخدام
```javascript
// composables/useFilters.js
export function useFilters(initialFilters = {}) {
    const filters = ref(initialFilters)
    const debouncedSearch = refDebounced(filters, 300)

    const applyFilters = () => {
        router.get(window.location.pathname, filters.value, {
            preserveState: true,
            preserveScroll: true,
        })
    }

    watch(debouncedSearch, applyFilters)

    return { filters, applyFilters }
}

// composables/useForm.js
export function useForm(initialValues = {}) {
    const form = ref({ ...initialValues })
    const errors = ref({})
    const processing = ref(false)

    const submit = async (routeName, method = 'post') => {
        processing.value = true
        errors.value = {}
        try {
            await router[method](route(routeName), form.value)
        } catch (e) {
            errors.value = e.response?.data?.errors || {}
        } finally {
            processing.value = false
        }
    }

    return { form, errors, processing, submit }
}
```

#### 14.2.3 Folder Structure للمشاهد (Pages)
```
resources/js/Pages/{Module}/
├── Partials/              ← أجزاء قابلة لإعادة الاستخدام داخل الوحدة
│   ├── CompanyForm.vue    ← form fields مستخدم في Create + Edit
│   ├── CompanyInfo.vue    ← عرض معلومات الشركة
│   └── CompanyStats.vue   ← إحصائيات
├── Index.vue              ← قائمة
├── Create.vue             ← إنشاء جديد
├── Edit.vue               ← تعديل
└── Show.vue               ← عرض (اختياري)
```

**القاعدة:** أي جزء من الصفحة يستخدم في أكثر من view → ضعه في `Partials/`

#### 14.2.4 Props Convention
- كل صفحة تستقبل **props معرفة** (لا `$page.props` مباشر)
- استخدام **lazy evaluation** للبيانات الثقيلة: `'companies' => fn() => ...`
- استخدام **TypeScript-like** naming (PascalCase للمكونات, camelCase للprops)

### 14.3 قواعد التوسع (Scalability Rules)

| القاعدة | الشرح |
|---------|-------|
| **Stateless Services** | لا تعتمد على حالة (state) داخل Service - كل طلب مستقل |
| **Dependency Injection** | كل الاعتماديات عبر Constructor - لا `app()` أو `resolve()` داخل الدوال |
| **Single Responsibility** | كل Class مسؤول عن شيء واحد فقط |
| **Interface Segregation** | لا تجبر Class على implement دوال لا يحتاجها |
| **Caching First** | قبل كتابة استعلام معقد، فكر في cache |
| **Lazy Loading** | كل شيء يُحمّل فقط عند الحاجة (في الباك والفرونت) |
| **Pagination** | كل قائمة مهما كانت صغيرة يجب أن تكون paginated |
| **Chunking** | المعاملات الكبيرة تستخدم chunk بدلاً من load كامل |
| **No Raw SQL** | استخدام Eloquent أينما أمكن (ما عدا التقارير المعقدة جداً) |
| **Events for Side Effects** | الإجراءات الجانبية (إرسال إيميل، تسجيل log) عبر Events |
| **Queue for Heavy Tasks** | أي مهمة تأخذ >2 ثانية → Queue |

### 14.4 أخطاء شائعة ممنوعة

```php
// ❌ ممنوع
Company::where(...)->get()->filter(...)->values(); // filter بعد get
$user->load('relations'); // في Blade/View (يجب أن يكون في Controller)
DB::raw("SELECT * FROM ..."); // بدون prepared statements
Cache::remember(...); // بدون tags
collect($array)->map(...); // تحويل array إلى collection بدون داعي

// ✅ المسموح
Company::where(...)->where(...)->paginate();
User::with('relations')->find($id);
DB::select("SELECT ... ?", [$param]); // prepared statement
Cache::tags(['group'])->remember(...);
array_map(fn($i) => ..., $array); // PHP native للـ arrays البسيطة
```

### 14.5 تصميم Database للتوسع
- **Normalization** حتى 3NF (Third Normal Form)
- **Denormalization** فقط للتقارير (performance)
- **Soft Deletes** لكل الجداول الرئيسية (companies, users, branches...)
- **Timestamps** (`created_at`, `updated_at`) في كل جدول
- **Polymorphic Relations** للمرفقات والملاحظات
- **UUID** كـ secondary identifier للـ API (اختياري)

### 14.6 معايير السرعة الإلزامية
| المعيار | الحد المسموح |
|---------|--------------|
| وقت تحميل الصفحة (Time to First Paint) | < 1.5 ثانية |
| وقت استجابة Inertia request | < 300ms |
| وقت استعلام DB | < 100ms (ما عدا التقارير) |
| حجم الـ JS bundle | < 200KB (initial load) |
| عدد استعلامات DB لكل صفحة | < 10 (مع eager loading) |
| وقت Queue job | < 30 ثانية |

---

## ملخص

هذا الدستور هو **القانون الأعلى** لكل عمل في مشروع HRM. كل وكيل ذكاء اصطناعي يجب أن يقرأ هذا الملف قبل أي تعديل. كل ميزة جديدة يجب أن تتبع هذا الدستور.

**آخر تحديث:** 2026-07-13
**الإصدار:** 3.0.0
