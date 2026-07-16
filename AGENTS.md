# AGENTS.md - AI Agent Guidelines for HRM Project

## 🚀 Quick Start

**New AI agent? Read these files in order:**
```
1. ONBOARDING.md                    ← Complete onboarding guide
2. .specify/memory/constitution.md  ← Project laws (HIGHEST authority)
3. specs/architecture.md            ← Project structure
4. specs/{module}/spec.md           ← Module you're working on
5. mistral.ai/DESIGN.md             ← Canonical design system (Mistral-inspired)
```

---

## 🎨 Design System (Mistral-Inspired)

**Canonical reference:** `mistral.ai\DESIGN.md` (single source of truth).

**CSS tokens:** All design tokens are defined in `resources\css\app.css` under Tailwind 4 `@theme` directive with the prefix `--color-mistral-*`, `--spacing-*`, `--radius-*`, etc. Auto-generate as Tailwind utilities (`bg-mistral-primary`, `text-mistral-ink`).

**Vue components:** All shared UI components live in `resources\js\Components\ui\` and `resources\js\Components\layout\`. Always use these — never write raw `<input>`, `<table>`, or `<button class="btn-*">` in pages.

**Key rules:**
- Buttons: `<Button variant="primary|secondary|cream|dark|on-cream|link|danger|ghost|icon">`
- Cards: `<Card variant="base|feature|cream|cream-soft|feature-product|stat">`
- Form fields: `<FormInput>`, `<FormSelect>`, `<FormTextarea>`, `<FormCheckbox>`, `<FormRadio>`, `<FormSwitch>`, `<FormDatepicker>`
- Brand signature: `<SunsetStripeBand />` (in `layout/`) — appears at the bottom of every page
- Colors: use `mistral-primary` (orange #fa520f) for CTAs and active states
- RTL: every component accepts a `dir` prop and uses logical properties (`ms-`, `me-`, `ps-`, `pe-`)

**Full spec:** `specs/003-mistral-design-system\spec.md`

---

## 📌 Project Overview

| Item | Details |
|------|---------|
| **Project** | HRM (Human Resource Management) |
| **Framework** | Laravel 13 |
| **Language** | PHP 8.3+ |
| **Architecture** | Modular (nwidart/laravel-modules) |
| **Database** | MySQL (prod) / SQLite (dev) |
| **UI** | Blade + Tailwind CSS 4.3 |
| **Language** | Arabic (primary), English (secondary) |
| **RTL** | Yes |
| **Modules** | 13 |

---

## 🏗️ Architecture Pattern

```
Controller → Service → Repository → Model → Database
```

**⚠️ NEVER skip layers!**

### Layer Rules
| Layer | Responsibility | ⛔ Forbidden |
|-------|---------------|-------------|
| **Controller** | HTTP + Inertia::render() + redirect | No business logic, no DB queries |
| **Service** | Business logic, validation, cache | No `app()`/`resolve()` - use DI |
| **Repository** | Eloquent queries, filtering, pagination | No business logic |
| **Model** | Relationships, scopes, attributes | No HTTP-related code |
| **FormRequest** | Validation rules + messages | No DB queries |
| **Resource** | Data transformation for frontend | No business logic |

### ❌ WRONG
```php
class CompaniesController extends Controller
{
    public function store(Request $request)
    {
        $company = Company::create($request->all()); // WRONG!
    }
}
```

### ✅ CORRECT
```php
class CompaniesController extends Controller
{
    public function store(Request $request)
    {
        $this->companyService->createCompany($request->all()); // RIGHT!
    }
}
```

---

## 📁 Project Structure

```
hrm/
├── AGENTS.md                         ← This file
├── ONBOARDING.md                     ← Complete onboarding guide
├── .specify/memory/constitution.md   ← Project constitution
├── specs/                            ← Documentation
│   ├── architecture.md
│   ├── database.md
│   ├── routes.md
│   ├── code-examples.md
│   ├── instructions.md
│   ├── quick-reference.md
│   └── {module}/spec.md
├── Modules/                          ← Code (13 modules)
│   ├── Companies/
│   ├── Branches/
│   ├── Departments/
│   ├── Positions/
│   ├── Grades/
│   ├── Shifts/
│   ├── Users/
│   ├── Attendance/
│   ├── FingerprintDevices/
│   ├── Holidays/
│   ├── Vacations/
│   ├── Settings/
│   └── Zones/
├── app/                              ← Core app (auth, dashboard)
├── config/                           ← Configuration
├── routes/                           ← Main routes
├── database/                         ← Global migrations
└── resources/                        ← Views, CSS, JS
```

---

## 🧩 Module Structure

Each module follows this pattern:
```
Modules/{Name}/
├── app/
│   ├── Http/Controllers/    ← HTTP handlers (THIN)
│   ├── Models/              ← Eloquent models
│   ├── Services/            ← Business logic
│   ├── Repositories/        ← Database queries
│   └── Providers/           ← Service providers
├── database/migrations/     ← Database changes
├── resources/views/         ← Blade templates
└── routes/web.php           ← Module routes
```

---

## 🏷️ File Naming Conventions

| Type | Convention | Example |
|------|-----------|---------|
| Models | Singular | Company, Branch |
| Services | Singular | CompanyService |
| Controllers | Plural | CompaniesController |
| Repositories | Singular | CompanyRepository |
| Migrations | snake_case | create_companies_table |
| Views | kebab-case | company-index |
| **Vue Pages** | **PascalCase** | **Index.vue, Create.vue** |
| **Vue Components** | **PascalCase** | **DataTable.vue, FormInput.vue** |
| **Composables** | **camelCase** | **useTranslations.js, useFilters.js** |

---

## 🔐 Permissions System

### Permission Format
```
{action}-{module}
```

### Available Permissions
```
view-companies       # View companies
create-companies     # Create companies
edit-companies       # Edit companies
delete-companies     # Delete companies
```

### Usage in Code
```php
// Controller
if (!auth()->user()->hasPermissionTo('create-companies')) {
    abort(403);
}

