# عقد API فئات الحضور (Attendance Groups)

**التاريخ:** 2026-07-16

---

## المسارات

### GET /attendance/groups
عرض قائمة الفئات

**الاستجابة:**
```json
{
    "data": [
        {
            "id": 1,
            "code": "SHIFT-AM",
            "name": "مناوبة صباحية",
            "company_id": 1,
            "status": 1,
            "employees_count": 25,
            "schedules_count": 3,
            "created_at": "2026-01-15T10:00:00Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 20,
        "total": 100
    }
}
```

### POST /attendance/groups
إنشاء فئة جديدة

**الطلب:**
```json
{
    "code": "SHIFT-PM",
    "name": "مناوبة مسائية",
    "company_id": 1
}
```

**الاستجابة:**
```json
{
    "data": {
        "id": 2,
        "code": "SHIFT-PM",
        "name": "مناوبة مسائية",
        "company_id": 1,
        "status": 1
    },
    "message": "تم إنشاء فئة الحضور بنجاح"
}
```

### GET /attendance/groups/{id}
عرض تفاصيل فئة

**الاستجابة:**
```json
{
    "data": {
        "id": 1,
        "code": "SHIFT-AM",
        "name": "مناوبة صباحية",
        "company_id": 1,
        "status": 1,
        "employees": [
            {
                "id": 1,
                "emp_id": 101,
                "employee_name": "أحمد محمد",
                "enable_attendance": true,
                "enable_schedule": true,
                "enable_overtime": false,
                "enable_holiday": true
            }
        ],
        "schedules": [
            {
                "id": 1,
                "shift_id": 1,
                "shift_name": "صباحية",
                "start_date": "2026-01-01",
                "end_date": "2026-12-31"
            }
        ],
        "policy": {
            "id": 1,
            "weekend1": 6,
            "weekend2": 0,
            "max_hrs": 8,
            "daily_ot": true
        }
    }
}
```

### PUT /attendance/groups/{id}
تعديل فئة

**الطلب:**
```json
{
    "name": "مناوبة صباحية (محدث)"
}
```

### DELETE /attendance/groups/{id}
حذف فئة

**الشرط:** لا يوجد موظفون نشطون في الفئة

**الاستجابة:**
```json
{
    "message": "تم حذف فئة الحضور بنجاح"
}
```

### POST /attendance/groups/{groupId}/employees
تعيين موظف للفئة

**الطلب:**
```json
{
    "emp_id": 102,
    "enable_attendance": true,
    "enable_schedule": true,
    "enable_overtime": false,
    "enable_holiday": true,
    "enable_compensatory": false
}
```

### DELETE /attendance/groups/{groupId}/employees/{employeeId}
إزالة موظف من الفئة

### GET /attendance/groups/{groupId}/employees
عرض موظفي الفئة

---

## أكواد الخطأ

| الكود | الوصف |
|-------|-------|
| 422 | بيانات غير صالحة |
| 404 | الفئة غير موجودة |
| 409 | لا يمكن الحذف (يوجد موظفون نشطون) |
| 403 | غير مصرح |

---

*آخر تحديث: 2026-07-16*
