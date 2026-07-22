# عقود API - إدارة طلبات الإجازة

**التاريخ:** 2026-07-22
**الحالة:** Phase 1 Complete

---

## 1. إنشاء طلب إجازة (Create Leave Request)

### POST /api/leaves

**الطلب:**
```json
{
  "leave_type_id": 1,
  "start_date": "2026-08-01",
  "end_date": "2026-08-05",
  "reason": "إجازة سنوية",
  "notes": "ملاحظات اختيارية",
  "contact_phone_during_leave": "0500000000"
}
```

**الاستجابة (201):**
```json
{
  "success": true,
  "message": "تم إنشاء طلب الإجازة بنجاح",
  "data": {
    "id": 1,
    "request_number": "LR20260801-1234",
    "status": "draft",
    "days_count": 3,
    "start_date": "2026-08-01",
    "end_date": "2026-08-05"
  }
}
```

**الأخطاء:**
- `422` - بيانات غير صحيحة
- `403` - غير مصرح

---

## 2. تعديل المسودة (Update Draft)

### PUT /api/leaves/:id

**الطلب:**
```json
{
  "leave_type_id": 2,
  "start_date": "2026-08-10",
  "end_date": "2026-08-12",
  "reason": "سبب محدث"
}
```

**الاستجابة (200):**
```json
{
  "success": true,
  "message": "تم تحديث الطلب بنجاح",
  "data": {
    "id": 1,
    "status": "draft",
    "days_count": 2
  }
}
```

**الأخطاء:**
- `404` - الطلب غير موجود
- `403` - غير مصرح (ليس صاحب الطلب)
- `422` - الطلب ليس بحالة مسودة

---

## 3. تقديم الطلب (Submit Leave Request)

### POST /api/leaves/:id/submit

**الطلب:**
```json
{}
```

**الاستجابة (200):**
```json
{
  "success": true,
  "message": "تم تقديم الطلب بنجاح",
  "data": {
    "id": 1,
    "status": "submitted",
    "current_step": 1,
    "approvals": [
      {
        "step": 1,
        "approver_name": "مدير القسم",
        "status": "pending"
      }
    ]
  }
}
```

**الأخطاء:**
- `404` - الطلب غير موجود
- `403` - غير مصرح
- `422` - الطلب ليس بحالة مسودة
- `422` - لا يمكن تقديم طلب ≥ 5 أيام
- `422` - يوجد تداخل مع طلب آخر
- `422` - الرصيد غير كافٍ

---

## 4. معالجة موافقة (Process Approval)

### POST /api/approvals/:id/process

**الطلب:**
```json
{
  "action": "approved",
  "notes": "ملاحظات اختيارية"
}
```

**أو:**
```json
{
  "action": "rejected",
  "notes": "سبب الرفض (إلزامي)"
}
```

**أو:**
```json
{
  "action": "returned",
  "notes": "ملاحظات التعديل (إلزامي)"
}
```

**الاستجابة (200):**
```json
{
  "success": true,
  "message": "تمت معالجة الموافقة بنجاح",
  "data": {
    "approval_id": 1,
    "action": "approved",
    "leave_status": "manager_approved",
    "current_step": 2
  }
}
```

**الأخطاء:**
- `404` - الموافقة غير موجودة
- `403` - غير مصرح (ليس المعتمد)
- `422` - الموافقة تمت معالجتها مسبقاً

---

## 5. عرض طلباتي (List My Requests)

### GET /api/leaves

