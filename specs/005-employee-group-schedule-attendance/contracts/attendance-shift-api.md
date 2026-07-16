# عقد API مناوبات الحضور (Attendance Shifts)

**التاريخ:** 2026-07-16

---

## المسارات

### GET /attendance/shifts
عرض قائمة المناوبات

**الاستجابة:**
```json
{
    "data": [
        {
            "id": 1,
            "alias": "صباحية",
            "cycle_unit": 1,
            "shift_cycle": 1,
            "work_weekend": false,
            "company_id": 1,
            "details_count": 7
        }
    ]
}
```

### POST /attendance/shifts
إنشاء مناوبة جديدة

**الطلب:**
```json
{
    "alias": "مسائية",
    "cycle_unit": 1,
    "shift_cycle": 1,
    "work_weekend": false,
    "weekend_type": 0,
    "work_day_off": false,
    "day_off_type": 0,
    "auto_shift": 0,
    "enable_ot_rule": false,
    "frequency": 1,
    "company_id": 1,
    "details": [
        {
            "day_index": 0,
            "in_time": "14:00",
            "out_time": "22:00",
            "time_interval_id": 2
        },
        {
            "day_index": 1,
            "in_time": "14:00",
            "out_time": "22:00",
            "time_interval_id": 2
        }
    ]
}
```

### GET /attendance/shifts/{id}
عرض تفاصيل مناوبة مع التفاصيل اليومية

**الاستجابة:**
```json
{
    "data": {
        "id": 1,
        "alias": "صباحية",
        "cycle_unit": 1,
        "shift_cycle": 1,
        "company_id": 1,
        "details": [
            {
                "id": 1,
                "day_index": 0,
                "day_name": "الأحد",
                "in_time": "06:00",
                "out_time": "14:00",
                "time_interval_id": 1,
                "time_interval_alias": "فترة صباحية"
            }
        ]
    }
}
```

### PUT /attendance/shifts/{id}
تعديل مناوبة

### DELETE /attendance/shifts/{id}
حذف مناوبة

**الشرط:** لا توجد جداول نشطة مرتبطة

---

*آخر تحديث: 2026-07-16*
