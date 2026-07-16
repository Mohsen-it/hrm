# نظام التصميم المُستوحى من Mistral AI - المواصفات

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-15
**الحالة:** مسودة
**المرجع:** `mistral.ai\DESIGN.md`

---

## نظرة عامة

نوحّد الهوية البصرية لنظام HRM باعتماد نظام تصميم مُستوحى من لغة Mistral AI البصرية، مع الحفاظ الكامل على دعم اللغة العربية واتجاه RTL. الملف المرجعي الكامل موجود في `mistral.ai\DESIGN.md` ويحتوي على جميع الرموز (tokens) للألوان والطباعة والمسافات والأشعار والمكونات. هذه المواصفة تُطبّق هذا النظام على **كل صفحات النظام** (لوحات التحكم، القوائم، النماذج، التفاصيل) و**كل عناصر الواجهة** (النماذج، الجداول، البطاقات، الأزرار، مربعات النصوص، خانات الاختيار، والألوان).

### الهدف

استبدال نظام التصميم الحالي المستوحى من Superhuman (الموجود في `DESIGN.md` على جذر المشروع) بنظام Mistral AI الجديد كمرجع وحيد لكل المكونات المشتركة، مع ضمان:

- الاتساق البصري عبر جميع وحدات النظام الـ 22.
- التطابق مع معايير الوصول (WCAG AA كحد أدنى).
- دعم RTL الأصلي في كل مكون.
- قابلية الصيانة عبر رموز تصميم مركزية (Design Tokens).

### غير مُشمول في هذه المواصفة

- منطق الأعمال (Business Logic) داخل الوحدات — يُترك للوحدات نفسها.
- تغيير أسماء الوحدات أو هيكل الصلاحيات.
- محتوى صفحات الـ Marketing الخارجية (موقع الشركة) — يتبع فريق التسويق.

## قصص المستخدمين

- [ ] كـ **موظف أو مسؤول HR**، أستطيع تعرّف الواجهة بسرعة لأنها تستخدم نفس الهوية البصرية في كل الصفحات.
- [ ] كـ **مستخدم جديد**، أستطيع قراءة النصوص والأزرار بسهولة بفضل تباين الألوان وحجم الخط.
- [ ] كـ **مستخدم على جهاز لوحي أو هاتف**، أحصل على تجربة متسقة ومتجاوبة دون فقدان السياق.
- [ ] كـ **مستخدم يستخدم اللغة العربية**، تعمل الواجهة بشكل طبيعي في اتجاه RTL دون كسر بصري.
- [ ] كـ **مطوّر**، أستطيع بناء أي صفحة جديدة باستخدام نفس مكونات UI المشتركة دون الحاجة لإعادة كتابة CSS.

## المتطلبات الوظيفية

### 1. اعتماد رموز التصميم (Design Tokens)

يجب اعتماد جميع الرموز المُعرّفة في `mistral.ai\DESIGN.md` كمرجع وحيد، ولا يجوز إنشاء رموز بديلة.

#### 1.1 رموز الألوان (Colors)

| مجموعة الرموز | الاستخدام | مثال |
|--------------|----------|------|
| `{colors.primary}` (`#fa520f`) | الأزرار الأساسية، الحالات النشطة، روابط التنقل | زر "حفظ"، تبويب نشط |
| `{colors.primary-deep}` (`#cc3a05`) | حالة الضغط للأزرار الأساسية | زر مضغوط |
| `{colors.cream}` (`#fff8e0`) | أسطح بطاقات المعاينة، لوحات النماذج | بطاقة مميزة، نموذج الاتصال |
| `{colors.cream-deeper}` (`#fff0c2`) | خلفية الـ badges الكريمية | شارة "جديد" |
| `{colors.beige-deep}` (`#e6d5a8`) | حدود 1px على الأسطح الكريمية | حد بطاقة كريمية |
| `{colors.canvas}` (`#ffffff`) | خلفية الصفحات والبطاقات البيضاء | خلفية عامة |
| `{colors.surface}` (`#fafafa`) | خلفية ثانوية هادئة | صف بديل في الجدول |
| `{colors.hairline}` (`#e5e5e5`) | فواصل 1px عامة | فاصل بين صفوف الجدول |
| `{colors.hairline-soft}` (`#ededed`) | فواصل أهدأ | حد بطاقة خفيف |
| `{colors.hairline-strong}` (`#c7c7c7`) | حدود 1px للحقول | حد input غير نشط |
| `{colors.ink}` (`#1f1f1f`) | النص الأساسي، العناوين | عنوان الصفحة |
| `{colors.steel}` (`#6a6a6a`) | نص ثالثوي، تسميات | تسمية حقل |
| `{colors.muted}` (`#a8a8a8`) | نص معطّل، placeholder | نص placeholder |
| `{colors.on-primary}` (`#ffffff`) | نص فوق `{colors.primary}` | نص زر "حفظ" |
| `{colors.on-dark}` (`#ffffff`) | نص على الأسطح الداكنة | نص على كود |
| `{colors.link}` (`#fa520f`) | لون الروابط المضمّنة | رابط في فقرة |

