# ZKTeco Microservice

Python-based microservice for ZKTeco device operations using `pyzk` library.

## Why This Service?

The PHP SDK (`coding-libs/zkteco-php`) has limitations with some ZKTeco devices, particularly for template upload operations. This Python service uses the `pyzk` library which may have better device compatibility.

## Features

- ✅ Health check endpoint
- ✅ Test device connection
- ✅ Export single template
- ✅ Export batch templates
- ✅ Get users from device
- 🔄 Better device compatibility (potentially)

## Installation

### Prerequisites

- Python 3.8 or higher
- pip

### Setup

```bash
cd zkteco-service

# Create virtual environment
python3 -m venv venv

# Activate virtual environment
# On macOS/Linux:
source venv/bin/activate
# On Windows:
# venv\Scripts\activate

# Install dependencies
pip install -r requirements.txt

# Copy environment file
cp .env.example .env
```

## Usage

### Start the service

```bash
# Activate virtual environment first
source venv/bin/activate

# Run the service
python app.py
```

The service will start on `http://localhost:5000`

### Run as background service (production)

```bash
# Using nohup
nohup python app.py > service.log 2>&1 &

# Or using systemd (Linux)
sudo systemctl start zkteco-service
```

## API Endpoints

### 1. Health Check

```bash
GET http://localhost:5000/health
```

Response:
```json
{
  "status": "ok",
  "service": "ZKTeco Microservice",
  "version": "1.0.0",
  "pyzk_available": true
}
```

### 2. Test Connection

```bash
POST http://localhost:5000/device/test-connection
Content-Type: application/json

{
  "ip": "192.168.10.240",
  "port": 4370,
  "password": 0
}
```

### 3. Export Single Template

```bash
POST http://localhost:5000/device/export-template
Content-Type: application/json

{
  "ip": "192.168.10.240",
  "port": 4370,
  "password": 0,
  "uid": 1,
  "finger_id": 0,
  "template_data": "base64_encoded_template..."
}
```

### 4. Export Batch Templates

```bash
POST http://localhost:5000/device/export-templates-batch
Content-Type: application/json

{
  "ip": "192.168.10.240",
  "port": 4370,
  "password": 0,
  "templates": [
    {
      "uid": 1,
      "finger_id": 0,
      "template_data": "base64..."
    },
    {
      "uid": 1,
      "finger_id": 1,
      "template_data": "base64..."
    }
  ]
}
```

### 5. Get Users

```bash
POST http://localhost:5000/device/get-users
Content-Type: application/json

{
  "ip": "192.168.10.240",
  "port": 4370,
  "password": 0
}
```

## Integration with Laravel

### In Laravel, create a service to communicate with this microservice:

```php
// app/Services/ZKTecoPythonService.php

class ZKTecoPythonService
{
    protected $serviceUrl = 'http://localhost:5000';
    
    public function exportTemplate($device, $uid, $fingerId, $templateData)
    {
        $response = Http::timeout(60)->post($this->serviceUrl . '/device/export-template', [
            'ip' => $device->ip_address,
            'port' => $device->port,
            'password' => 0,
            'uid' => $uid,
            'finger_id' => $fingerId,
            'template_data' => $templateData
        ]);
        
        return $response->json();
    }
}
```

## Important Notes

### About pyzk Library

The `pyzk` library is a Python implementation for ZKTeco devices. However:

⚠️ **It may also have limitations** with template upload on some devices.

⚠️ **Not all methods are implemented** in pyzk.

The actual support depends on:
- Device firmware version
- Device model
- Security settings

### Fallback Strategy

If pyzk also doesn't support template upload:

1. **Use Official ZKTeco SDK** (Windows only, C++/C#)
2. **Use manual registration** on device
3. **Contact ZKTeco support** for assistance

## Troubleshooting

### pyzk not installed

```bash
pip install pyzk
```

### Connection timeout

- Check device IP and port
- Ensure device is reachable: `ping 192.168.10.240`
- Check firewall settings

### Template upload not supported

- This is likely a **device firmware limitation**
- Use manual registration instead
- Consider upgrading device firmware

## Logs

Logs are printed to stdout. In production, redirect to file:

```bash
python app.py > logs/service.log 2>&1
```

## Security

- Change default password in `.env`
- Use HTTPS in production
- Restrict access to service (firewall/nginx)
- Consider authentication (JWT, API keys)

## Development

### Running in development mode:

```bash
export FLASK_ENV=development
python app.py
```

### Testing:

```bash
# Test health
curl http://localhost:5000/health

# Test connection
curl -X POST http://localhost:5000/device/test-connection \
  -H "Content-Type: application/json" \
  -d '{"ip": "192.168.10.240"}'
```

## License

Same as main HRM project.

---

**Note:** This is an experimental service to work around PHP SDK limitations. The actual success depends on device firmware capabilities.

