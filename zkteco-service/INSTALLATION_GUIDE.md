# دليل التثبيت - ZKTeco Python Microservice
# Installation Guide - ZKTeco Python Microservice

## 📋 المتطلبات (Prerequisites)

### 1. Python
```bash
# تحقق من وجود Python
python3 --version
# يجب أن يكون 3.8 أو أحدث

# إذا لم يكن مثبتاً:
# على macOS:
brew install python3

# على Ubuntu/Debian:
sudo apt-get update
sudo apt-get install python3 python3-pip python3-venv

# على Windows:
# حمّل من python.org
```

### 2. pip
```bash
# تحقق من pip
pip3 --version

# إذا لم يكن مثبتاً:
python3 -m ensurepip --upgrade
```

---

## 🚀 التثبيت خطوة بخطوة

### الخطوة 1: الانتقال للمجلد

```bash
cd /path/to/hrm/zkteco-service
```

### الخطوة 2: إنشاء Virtual Environment

```bash
# إنشاء venv
python3 -m venv venv

# تفعيل venv
# على macOS/Linux:
source venv/bin/activate

# على Windows:
venv\Scripts\activate

# يجب أن ترى (venv) في بداية السطر
```

### الخطوة 3: تثبيت المكتبات

```bash
# تثبيت جميع المكتبات
pip install -r requirements.txt

# يجب أن ترى:
# ✅ Installing Flask...
# ✅ Installing pyzk...
# ... etc
```

### الخطوة 4: إعداد Environment

```bash
# نسخ ملف الإعدادات
cp .env.example .env

# تعديل إذا لزم (اختياري)
nano .env
```

---

## ▶️ تشغيل الخدمة

### طريقة 1: باستخدام Startup Script (الأسهل)

```bash
# على macOS/Linux:
./start.sh

# على Windows:
start.bat
```

### طريقة 2: يدوياً

```bash
# تفعيل venv
source venv/bin/activate  # macOS/Linux
# أو
venv\Scripts\activate  # Windows

# تشغيل
python app.py
```

### سترى:

```
* Running on http://0.0.0.0:5000
Starting ZKTeco Microservice...
pyzk available: True
```

---

## ✅ التحقق من التثبيت

### 1. اختبار Health Endpoint

في terminal جديد:

```bash
curl http://localhost:5000/health
```

يجب أن ترى:

```json
{
  "status": "ok",
  "service": "ZKTeco Microservice",
  "version": "1.0.0",
  "pyzk_available": true
}
```

### 2. اختبار الاتصال بالجهاز

```bash
curl -X POST http://localhost:5000/device/test-connection \
  -H "Content-Type: application/json" \
  -d '{
    "ip": "192.168.10.240",
    "port": 4370,
    "password": 0
  }'
```

---

## 🔗 دمج مع Laravel

### الخطوة 1: تحديث .env

أضف في `/path/to/hrm/.env`:

```env
ZKTECO_PYTHON_SERVICE_ENABLED=true
ZKTECO_PYTHON_SERVICE_URL=http://localhost:5000
ZKTECO_PYTHON_SERVICE_TIMEOUT=60
```

### الخطوة 2: مسح Cache

```bash
cd /path/to/hrm
php artisan config:clear
php artisan cache:clear
```

### الخطوة 3: اختبار من Laravel

افتح tinker:

```bash
php artisan tinker
```

```php
$service = new \App\Services\ZKTecoPythonBridgeService();

// Test if available
$service->isAvailable();
// Should return: true

// Test connection
$result = $service->testConnection('192.168.10.240', 4370, 0);
print_r($result);
```

---

## 🔧 الإنتاج (Production)

### استخدام Supervisor (موصى به)

#### 1. تثبيت Supervisor

```bash
# على Ubuntu/Debian:
sudo apt-get install supervisor

# على macOS:
brew install supervisor
```

#### 2. إنشاء Config File

```bash
sudo nano /etc/supervisor/conf.d/zkteco-service.conf
```

محتوى الملف:

```ini
[program:zkteco-service]
command=/path/to/hrm/zkteco-service/venv/bin/python app.py
directory=/path/to/hrm/zkteco-service
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/path/to/hrm/zkteco-service/logs/service.log
environment=FLASK_ENV="production"
```

#### 3. تشغيل Supervisor

```bash
# Reload config
sudo supervisorctl reread
sudo supervisorctl update

# Start service
sudo supervisorctl start zkteco-service

# Check status
sudo supervisorctl status zkteco-service
```

---

### استخدام systemd

#### 1. إنشاء Service File