**القاعدة:** `{colors.primary}` محصور في CTAs الأساسية، الحالات النشطة، وروابط التنقل فقط. لا يجوز استخدامه كخلفية لبطاقات عادية أو لون نص ثانوي.

#### 1.2 رموز الطباعة (Typography)

| الرمز | الاستخدام | مثال |
|------|----------|------|
| `{typography.heading-1}` | عناوين الصفحات الرئيسية | "إدارة الشركات" |
| `{typography.heading-2}` | عناوين الأقسام الفرعية | "معلومات أساسية" |
| `{typography.heading-3}` | عناوين البطاقات | اسم شركة في بطاقة |
| `{typography.heading-4}` | عناوين فرعية للبطاقات | عنوان فرعي |
| `{typography.body-md}` | النص الأساسي | فقرة وصف |
| `{typography.body-sm}` | نص ثانوي | نص مساعد |
| `{typography.caption}` | نص توضيحي صغير | نص تحت حقل |
| `{typography.button-md}` | تسميات الأزرار | نص "إضافة جديد" |
| `{typography.micro}` | microcopy صغير | نص في footer |

**القاعدة:** PP Editorial Old (display) للعناوين التحريرية الكبيرة فقط (صفحات الهبوط)، Inter (UI) لكل شيء آخر. لا يجوز استخدام خطوط بديلة.

#### 1.3 رموز المسافات (Spacing)

| الرمز | القيمة | الاستخدام |
|------|--------|----------|
| `{spacing.xxs}` | 4px | هوامش داخلية صغيرة جداً |
| `{spacing.xs}` | 8px | فجوة بين عناصر مرتبطة |
| `{spacing.sm}` | 12px | حشو داخلي للحقول |
| `{spacing.md}` | 16px | فجوة افتراضية، حشو البطاقات الصغيرة |
| `{spacing.lg}` | 20px | حشو البطاقات المتوسطة |
| `{spacing.xl}` | 24px | حشو البطاقات الكبيرة |
| `{spacing.xxl}` | 32px | حشو لوحات النماذج |
| `{spacing.section}` | 64px | فجوة بين أقسام الصفحة |
| `{spacing.section-lg}` | 96px | فجوة كبيرة (لوحات التحكم) |

**القاعدة:** تُستخدم رموز المسافات حصرياً. لا يجوز استخدام قيم عشوائية (مثل `mt-[13px]`).

#### 1.4 رموز الزوايا (Rounded)

| الرمز | القيمة | الاستخدام |
|------|--------|----------|
| `{rounded.xs}` | 4px | شارات صغيرة، عناصر تحكم دقيقة |
| `{rounded.sm}` | 6px | شارات الخصم، واجهة مضغوطة |
| `{rounded.md}` | 8px | **الأزرار، الحقول، البحث** |
| `{rounded.lg}` | 12px | **البطاقات، المودالات، اللوحات** |
| `{rounded.xl}` | 16px | لوحات المعاينة الكبيرة |
| `{rounded.xxl}` | 20px | بطاقات تأكيد/تركيز |
| `{rounded.full}` | 9999px | **الـ badges فقط** (محصورة) |

**القاعدة الحرجة:** لا يجوز استخدام أزرار بحواف دائرية كاملة (pill). `{rounded.md}` للأزرار، `{rounded.lg}` للبطاقات، `{rounded.full}` للشارات فقط.

### 2. قواعد المكونات الإلزامية

تُطبّق القواعد التالية على كل المكونات المشتركة (الموجودة في `resources/js/Components/ui/`). كل مكون يجب أن يستخدم رموز التصميم من `mistral.ai\DESIGN.md`.

#### 2.1 الأزرار (Buttons)