**الاستجابة (200):**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "per_page": 20,
    "total": 15,
    "data": [
      {
        "id": 1,
        "request_number": "LR20260801-1234",
        "leave_type_name": "إجازة سنوية",
        "start_date": "2026-08-01",
        "end_date": "2026-08-05",
        "days_count": 3,
        "status": "submitted",
        "created_at": "2026-07-20"
      }
    ]
  }
}
```

**الفلاتر:**
- `?status=submitted` - تصفية بالحالة
- `?leave_type_id=1` - تصفية بنوع الإجازة
- `?page=1&limit=20` - ترقيم الصفحات

---

## 6. تفاصيل الطلب (Get Leave Request)

### GET /api/leaves/:id

**الاستجابة (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "request_number": "LR20260801-1234",
    "user": {
      "id": 5,
      "name": "محمد الدوسري",
      "role": "employee",
      "department_name": "تقنية المعلومات"
    },
    "leave_type": {
      "id": 1,
      "name": "إجازة سنوية",
      "is_paid": true
    },
    "start_date": "2026-08-01",
    "end_date": "2026-08-05",
    "days_count": 3,
    "reason": "إجازة سنوية",
    "notes": null,
    "status": "submitted",
    "current_step": 1,
    "approvals": [
      {
        "id": 1,
        "approver": {
          "id": 10,
          "name": "أحمد المدير"
        },
        "step": 1,
        "status": "pending",
        "notes": null,
        "processed_at": null
      }
    ],
    "attachments": [],
    "created_at": "2026-07-20 10:00:00"
  }
}
```

---

## 7. عرض الموافقات المعلقة (List Pending Approvals)

### GET /api/approvals/pending

**الاستجابة (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "leave_request": {
        "id": 5,
        "request_number": "LR20260801-5678",
        "user_name": "محمد الدوسري",
        "department_name": "تقنية المعلومات",
        "leave_type_name": "إجازة سنوية",
        "start_date": "2026-08-10",
        "end_date": "2026-08-12",
        "days_count": 2
      },
      "step": 1,
      "status": "pending",
      "created_at": "2026-07-21"
    }
  ]
}
```

---

## 8. طلب إلغاء إجازة معتمدة (Request Cancellation)

### POST /api/leaves/:id/request-cancellation

**الطلب:**
```json
{
  "reason": "سبب الإلغاء"
}
```

**الاستجابة (200):**
```json
{
  "success": true,
  "message": "تم إرسال طلب الإلغاء بنجاح",
  "data": {
    "id": 1,
    "status": "pending_cancellation"
  }
}
```

**الأخطاء:**
- `404` - الطلب غير موجود
- `422` - الطلب ليس بحالة gm_approved

---

## 9. معالجة إلغاء إجازة (Process Cancellation)

### POST /api/approvals/:id/process-cancellation

**الطلب:**
```json
{
  "action": "approved",
  "notes": "الموافقة على الإلغاء"
}
```

**الاستجابة (200):**
```json
{
  "success": true,
  "message": "تمت معالجة الإلغاء بنجاح",
  "data": {
    "approval_id": 2,
    "action": "approved",
    "leave_status": "cancelled",
    "balance_restored": 3
  }
}
```

---

## 10. رفع مرفق (Upload Attachment)

### POST /api/leaves/:id/attachment

**الطلب:** `multipart/form-data`
- `file`: الملف (PDF, PNG, JPG, DOCX)
- `description`: وصف اختياري

**الاستجابة (201):**
```json
{
  "success": true,
  "message": "تم رفع المرفق بنجاح",
  "data": {
    "id": 1,
    "file_name": "medical_report.pdf",
    "file_type": "application/pdf",
    "file_size": 1024000
  }
}
```

**الأخطاء:**
- `413` - حجم الملف يتجاوز 10MB
- `422` - نوع الملف غير مدعوم

---

## 11. تصدير Excel (Export Excel)

### GET /api/reports/export/excel

**الفلاتر:**
- `?start_date=2026-01-01`
- `?end_date=2026-12-31`
- `?status=gm_approved`
- `?department_id=3`
- `?leave_type_id=1`

**الاستجابة:** ملف Excel (binary)

---

## 12. تصدير PDF (Export PDF)

### GET /api/reports/export/pdf

**نفس فلاتر Excel**

**الاستجابة:** ملف PDF (binary)

---

*آخر تحديث: 2026-07-22*
