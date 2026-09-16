# Phase 5 — Operations, Monitoring & Rollout (2026-09-14)

> نفّذ من هنا: تدقيق قراءة + إثباتات + توثيق. لم يُلمس `.env`، لم تُشغّل workers، لم يُفعّل cron.

## 1. إثباتات الجاهزية (نُفذت محلياً ومسحت)

| الفحص | النتيجة |
|---|---|
| `route:cache` | كان محظوراً ب closure `/` → حُوّل إلى `Route::redirect` (سلوك مكافئ 302). بعده: **cached 388 route بنجاح ثم `route:clear` — صفر أثر** (`bootstrap/cache` مُتجاهل git) |
| `config:cache` | **نجح والإقلاع سليم** ثم `config:clear` — صفر أثر. ملاحظة: `AttendanceIntegration/app/Logs/channels.php` يقرأ `env()` عند الإقلاع → تحت cache تعود levels للافتراضي (debug). أثره تجميلي (مستوى سجلات فقط)، موثق لا مُصلح |
| `env()` خارج config | ممسوح (app + 4 وحدات حرجة): لا سوء استخدام يعطل الـ cache |
| `/` route | `ANY / → RedirectController` قابل للتسلسل |
| `pint routes/web.php` | PASS |

## 2. فجوة المجدول (الأهم تشغيلياً)

- **35 أمر artisan موجود، صفر مجدول**: `routes/console.php` فيه `inspire` فقط، ولا `Schedule::` في أي مكان.
- أوامر تستحق cron (تُفعّل على السيرفر في نافذة صيانة، خارج هذا العمل):
  - `attendance:close-open-sessions` — يومياً بعد منتصف الليل ب little delay
  - `attendance:mark-absent` + `mark-late` — بعد إغلاق الجلسات
  - `attendance:generate-daily-summaries` — بعد الحساب
  - `devices:health-check` — كل 5-15 دقيقة (ADMS bridge)
  - `devices:retry-failed-face` — كل 10 دقائق (bounded أصلاً)
  - `attendance:cleanup-old-logs` — أسبوعياً (retention)
  - `queue:prune-failed --hours=72` — يومياً (جدول `failed_jobs` موجود عبر migration الأساس)
- تحذير: لا تُفعّل قبل مراجعة crontab/Task Scheduler الحالي — التفعيل المزدوج يضاعف التوزيع.

## 3. العمال والطوابير

- على المضيف: 5 عمليات `php.exe` نشطة (workers/serve محتملة) — **يجب تأكيد أوامرها**: `queue:work --queue=attendance,... --timeout` مطابق لـ job timeouts (خطر Phase 2 المسجل: mismatch يسبب retry storms).
- الإنتاج: `queue=database`, `cache/session=file`. المسار غير المتزامن (Biodata/Attendance jobs) يعتمد على worker حي — راقب `jobs` (تراكم) و`failed_jobs` (أخطاء).

## 4. البيئة: `.env.example` ≠ الإنتاج (موثق، بلا أسرار)

| المفتاح | المثال | الإنتاج المرصود |
|---|---|---|
| APP_ENV/DEBUG | local/true | production/OFF |
| DB | sqlite | mysql |
| QUEUE/CACHE/SESSION | redis | database/file/file |
| السجلات | — | daily rotation 14 يوم (`laravel/biodata/attendance-push`) + 30 يوم integration |

## 5. الصحة والمراقبة (قائمة السيرفر)

- `/up` موجود (framework). المقترح: فحص مخصص DB + queue depth + آخر `last_seen` للأجهزة (عبر `devices:health-check`).
- راقب: `failed_jobs` count، `jobs` lag، slow queries (>100ms)، حجم `storage/logs` + `backups/debug-archive-*`، مساحة القرص.
- Retention: سجلات daily موجودة (14/30 يوم). `backups/debug-archive-2026-09-09` + `grant_admin_bridge.php` الجذريان: **لا حذف** بلا سياسة وموافقة (دين pint مسجل من Phase 0).

## 6. خطة Rollout للتغييرات المنجزة

1. **الآن (منخفض الخطر):** 12 ملف اختبارات + `ReportTable` + توحيد UI + `navigation.js` + تحويل `/` — كلها مغطاة (532 اختبار + build + lint).
2. **نافذة صيانة أولى:** `php artisan migrate` (عمودان nullable فقط — آمن نظرياً، يُقاس زمنه) + `config:cache` + `route:cache` + `view:cache` (مثبتة هنا).
3. **ممنوع وقت ذروة البصمات:** أي rollout يمس `AttendanceIntegration`/`FingerprintDevices`.
4. **Kill switches جاهزة:** `fingerprintdevices.device_writes_enabled=false` يوقف التوزيع؛ `distribution_max_age_hours` يحد عمر المهام.
5. **رجوع:** `migrate:rollback --step=1` (مثبتة down() في عزلة) + `config:clear/route:clear` + redeploy السابق.

## 7. ملاحظة صراحة

بُنيت أصول `public/build` على هذا المضيف أثناء العمل (محتوى مكافئ + تغييرات UI المُجازة). إن كان يخدم حركة حية، فهذا التفاف على إجراء الـ rollout — يُذكر للشفافية.
