// اختبار سريع: هل يمكن جلب البيانات عبر API؟
fetch('/api/rotations')
    .then(res => res.json())
    .then(data => {
        console.log('API Rotations:', data);
        alert('الاختبار ناجح: ' + data.length + ' دوريات');
    })
    .catch(err => {
        console.error('خطأ في API:', err);
        alert('خطأ في API: تحقق من Network tab');
    });