| المكون | الوصف | المواصفات |
|--------|-------|-----------|
| **Button Primary** | الإجراء الرئيسي في الصفحة | `{colors.primary}` خلفية، `{colors.on-primary}` نص، `{rounded.md}`، `10px 20px` حشو |
| **Button Primary Pressed** | حالة الضغط | `{colors.primary-deep}` خلفية |
| **Button Primary Disabled** | حالة التعطيل | `{colors.hairline}` خلفية، `{colors.muted}` نص |
| **Button Secondary** | إجراء ثانوي | شفاف، `{colors.ink}` نص، `1px solid {colors.hairline-strong}` حد |
| **Button Cream** | إجراء على سطح كريمي | `{colors.cream}` خلفية، `1px solid {colors.beige-deep}` حد |
| **Button Dark** | CTA على سطح كريمي | `{colors.ink}` خلفية، `{colors.on-dark}` نص |
| **Button On Cream** | زر أبيض على كريمي | `{colors.canvas}` خلفية، `1px solid {colors.beige-deep}` حد |
| **Button Link** | رابط نصي مضمّن | شفاف، `{colors.primary}` نص، `{typography.body-sm-medium}` |

**السلوك:**
- ارتفاع الزر 40–44px (متوافق مع WCAG AAA).
- لا hover state مرئي (افتراضي الصحافة فقط، كما في `mistral.ai\DESIGN.md`).
- النص عربي/إنجليزي قابل للقراءة في كلا الاتجاهين.

#### 2.2 البطاقات (Cards)

| المكون | الوصف | المواصفات |
|--------|-------|-----------|
| **Card Base** | بطاقة محتوى قياسية | `{colors.canvas}`، `{rounded.lg}`، `{spacing.xl}` حشو، `1px {colors.hairline-soft}` حد |
| **Card Feature** | بطاقة ميزات | `{colors.canvas}`، `{rounded.lg}`، `{spacing.xxl}` حشو |
| **Card Cream** | بطاقة كريمية | `{colors.cream}`، `{rounded.lg}`، `{spacing.xxl}` حشو، `1px {colors.beige-deep}` حد |
| **Card Cream Soft** | كريمي فاتح | `{colors.surface-cream-soft}`، `{rounded.lg}`، `{spacing.xxl}` |
| **Card Feature Product** | بطاقة منتج بارتفاع | ظلال `rgba(0,0,0,0.04) 0 4px 12px` |
| **Stat Card** | بطاقة إحصائية في Dashboard | `{rounded.lg}`، `{spacing.xl}`، رقم بـ `{typography.stat-display}` |

**السلوك:**
- كل البطاقات يجب أن تدعم RTL.
- لا ظلال على البطاقات العادية (Elevation 0 افتراضي).
- ظلال فقط لـ `card-feature-product` وmodals.

#### 2.3 حقول الإدخال (Text Inputs)

| المكون | الوصف | المواصفات |
|--------|-------|-----------|
| **Text Input** | حقل نصي | `{colors.canvas}` خلفية، `1px {colors.hairline-strong}` حد، `{rounded.md}`، `{spacing.sm} {spacing.md}` حشو، ارتفاع 44px |
| **Text Input Focused** | حقل نشط | الحد يتحول إلى `2px solid {colors.primary}` |
| **Text Input Error** | حقل بخطأ | الحد `{colors.primary-deep}`، نص خطأ بـ `{typography.caption}` `{colors.primary-deep}` |
| **Text Input Disabled** | حقل معطّل | `{colors.surface}` خلفية، `{colors.muted}` نص |
| **Text Area** | حقل نص طويل | مثل Text Input بدون حد للارتفاع، `{spacing.md}` حشو |
| **Search Input** | حقل بحث | مثل Text Input + أيقونة بحث على الجانب الأيمن (RTL) |

**السلوك:**
- النص placeholder بـ `{colors.muted}`.
- التركيز (focus) يُبرز الحد بـ `{colors.primary}` فوراً.
- رسائل الخطأ تظهر أسفل الحقل بمسافة `{spacing.xs}`.

#### 2.4 خانات الاختيار والقوائم المنسدلة (Checkboxes, Selects)

