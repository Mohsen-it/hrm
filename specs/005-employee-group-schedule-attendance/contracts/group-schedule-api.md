# عقد API جداول الفئات (Group Schedules)

**التاريخ:** 2026-07-16

---

## المسارات

### GET /attendance/group-schedules
عرض قوائم جداول الفئات

**الاستجابة:**
```json
{
    "data": [
        {
            "id": 1,
            "group_id": 1,
            "group_name": "مناوبة صباحية",
            "shift_id": 1,
            "shift_name": "صباحية",
            "start_date": "2026-01-01",
            "end_date": "2026-12-31",
            "status": 1
        }
    ]
}
```

### POST /attendance/group-schedules
إنشاء جدول جديد

**الطلب:**
```json
{
    "group_id": 1,
    "shift_id": 1,
    "start_date": "2026-07-01",
    "end_date": "2026-07-31"
}
```

**التحقق:**
- لا يوجد تداخل مع جداول أخرى لنفس الفئة في نفس الفترة

### GET /attendance/group-schedules/{id}
عرض تفاصيل الجدول

### PUT /attendance/group-schedules/{id}
تعديل جدول

### DELETE /attendance/group-schedules/{id}
حذف جدول

**الشرط:** لا يكون الجدول في فترة حالية أو مستقبلية

---

*آخر تحديث: 2026-07-16*
