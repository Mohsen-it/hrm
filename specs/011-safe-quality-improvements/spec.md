# المواصفة 011 — تطويرات نوعية آمنة بدون كسر (Safe Quality Improvements)

**التاريخ:** 2026-09-09
**الحالة:** قيد التنفيذ (المرحلة P0)
**المبدأ الأعلى:** لا حذف بيانات، لا تغيير وظيفي، لا كسر — كل تغيير إضافي (additive) وقابل للتراجع.

---

## 1. ما تم اكتشافه في الفحص (ولماذا هذه الخطة)

فحص شامل بتاريخ 2026-09-09 أظهر أن جزءاً كبيراً من خطة `010` **مُنفّذ فعلاً** في الكود الحالي:

| بند من 010 | الحالة الفعلية المكتشفة | الدليل |
|---|---|---|
| `LOG_STACK=daily` + مستوى warning | ✅ منفذ | `.env:18-22` |
| `CACHE_STORE=file` (ليس database) | ✅ منفذ | `.env:41` |
| `Cache::tags` guard مع fallback | ✅ منفذ | `Setting.php:117-136` + `ZoneService.php:182-196` |
| `DB_QUEUE_RETRY_AFTER=610` + `AFTER_COMMIT=true` | ✅ منفذ | `.env:52-56` + `config/queue.php:38-45` |
| `AttendanceIngestionJob` tries/backoff/timeout | ✅ منفذ | `timeout=600 < retry_after=610` |
| قنوات daily لـ laravel/biodata/attendance-push | ✅ منفذة وتعمل | ملفات مؤرخة يومياً في `storage/logs/` |
| `hrm-laravel-server.log` rotation | ❌ **غير منفذ فعلياً** | الملف 239MB ويكبر (stdout خارجي، ليس قناة Laravel) |
| 19 سكربت debug في الجذر | ❌ موجودة ومنسية | `_dbg_*`/`_audit_*`/`_cleanup_*` بتاريخ 2026-08-27 |
| `hrm_server` channel يشارك نفس مسار stdout | ⚠️ تعارض كامن (القناة غير مستخدمة) | `config/logging.php:142-148` |

## 2. النطاق — المرحلة P0 فقط (هذه المواصفة)

1. **P0-1:** تدوير stdout logs عند بدء التشغيل (أرشفة بإعادة تسمية، بدون حذف).
2. **P0-2:** أرشفة سكربتات debug من الجذر عبر `git mv` (حفظ التاريخ، لا شيء يشير إليها).
3. **P0-3:** فك تعارض مسار قناة `hrm_server` (قناة غير مستخدمة — تغيير آمن).
4. **P0-4:** تصحيح سطر توثيق غير دقيق في `docs/OPTIMIZATION_ROADMAP.md`.

## 3. خارج النطاق (مؤجل لمراحل لاحقة بموافقة صريحة)

- أي migration أو فهرس جديد (P1 — المواصفة 008).
- أي إصلاح N+1 في `AbsenceCalculationService` أو كنترولرات Shifts (P1).
- أي تغيير في أوامر queue workers (`--timeout=180/60`) وهي تعمل حالياً.
- تصميم الإجازات 009 (يبنى بجداول موازية عند طلبه).
- المساس بمحتوى `hrm-laravel-server.log` الحالي (يُترك كما هو — لا حذف).

## 4. بوابات التحقق الإلزامية (راجع checklist.md)

قبل/بعد كل تغيير: `git status` + اختبارات مستهدفة + `pint --test` للملفات المعدلة.
ممنوع: `migrate:fresh` / `optimize:clear` على بيئة الإنتاج الحية / حذف أي ملف (`git mv` فقط).