| المكون | الوصف | المواصفات |
|--------|-------|-----------|
| **Checkbox Unchecked** | مربع اختيار غير محدد | `1px {colors.hairline-strong}` حد، `{rounded.xs}` |
| **Checkbox Checked** | مربع اختيار محدد | `{colors.primary}` خلفية، علامة ✓ بيضاء، `{rounded.xs}` |
| **Checkbox Disabled** | معطّل | `{colors.surface}` خلفية، `{colors.muted}` علامة |
| **Radio Unchecked/Checked** | أزرار اختيار دائرية | مثل Checkbox لكن `{rounded.full}` |
| **Toggle Switch Off** | مفتاح تبديل معطّل | `{colors.hairline}` خلفية، `{rounded.full}` |
| **Toggle Switch On** | مفتاح تبديل نشط | `{colors.primary}` خلفية، `{rounded.full}` |
| **Form Select** | قائمة منسدلة | مثل Text Input + سهم لأسفل يسار (في LTR) أو يمين (في RTL) |
| **Form Select Open** | قائمة مفتوحة | خلفية `{colors.canvas}`، قائمة منسدلة بـ `{rounded.md}`، ظلال `rgba(0,0,0,0.12) 0 16px 48px -8px` |
| **Form Select Item Hover** | عنصر تحت المؤشر | `{colors.surface-cream-soft}` خلفية |
| **Form Select Item Selected** | عنصر محدد | `{typography.body-md-medium}`، علامة ✓ `{colors.primary}` يسار (RTL) |

**السلوك:**
- جميع خانات الاختيار يجب أن تكون قابلة للنقر بمساحة 44×44px (touch target).
- RTL: السهم في Select يكون على اليمين، علامة ✓ في Selected Item على اليسار.
- حالة التركيز تُبرز العنصر بـ `2px {colors.primary}` outline.

#### 2.5 الجداول (Tables)

| المكون | الوصف | المواصفات |
|--------|-------|-----------|
| **Table Container** | حاوية الجدول | `{colors.canvas}` خلفية، `{rounded.lg}`، `1px {colors.hairline-soft}` حد |
| **Table Header** | صف العناوين | `{colors.surface}` خلفية، `{typography.caption-bold}` نص |
| **Table Header Cell** | خلية عنوان | `{spacing.md}` حشو، `1px {colors.hairline}` حد سفلي |
| **Table Row** | صف بيانات | `{colors.canvas}` خلفية |
| **Table Row Alternate** | صف بديل (zebra) | `{colors.surface}` خلفية |
| **Table Row Hover** | صف تحت المؤشر | `{colors.surface-cream-soft}` خلفية (كريمي خفيف) |
| **Table Cell** | خلية بيانات | `{spacing.md}` حشو، `1px {colors.hairline-soft}` حد سفلي |
| **Table Action Cell** | خلية إجراءات | أيقونات `{colors.steel}` (تعديل/حذف) |
| **Table Empty State** | حالة فارغة | `{colors.muted}` نص، أيقونة 48×48 |

**السلوك:**
- الأعمدة قابلة للفرز (sort) بالنقر على العنوان.
- RTL: الأسهم (↑↓) لفرز الأعمدة على يسار النص.
- الإحصائيات (عدد السجلات) تظهر في Footer الجدول.

#### 2.6 النماذج (Forms)

| المكون | الوصف | المواصفات |
|--------|-------|-----------|
| **Form Group** | مجموعة حقل + label + خطأ | label أعلى الحقل، خطأ أسفل، فجوة `{spacing.md}` |
| **Form Group Horizontal** | حقل أفقي | label يمين (RTL)، حقل يسار (RTL)، فجوة `{spacing.md}` |
| **Form Section** | قسم في النموذج | عنوان `{typography.heading-2}`، حشو `{spacing.xl}` |
| **Form Panel** | لوحة نموذج كاملة | `{colors.cream}` خلفية، `{rounded.lg}`، `{spacing.xxl}` حشو، `1px {colors.beige-deep}` حد |
| **Form Actions** | أزرار النموذج | محاذاة لليسار (RTL) أو الوسط، فجوة `{spacing.md}` بين الأزرار |
| **Form Field Hint** | تلميح للحقل | `{typography.caption}`، `{colors.steel}` |
| **Required Indicator** | علامة حقل مطلوب | نجمة `*` `{colors.primary}` بعد النص |

**السلوك:**
- التحقق من الصحة (validation) يحدث على blur وsubmit.
- رسائل الخطأ بالعربية/الإنجليزية.
- الأخطاء تُلخّص أعلى النموذج (Alert) + تظهر تحت كل حقل.

#### 2.7 التابات والـ Badges

