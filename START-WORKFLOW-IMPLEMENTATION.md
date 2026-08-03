# تنفيذ منصة Enterprise Approval & Workflow

أنت مهندس Laravel Enterprise وDDD وQA. نفّذ هذه المهمة في مشروع `D:\hrm`.

## قواعد ملزمة

- اقرأ أولاً: `AGENTS.md` ثم `.specify/memory/constitution.md`.
- استخدم Inertia + Vue 3 فقط للواجهات الجديدة.
- لا تلمس الوحدات المحمية: Employees/Users، Vacations، Attendance، Departments، Roles & Permissions، Authentication إلا بالتمديد المتوافق.
- لا refactor أو إعادة تنسيق غير مرتبطة بالمهمة.
- حافظ على كل routes وأسمائها وAPI responses والأحداث والـqueue payloads الحالية.
- لا migrations مدمرة: additive فقط، وآمنة للإنتاج.
- استخدم SpecKit وأنشئ/حدّث: `specs/010-enterprise-workflow-platform/`.
- لا تستخدم Eloquent أو Laravel Facades أو Request أو Auth أو DB أو Cache داخل طبقة Domain.
- لا تربط Domain بأي مزود بنية تحتية؛ استخدم Contracts وAdapters.
- استخدم `apply_patch` فقط لتعديل الملفات.
- افحص `git status` أولاً واحفظ التعديلات المحلية غير المتعلقة بالمهمة كما هي.

## قرارات معتمدة

- المستأجر هو الشركة: `companies.id`.
- التفعيل تدريجي: الإجازات القديمة لا تتغير، والطلبات الجديدة تمر بالمحرك فقط عندما يكون Feature Flag مفعلاً وقالب منشور متاح.
- محرك القواعد DSL مقيدة وآمنة؛ يمنع PHP eval والتنفيذ الحر.
- `users.manager_id` هو مصدر التسلسل الإداري الأولي. غياب المدير يحوّل الطلب إلى `Waiting for Assignment`.
- القوالب مركزية أو خاصة بشركة؛ القالب المركزي read-only للشركات.
- كل تكامل مستقبلي يتم حصراً عبر Workflow SDK.

## المطلوب الآن: المرحلة 1 فقط

نفّذ المرحلة 1: التحليل، ADR، والتحقق المعماري فقط. لا تبدأ المرحلة 2.

1. أنشئ وثائق SpecKit ومخرجات المرحلة:
   - Compatibility Report.
   - Event Catalog.
   - ADRs.
   - ERD.
   - Sequence Diagrams.
   - Component Diagram.
   - Deployment Diagram.
   - API/SDK Contract Catalog.
   - Risk Register وخطة اختبارات.

2. وثّق حدود الطبقات المستهدفة:
   - `Modules/Workflow/app/Domain`
   - `Modules/Workflow/app/Application`
   - `Modules/Workflow/app/Infrastructure`
   - `Modules/Workflow/app/Interfaces`
   - `Modules/Workflow/app/Sdk`

3. وثّق توافق الإجازات الحالي:
   - لا تغيّر `user_vacation_requests` أو حالاتها الحالية.
   - لا تغيّر أحداث `VacationRequested` و`VacationApproved` و`VacationRejected` و`VacationCancelled`.
   - لا تغيّر مسارات `vacations.*`.
   - الحفاظ على تكامل Attendance بعد قرار الإجازة.

4. وثّق state machine الرسمية:
   `Draft`, `Pending`, `In Review`, `Waiting for Assignment`, `Returned`,
   `Approved`, `Rejected`, `Cancelled`, `Expired`.

5. وثّق العقود المستهدفة لـ:
   - Workflowable وProvider.
   - Command/Query boundaries.
   - Idempotency وoptimistic locking وdistributed lock.
   - Outbox وDLQ.
   - Expression evaluator.
   - Feature flags.
   - Audit/correlation/request/trace IDs.
   - Contract testing helpers.

6. شغّل Quality Gate غير المدمر:
   - build.
   - الاختبارات الحالية.
   - توثيق أي فشل موجود مسبقاً أو ناتج من تغييرات محلية، دون إصلاحات خارج النطاق.

## شرط التوقف

بعد إنهاء المرحلة 1، قدّم تقريراً واضحاً بالملفات المعدلة ونتائج الجودة والمخاطر، ثم توقّف تماماً واطلب إذناً صريحاً قبل المرحلة 2.
