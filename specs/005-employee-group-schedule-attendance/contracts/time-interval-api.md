# عقد API الفواصل الزمنية (Time Intervals)

**التاريخ:** 2026-07-16

---

## المسارات

### GET /attendance/time-intervals
عرض قائمة الفواصل

**الاستجابة:**
```json
{
    "data": [
        {
            "id": 1,
            "alias": "فترة صباحية",
            "in_time": "06:00",
            "duration": 480,
            "in_ahead_margin": 15,
            "in_above_margin": 30,
            "out_ahead_margin": 0,
            "out_above_margin": 15,
            "day_change": "00:00",
            "enable_overtime": true,
            "company_id": 1,
            "break_times_count": 1
        }
    ]
}
```

### POST /attendance/time-intervals
إنشاء فاصل جديد

**الطلب:**
```json
{
    "alias": "فترة مسائية",
    "in_time": "14:00",
    "duration": 480,
    "in_ahead_margin": 15,
    "in_above_margin": 30,
    "out_ahead_margin": 0,
    "out_above_margin": 15,
    "day_change": "00:00",
    "enable_overtime": true,
    "company_id": 1,
    "break_time_ids": [1, 2]
}
```

### GET /attendance/time-intervals/{id}
عرض تفاصيل الفاصل

**الاستجابة:**
```json
{
    "data": {
        "id": 1,
        "alias": "فترة صباحية",
        "in_time": "06:00",
        "duration": 480,
        "day_change": "00:00",
        "enable_overtime": true,
        "break_times": [
            {
                "id": 1,
                "alias": "استراحة غداء",
                "period_start": "10:00",
                "duration": 60
            }
        ]
    }
}
```

### PUT /attendance/time-intervals/{id}
تعديل فاصل

### DELETE /attendance/time-intervals/{id}
حذف فاصل

**الشرط:** لا يكون مستخدماً في مناوبات نشطة

---

*آخر تحديث: 2026-07-16*