| المكون | الوصف | المواصفات |
|--------|-------|-----------|
| **Pill Tab** | تاب حبوب | `{colors.canvas}`، `{colors.steel}` نص، `{rounded.full}`، `{spacing.xs} {spacing.md}` حشو، `1px {colors.hairline}` حد |
| **Pill Tab Active** | تاب نشط | `{colors.ink}` خلفية، `{colors.on-dark}` نص |
| **Segmented Tab** | تاب مسطّح | شفاف، `{colors.steel}` نص، `{spacing.sm} {spacing.md}` حشو |
| **Segmented Tab Active** | تاب مسطّح نشط | `{colors.primary}` نص، `0 0 2px {colors.primary}` حد سفلي |
| **Badge Orange** | شارة برتقالية | `{colors.primary}`، `{colors.on-primary}` نص، `{rounded.full}`، `4px 10px` حشو |
| **Badge Cream** | شارة كريمية | `{colors.cream-deeper}`، `{colors.ink}` نص، `{rounded.full}` |
| **Badge Dark** | شارة داكنة | `{colors.ink}`، `{colors.on-dark}` نص، `{rounded.full}` |

#### 2.8 المودالات (Modals)

| المكون | الوصف | المواصفات |
|--------|-------|-----------|
| **Modal Backdrop** | خلفية داكنة | `rgba(0,0,0,0.5)` |
| **Modal Container** | حاوية المودال | `{colors.canvas}`، `{rounded.lg}`، `{spacing.xxl}` حشو، ظلال `rgba(0,0,0,0.12) 0 16px 48px -8px` |
| **Modal Header** | رأس المودال | `{typography.heading-3}`، حد سفلي `1px {colors.hairline-soft}` |
| **Modal Body** | جسم المودال | `{spacing.xl}` حشو |
| **Modal Footer** | تذييل المودال | محاذاة يسار (RTL)، أزرار `Button Secondary` + `Button Primary` |
| **Modal Close** | زر الإغلاق | أيقونة `×` في الزاوية المقابلة للـ direction |

**السلوك:**
- الإغلاق بـ `Esc` أو النقر خارج المودال.
- التركيز (focus trap) على أول حقل قابل للتركيز.
- لا يُغلق المودال أثناء عملية جارية (مع spinner).

#### 2.9 التنبيهات (Alerts)

| المكون | الوصف | المواصفات |
|--------|-------|-----------|
| **Alert Success** | نجاح | `{colors.cream}` خلفية، `1px {colors.beige-deep}` حد، أيقونة ✓ `{colors.ink}` |
| **Alert Info** | معلومة | `{colors.canvas}` خلفية، `1px {colors.hairline}` حد، أيقونة ⓘ `{colors.steel}` |
| **Alert Warning** | تحذير | `{colors.cream-deeper}` خلفية، أيقونة ⚠ |
| **Alert Error** | خطأ | `{colors.surface}` خلفية، `1px {colors.primary}` حد، أيقونة ✕ `{colors.primary}` |

**السلوك:**
- التنبيهات تظهر أعلى الصفحة أو أعلى النموذج.
- تختفي تلقائياً بعد 5 ثوانٍ (لـ success/info).
- أخطاء لا تختفي تلقائياً.

### 3. تطبيق النظام على أنواع الصفحات

#### 3.1 صفحة القائمة (Index)

- **Header الصفحة:** `{typography.heading-1}` + زر "إضافة جديد" (Button Primary).
- **شريط البحث:** Search Input (44px) + Filter Pills (Pill Tab).
- **الجدول:** DataTable بكل المواصفات في القسم 2.5.
- **Pagination:** في الأسفل مع `{typography.micro}`.
- **Empty State:** عند عدم وجود بيانات، أيقونة 64×64 + نص توجيهي + زر "إضافة أول سجل".

#### 3.2 صفحة الإنشاء/التعديل (Create/Edit)

- **Form Panel:** `card-cream` بنمط `contact-form-panel` يحتوي كل الحقول.
- **Form Sections:** مقسّمة بأقسام منطقية (مثال: "معلومات أساسية"، "العنوان"، "الإعدادات").
- **Form Actions:** في أسفل النموذج، Button Secondary (إلغاء) + Button Primary (حفظ).

#### 3.3 صفحة التفاصيل (Show)

