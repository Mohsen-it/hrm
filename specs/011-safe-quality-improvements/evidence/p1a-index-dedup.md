# دليل P1-A — إزالة فهرسين زائدين من `attendance_sessions`

**التاريخ:** 2026-09-09
**الملف:** `database/migrations/2026_09_09_000001_drop_redundant_attendance_session_indexes.php`

## البرهان (قاعدة البادئة اليسرى)
| الفهرس المحذوف | الفهرس المغطي الباقي |
|---|---|
| `att_sessions_user_date_idx(user_id, attendance_date)` | `idx_att_sessions_user_date_status(user_id, attendance_date, status)` |
| `att_sessions_date_status_idx(attendance_date, status)` | `idx_att_sessions_date_status_type(attendance_date, status, session_type)` |

## قبل
- `attendance_sessions`: data=6.52MB / idx=18.97MB (15 شجرة فهرس — 3x حجم البيانات)
- counts: sessions=27395, users=990, raw_logs=36859
- EXPLAIN Q1: `range` key=`att_sessions_user_status_date_idx` rows=16
- EXPLAIN Q2: `range` key=`att_sessions_date_status_idx` rows=2821

## بعد (migrate — 116ms ثم 52ms)
- الفهرسان الزائدان محذوفان، المغطيان موجودان
- counts: sessions=27396 (+1 كتابة حية أثناء العمل — النظام الحي لم يتوقف), users=990
- EXPLAIN Q1: `range` key=`att_sessions_user_status_date_idx` rows=16 (مطابق)
- EXPLAIN Q2: `range` key=`idx_att_sessions_date_status_type` rows=2822 (المغطي — مطابق)

## التراجع (مُختبر على الإنتاج)
- `migrate:rollback --step=1`: الفهرسان عادا، counts سليمة
- `migrate` مجدداً: الحذف تم — ذهاب وإياب آمن

## الاختبارات
- `IndexingTest`: 5/5 ✅
- `tests/Unit/Modules/Attendance` + `tests/Feature/Modules/Attendance`: 48/48 ✅
- إخفاقات `AttendanceIntegration` العشرون (ADMS/Biodata parsers) **موجودة مسبقاً** قبل هذا العمل (موثقة في فحص 2026-09-09 الأول + `.phpunit.result.cache`) — تعمل على sqlite memory ولا علاقة لها بفهارس MySQL
