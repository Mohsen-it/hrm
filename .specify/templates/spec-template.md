# {Feature Name} - المواصفات

**الإصدار:** 1.0.0
**التاريخ:** {DATE}
**الحالة:** مسودة

---

## نظرة عامة
{وصف الميزة}

## قصص المستخدمين
- [ ] كـ {role}، أستطيع {action}
- [ ] كـ {role}، أستطيع {action}

## المتطلبات الوظيفية

### Business Rules
1. {rule 1}
2. {rule 2}

### Validation Rules
1. {validation 1}
2. {validation 2}

## المتطلبات التقنية

### قاعدة البيانات
- الجدول: `{table_name}`
- الحقول: id, {field1}, {field2}

### النموذج (Model)
- العلاقات:
  - `{relation}()` → BelongsTo/HasMany/BelongsToMany → {Model}
- الأوسمة (Scopes): `scope{Name}()`
- الوصولات (Accessors): `get{Name}Attribute()`

### الخدمة (Service)
- `{method}({params})`: {description}

### المستودع (Repository)
- `{method}({params})`: {description}

### التحكم (Controller)
- `index()`: عرض القائمة
- `create()`: عرض نموذج الإنشاء
- `store()`: حفظ البيانات
- `show($id)`: عرض التفاصيل
- `edit($id)`: عرض نموذج التعديل
- `update($id)`: تحديث البيانات
- `destroy($id)`: حذف البيانات

### المسارات (Routes)
```php
Route::resource('{resource}', {Controller}::class);
```

### القوالب (Views)
- `index.blade.php`
- `create.blade.php`
- `edit.blade.php`
- `show.blade.php`

## الصلاحيات
- `view-{module}`: عرض
- `create-{module}`: إنشاء
- `edit-{module}`: تعديل
- `delete-{module}`: حذف

## معايير القبول
- [ ] CRUD يعمل بشكل كامل
- [ ] الصلاحيات محمية
- [ ] الترجمة متوفرة (عربي/إنجليزي)
- [ ] الاختبارات تمر بنجاح

## الاعتماديات
- يعتمد على: {dependency}
- مطلوب لـ: {dependent}

---

*آخر تحديث: {DATE}*