- **Hero Card:** بطاقة معلومات أساسية (الاسم، الرقم، الحالة) بـ `card-base`.
- **Tab Navigation:** Pill Tab أو Segmented Tab للتنقل بين أقسام التفاصيل.
- **Stat Row:** `Stat Card` للإحصائيات الرئيسية.
- **Activity Timeline:** سجل الأحداث (إن وجد).

#### 3.4 لوحة التحكم (Dashboard)

- **Stat Row:** 4 بطاقات إحصائية في الأعلى.
- **Charts Area:** بطاقات Charts بـ `card-feature-product`.
- **Recent Activity:** جدول مختصر بأحدث العمليات.
- **Quick Actions:** Pill Tab أو مجموعة أزرار للإجراءات السريعة.

#### 3.5 صفحة تسجيل الدخول (Login)

- **Login Panel:** `contact-form-panel` في وسط الصفحة.
- **Logo:** في الأعلى.
- **Form:** Email + Password (Text Input) + "تذكرني" (Checkbox) + Button Primary.

### 4. التوقيع البصري (Brand Signature)

#### 4.1 شريط الغروب الختامي (Sunset Stripe Band)

- يجب أن يظهر في **أسفل كل صفحة لوحة تحكم وصفحات المصادقة**.
- تدرج لوني: `{colors.primary}` → `{colors.sunshine-700}` → `{colors.yellow-saturated}` → `{colors.cream}`.
- ارتفاع 4px، عرض 100% من الشاشة.
- هو العنصر الأكثر تميزاً في النظام البصري.

#### 4.2 الأيقونات (Icons)

- مكتبة الأيقونات: **Font Awesome 6** (المستخدمة بالفعل في المشروع).
- حجم افتراضي: 16px للأيقونات داخل النص، 20px للأيقونات داخل الأزرار، 24px للأيقونات في البطاقات.
- لون افتراضي: `{colors.ink}` للنشط، `{colors.steel}` للثانوي، `{colors.muted}` للمعطّل.

### 5. سلوكيات النظام

#### 5.1 دعم RTL

- كل المكونات ترث `dir="rtl"` من `AppLayout.vue`.
- المحاذاة: يمين افتراضياً للنصوص، يسار للأيقونات الفرعية.
- الأيقونات التي لها معنى اتجاهي (سهم، رجوع) تنعكس بـ `rtl-flip`.
- المخططات (Charts) تنعكس محاورها.

#### 5.2 الاستجابة (Responsive)

| نقطة الفصل | التغييرات |
|-----------|-----------|
| Mobile (< 768px) | الأعمدة تتكدس، الجدول يصبح بطاقة لكل سجل، المودال يأخذ الشاشة كاملة |
| Tablet (768–1023px) | تخطيط 2 أعمدة، الجدول يبقى جدول |
| Desktop (≥ 1024px) | التخطيط الكامل، الشريط الجانبي يظهر |

#### 5.3 حالات التفاعل (Interaction States)

- **Default:** المظهر الأساسي.
- **Focus:** outline `2px {colors.primary}` بـ `outline-offset: 2px`.
- **Pressed:** خلفية أعمق بـ 10%.
- **Disabled:** `{colors.muted}` نص، `{colors.surface}` خلفية، مؤشر `not-allowed`.
- **Loading:** spinner + تعطيل التفاعل.

## معايير النجاح

### معايير كمية

- **95%** على الأقل من عناصر UI في المشروع تستخدم المكونات المشتركة (DataTable, FormInput, Button, Card) — لا بناء يدوي.
- **0** قيم ألوان أو مسافات أو زوايا عشوائية في ملفات CSS (كلها رموز من `mistral.ai\DESIGN.md`).
- **0** استخدام لـ `pill buttons` (rounded-full) خارج الـ badges.
- زمن تحميل الصفحة الأولى (FCP) يبقى **< 1.5 ثانية** بعد التطبيق.

### معايير نوعية

- يحقق التصميم تباين ألوان يتوافق مع **WCAG AA** على جميع عناصر النص الأساسية (4.5:1 للعادي، 3:1 للكبير).
- يستخدم التصميم نفس البطاقات والأزرار والحقول في **كل الصفحات** دون استثناء.
- يقرأ المستخدم العربي الواجهة بشكل طبيعي في RTL دون أي كسر بصري.
- يحصل التصميم على درجة **4 من 5** أو أعلى في مراجعة فريق المنتج عند تطبيقه على 3 صفحات نموذجية (Companies Index، Employee Create، Dashboard).