```bash
sudo nano /etc/systemd/system/zkteco-service.service
```

محتوى الملف:

```ini
[Unit]
Description=ZKTeco Python Microservice
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/path/to/hrm/zkteco-service
ExecStart=/path/to/hrm/zkteco-service/venv/bin/python app.py
Restart=always
Environment="FLASK_ENV=production"

[Install]
WantedBy=multi-user.target
```

#### 2. تفعيل وتشغيل

```bash
# Reload systemd
sudo systemctl daemon-reload

# Enable service
sudo systemctl enable zkteco-service

# Start service
sudo systemctl start zkteco-service

# Check status
sudo systemctl status zkteco-service

# View logs
sudo journalctl -u zkteco-service -f
```

---

## 🛡️ الأمان

### 1. تقييد الوصول

في `app.py`, غيّر:

```python
# من:
app.run(host='0.0.0.0', port=5000)

# إلى (localhost only):
app.run(host='127.0.0.1', port=5000)
```

### 2. استخدام Nginx كـ Reverse Proxy

```nginx
# /etc/nginx/sites-available/zkteco-service

server {
    listen 5000;
    server_name localhost;
    
    location / {
        proxy_pass http://127.0.0.1:5001;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        
        # Security headers
        add_header X-Content-Type-Options nosniff;
        add_header X-Frame-Options DENY;
    }
}
```

### 3. Firewall

```bash
# السماح فقط من localhost
sudo ufw deny 5000
sudo ufw allow from 127.0.0.1 to any port 5000
```

---

## 🐛 استكشاف الأخطاء

### المشكلة: "pyzk not installed"

```bash
# تأكد من تفعيل venv
source venv/bin/activate

# ثبّت pyzk
pip install pyzk

# أو
pip install -r requirements.txt
```

### المشكلة: "Connection refused"

```bash
# تحقق من تشغيل الخدمة
curl http://localhost:5000/health

# إذا فشل:
# - تأكد من تشغيل app.py
# - تحقق من port 5000 (lsof -i:5000)
```

### المشكلة: Laravel لا يتصل بـ Python service

```bash
# في Laravel .env:
ZKTECO_PYTHON_SERVICE_URL=http://localhost:5000

# تأكد من:
php artisan config:clear

# اختبار:
php artisan tinker
>>> (new \App\Services\ZKTecoPythonBridgeService())->isAvailable();
```

---

## 📊 المراقبة (Monitoring)

### Logs

```bash
# Python service logs
tail -f /path/to/zkteco-service/logs/service.log

# Laravel logs
tail -f /path/to/hrm/storage/logs/laravel.log

# Supervisor logs (if using)
sudo tail -f /var/log/supervisor/zkteco-service.log
```

### Health Check Script

```bash
#!/bin/bash
# health_check.sh

STATUS=$(curl -s http://localhost:5000/health | jq -r '.status')

if [ "$STATUS" == "ok" ]; then
    echo "✅ Service is healthy"
    exit 0
else
    echo "❌ Service is down"
    exit 1
fi
```

---

## 🔄 التحديث

### عند تحديث الكود:

```bash
cd zkteco-service

# Pull latest changes
git pull

# Activate venv
source venv/bin/activate

# Update dependencies
pip install --upgrade -r requirements.txt

# Restart service
# Supervisor:
sudo supervisorctl restart zkteco-service

# systemd:
sudo systemctl restart zkteco-service
```

---

## ⚠️ ملاحظات مهمة

### 1. pyzk Library Limitations

```
⚠️ pyzk قد لا يدعم template upload أيضاً
⚠️ يعتمد على firmware الجهاز
⚠️ ليس حلاً سحرياً
```

### 2. الأداء

```
✅ Python service: microservice منفصل
⚠️ Network latency إضافي (localhost)
⚠️ عملية أبطأ قليلاً من PHP مباشر
```

### 3. Fallback Strategy

```
1️⃣ جرّب Python service
2️⃣ إذا فشل → PHP SDK
3️⃣ إذا فشل → رسالة للمستخدم
```

---

## 📞 الدعم

### إذا واجهت مشاكل:

1. **تحقق من Logs:**
   - Python service logs
   - Laravel logs

2. **تأكد من المتطلبات:**
   - Python 3.8+
   - pyzk installed
   - Service running

3. **تواصل مع المطور:**
   - راجع `FINGERPRINT_EXPORT_FINAL_CONCLUSION.md`
   - تحقق من التوثيق

---

**آخر تحديث:** 2025-10-11  
**الحالة:** ✅ جاهز للتثبيت والاختبار