// Blade
@if(auth()->user()->hasPermissionTo('create-companies'))
    <a href="{{ route('companies.create') }}">Create</a>
@endif

// Route
Route::middleware('permission:create-companies')->group(function () {
    // ...
});
```

---

## 🌐 Bilingual Support

### Translation Helper
```php
// In PHP
__('companies.company_created_successfully')

// In Blade
{{ __('companies.name') }}

// With placeholder
__('companies.welcome_user', ['name' => $user->name])
```

### Language Switch
```php
Route::get('/language/{locale}', [LanguageController::class, 'switchLanguage']);
```

---

## 📋 Available Commands

### Development
```bash
composer dev                    # Run everything
php artisan serve               # HTTP server only
npm run dev                     # Vite only
php artisan queue:listen        # Queue only
```

### Code Quality
```bash
php artisan pint                # Format code
php artisan test                # Run tests
```

### Cache
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### ZKTeco
```bash
php artisan zkteco:service start
php artisan zkteco:service stop
php artisan zkteco:service status
```

---

## 🚫 Forbidden Actions

| Action | Why |
|--------|------|
| Delete data without migration | Data loss |
| Commit secrets/keys | Security risk |
| Skip validation | Data integrity |
| Use DB::raw() without prepared statements | SQL injection |
| Add libraries without checking | Dependency bloat |
| Use Model directly in Controller | Architecture violation |
| **Write `<table>` manually** | **Use `<DataTable />` component** |
| **Write `<input>` without wrapper** | **Use `<FormInput />` component** |
| **Build custom modals** | **Use `<FormModal />` component** |
| **N+1 queries** | **Always use `with()` or `load()`** |
| **Skip eager loading** | **Performance violation** |
| **Use `app()`/`resolve()` in Services** | **Must use Dependency Injection** |
| **Put logic in Controllers** | **Services only** |
| **Build same UI twice** | **Reuse shared components** |

---

## ✅ Required Actions

| Action | When |
|--------|------|
| Read constitution.md | Before ANY change |
| Follow modular architecture | Always |
| Write PHPDoc | Public methods |
| Run php artisan pint | Before commit |
| Run php artisan test | After changes |

---

## 🔄 Workflow for New Features

1. **Read** this document + constitution.md
2. **Read** relevant module spec
3. **Create** feature branch: `feat/001-feature-name`
4. **Implement** following architecture
5. **Run** pint for formatting
6. **Run** tests
7. **Create** PR

---

## 🐛 Common Issues & Solutions

### Migration Failed
```bash
php artisan migrate:refresh
php artisan db:seed
```

### Cache Issues
```bash
php artisan config:clear
php artisan cache:clear
```

### Queue Not Working
```bash
php artisan queue:restart
```

### ZKTeco Connection
```bash
php artisan zkteco:service status
netstat -tlnp | grep 4370
```

---

## 📖 Where to Find Information

| Need | File |
|------|------|
| Complete onboarding | ONBOARDING.md |
| Project rules | .specify/memory/constitution.md |
| Database schema | specs/database.md |
| API endpoints | specs/routes.md |
| Code examples | specs/code-examples.md |
| How to work | specs/instructions.md |
| Module details | specs/{module}/spec.md |

---

## 💡 Tips for Success

1. **Always read before writing** - Understand first
2. **Follow existing patterns** - Don't reinvent
3. **Keep it simple** - No over-engineering
4. **Test everything** - Never skip tests
5. **Document complex logic** - PHPDoc for public methods

---

## 🆘 Getting Help

| Problem | Solution |
|---------|----------|
| Don't understand a module | Read specs/{module}/spec.md |
| Don't know the pattern | Read specs/code-examples.md |
| Don't know the route | Read specs/routes.md |
| Don't know the schema | Read specs/database.md |
| Don't know the rules | Read .specify/memory/constitution.md |

---

## 🎯 Summary

1. **Read** ONBOARDING.md first
2. **Read** constitution.md
3. **Understand** the architecture
4. **Follow** the patterns
5. **Test** everything
6. **Document** your work

**Welcome to the team! 🚀**

---

*Last updated: 2026-07-08*
*Version: 1.0.0*