### معايير قابلية الصيانة

- إضافة لون جديد يتطلب **< 5 دقائق** (تعديل ملف الرموز فقط).
- إضافة مكون جديد يتطلب **< 30 دقيقة** (إنشاء + توثيق + اختبار).
- حجم ملف `DESIGN.md` المرجعي **< 1000 سطر** (التوثيق الكامل في `mistral.ai\DESIGN.md`).

## معايير القبول

- [ ] جميع المكونات المشتركة في `resources/js/Components/ui/` تستخدم رموز من `mistral.ai\DESIGN.md` فقط.
- [ ] ملف `DESIGN.md` على جذر المشروع يحذف أو يُعاد توجيهه إلى `mistral.ai\DESIGN.md` (لا يوجد نظامان متعارضان).
- [ ] جميع النماذج تستخدم `<FormInput />` و`<FormSelect />` و`<FormCheckbox />` و`<FormTextarea />` — لا `<input>` يدوي.
- [ ] جميع الجداول تستخدم `<DataTable />` — لا `<table>` يدوي.
- [ ] جميع المودالات تستخدم `<FormModal />` — لا بناء modal مخصص.
- [ ] جميع البطاقات تستخدم `<Card />` بنوع (base/feature/cream).
- [ ] جميع الأزرار تستخدم `<Button />` بنوع (primary/secondary/cream/dark/on-cream/link).
- [ ] شريط الغروب الختامي (Sunset Stripe Band) يظهر في أسفل كل صفحة.
- [ ] الـ Sidebar والـ Navbar تستخدمان نفس الرموز (تكامل مع spec 002).
- [ ] لا قيم ألوان/مسافات/زوايا hard-coded في أي ملف Vue/JS.
- [ ] يتم اختبار التصميم على 3 نقاط فصل (mobile, tablet, desktop) في RTL.
- [ ] يتم اختبار تباين الألوان (WCAG AA) على الأزرار والنصوص الأساسية.
- [ ] تمر جميع اختبارات الـ components بدون أخطاء.
- [ ] لا تراجع في أداء التطبيق (Time to First Paint < 1.5s).

## الافتراضات

- `mistral.ai\DESIGN.md` هو المرجع الوحيد والثابت لرموز التصميم. أي تحديث يُحدّث في هذا الملف ثم يُسحب للمكونات.
- مكتبة Font Awesome 6 متاحة (مستخدمة بالفعل في المشروع).
- Vue 3 + Tailwind CSS 4.3 + Inertia.js هي البنية الأمامية المعتمدة (مذكورة في الدستور).
- خطوط PP Editorial Old وInter وJetBrains Mono متاحة أو يمكن استبدالها بـ fallbacks قريبة بصرياً (Georgia للعربي Editorial، system-ui للعربي Inter).
- التحديثات ستتم على دفعات: المرحلة 1 (المكونات الأساسية)، المرحلة 2 (الصفحات)، المرحلة 3 (التحسينات).
- `specs/002-sidebar-ui-redesign` سيُعدّل لاستخدام رموز Mistral بدلاً من Superhuman (الدمج مطلوب).

## الاعتماديات

- **يعتمد على:** `mistral.ai\DESIGN.md` (المرجع البصري الكامل).
- **يعتمد على:** `specs/002-sidebar-ui-redesign` (يجب توحيده مع النظام الجديد).
- **يعتمد على:** `resources/js/Components/ui/*` (المكونات المشتركة الحالية — يجب تحديثها).
- **يعتمد على:** `tailwind.config.js` (يجب تسجيل رموز Mistral كأسماء Tailwind).
- **مطلوب لـ:** كل صفحات ووحدات النظام الـ 22.
- **مطلوب لـ:** أي ميزة UI جديدة في المستقبل.

## مخاطر محتملة

- **اختلاف الـ fallback العربي:** خط PP Editorial Old قد لا يدعم العربية بشكل جيد. يجب اختبار العناوين العربية في كل الـ breakpoints قبل النشر.
- **WCAG Orange:** `{colors.primary}` (`#fa520f`) على `{colors.on-primary}` (أبيض) يحتاج تحقق تباين (قد يحتاج إلى `primary-deep` للنص).
- **حجم المكتبة:** تطبيق النظام على كل المكونات قد يزيد من حجم الـ bundle مؤقتاً — يجب قياس الأداء بعد كل دفعة.

---

*آخر تحديث: 2026-07-15*
