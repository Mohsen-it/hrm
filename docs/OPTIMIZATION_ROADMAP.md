# خارطة طريق تحسين النظام — HRM (2026-2028)

**الحالة:** التوثيق مكتمل — التنفيذ يبدأ من `specs/010-system-optimization-stability/`
**المواصفة الكاملة:** [`specs/010-system-optimization-stability/spec.md`](../specs/010-system-optimization-stability/spec.md)
**الخطة التقنية:** [`plan.md`](../specs/010-system-optimization-stability/plan.md)
**المهام:** [`tasks.md`](../specs/010-system-optimization-stability/tasks.md) — 47 مهمة
**التقدم الحي:** [`progress.md`](../specs/010-system-optimization-stability/progress.md)

---

## الرؤية: نظام يعيش 10 سنوات بدون فشل فجائي

```
اليوم (2026)                    المستقبل (2028)
يعمل لكن هش ──────────► مستقر + سريع + قابل للتوسع
```

| البُعد | اليوم | الهدف |
|-------|-------|-------|
| **Cache/Queue** | database (بطيء، tags مكسور) | redis (سريع، tags يعمل) |
| **DB** | 85% indexes + ثغرات حرجة | 100% + لا table scan |
| **Architecture** | 65% Controllers تستخدم Model مباشر | 0% — كلها عبر Service/Repo |
| **Frontend** | 2.49MB بلا split | <200KB initial + lazy chunks |
| **Logs** | 19MB بلا rotation | daily 14d + VACUUM |
| **Scheduler** | يعمل يدوياً | cron + Supervisor + onOneServer |

---

## المراحل — نظرة طير

```
P0 ████████ Infrastructure الحرج          يوم 1  — يمنع الفشل الفجائي (80%)
P1 ████████ Database                      يوم 2  — يصلح البطء
P2 ████████ Architecture                  يوم 3  — يصلح N+1 والديون التقنية
P3 ████████ Frontend                      يوم 4  — يسرّع التحميل
P4 ████████ Scheduler + Validation        يوم 5  — يضمن الاستمرارية
─────────────────────────────────────────────────
Polish ░░░ التوثيق + Benchmark            يوم 5
```

**MVP (يوم 1):** P0 + جزء من P1 → يمنع 80% من الفشل الفجائي — قابل للنشر فوراً.

---

## ماذا سنعمل الآن (010) — تفصيل

### P0 — حرج (8 مهام) — يبدأ فوراً
- `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis`
- `retry_after=610`, `after_commit=true`, `attendance_push` channel
- إصلاح `Cache::tags` + `ZKTeco PID` على Windows

### P1 — قاعدة البيانات (8 مهام)
- 3 migrations جديدة (deleted_at + esc_active + holidays)
- إصلاح `whereDate` → `whereBetween`, `LIKE "%"` → FULLTEXT, `per_page=all`

### P2 — المعمارية (11 مهمة)
- DI بدل `app()`, نقل business من Controllers, إصلاح N+1, cache لـ Holidays, SQL aggregation

### P3 — الواجهة (7 مهام)
- `manualChunks`, FontAwesome subset, `DashboardChart` lazy, `ViolationTable` extract, حذف CSS ميت

### P4 — الجدولة والتحقق (10 مهام)
- Jobs `tries/backoff`, نقل schedules إلى `routes/console.php`, `onOneServer`, 6 tests, `pint`, `migrate:rollback`

---

## ماذا سنعمل مستقبلاً (011+) — خارطة الطريق

| # | الميزة | متى | الهدف |
|---|--------|-----|-------|
| 011 | Full-Text Search | بعد 010 | استبدال كل `LIKE "%"` بـ `FULLTEXT` + `tsvector` |
| 012 | API Rate Limiting + Throttling | 2026 Q4 | حماية من الضغط + `ATTENDANCE_PUSH_RATE_LIMIT=60` |
| 013 | Horizon / Queue Monitoring | 2026 Q4 | لوحة مراقبة Queue + تنبيه عند فشل |
| 014 | Read Replicas | 2027 | `sticky: true` + replica للقراءة |
| 015 | Partitioning لـ `raw_attendance_logs` | 2027 | تقسيم شهري للجدول الأكبر |
| 016 | CDN + Asset Optimization | 2027 | `bunny` fonts `display=swap` + `content-visibility` |
| 017 | Automated Backup + PITR | 2027 | نسخ احتياطي يومي + point-in-time recovery |
| 018 | Load Testing (k6) | 2028 | اختبار 1000 req/s |

---

## كيف نتابع التقدم

1. **الملف الحي:** [`specs/010-system-optimization-stability/progress.md`](../specs/010-system-optimization-stability/progress.md) — يُحدّث بعد كل مهمة
2. **Checklist:** [`checklist.md`](../specs/010-system-optimization-stability/checklist.md) — تحقق قبل/بعد كل مرحلة
3. **الأمر:** `git log --oneline --grep="010"` — كل commit يذكر رقم المهمة
4. **الاختبار:** `php artisan test && php artisan pint --test && npm run build`

---

## معايير النجاح النهائية (010)

| # | المعيار |
|---|---------|
| 1 | `Cache::tags()->flush()` يمسح كل المفاتيح ✅ |
| 2 | لا duplicate jobs ✅ |
| 3 | `/users` < 200ms على 10k ✅ |
| 4 | تقرير الحضور < 500ms ✅ |
| 5 | `hrm-laravel-server.log` daily rotation ✅ |
| 6 | JS initial < 200KB ✅ |
| 7 | `php artisan test` 100% ✅ |
| 8 | `pint` نظيف ✅ |
| 9 | لا تغيير وظيفي ✅ |

---

## البدء

```bash
# 1. إنشاء الفرع
git checkout -b 010-system-optimization-stability

# 2. البدء بـ P0
# راجع tasks.md — T001 أولاً
```

**الموافقة المطلوبة:** هل نبدأ P0 الآن؟ (8 مهام — يوم واحد)

---

*آخر تحديث: 2026-08-27*
*المؤلف: Muse Spark — فحص 4 محاور + 47 مهمة*
