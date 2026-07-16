# 🚀 HRM Build Kit - Spec Kit Format

## 📁 الهيكل (Spec Kit)

```
hrm-build-kit/
├── .specify/                          ← Spec Kit core
│   ├── memory/
│   │   └── constitution.md            ← دستور المشروع (القوانين الإلزامية)
│   └── templates/                     ← قوالب لإنشاء specs جديدة
│       ├── spec-template.md
│       ├── plan-template.md
│       └── tasks-template.md
├── specs/
│   └── 00-hrm-system/                 ← النظام الرئيسي
│       ├── spec.md          (84KB)    ← المواصفات الكاملة
│       ├── plan.md          (38KB)    ← خطة البناء
│       ├── tasks.md         (44KB)    ← 159 مهمة
│       └── DESIGN.md        (NEW!)    ← 🎨 نظام التصميم (ألوان، خطوط، مكونات، RTL)
├── AGENTS.md                          ← دليل AI Agent
├── .ai-prompt.md                      ← 🎯 كود Cursor (برومبت كامل)
├── START-HERE.txt                     ← 🎯 نسخة سريعة للصق مباشر
└── README.md                          ← هذا الملف
```

## 🏁 البداية

```bash
# 1. أنشئ المشروع
composer create-project laravel/laravel:^12.0 hrm
cd hrm

# 2. انسخ محتويات hrm-build-kit إلى جذر المشروع
xcopy C:\Users\pc\Desktop\hrm-build-kit\*.* . /E

# 3. افتح في Cursor
code .

# 4. الصق START-HERE.txt في Cursor → Ctrl+K
```

## 📋 أوامر Spec Kit

| الأمر | الوظيفة |
|-------|---------|
| `/speckit.specify [وصف]` | إنشاء مواصفات جديدة |
| `/speckit.clarify` | توضيح النقاط |
| `/speckit.plan [متطلبات]` | إنشاء خطة |
| `/speckit.tasks` | تقسيم إلى مهام |
| `/speckit.implement` | تنفيذ المهمة الحالية |
