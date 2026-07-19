#!/usr/bin/env python3
"""
ZKTeco Microservice - Python Service for ZKTeco Devices
Uses pyzk library which may have better device support than PHP SDK

This service provides HTTP API endpoints for ZKTeco device operations
that are not supported by PHP SDK, particularly template upload.
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
import logging
from datetime import datetime
import traceback
import base64
import struct
import os

try:
    from zk import ZK, const
    from zk.user import User
    from zk.finger import Finger
except ImportError:
    print("⚠️ pyzk not installed. Run: pip install pyzk")
    ZK = None
    User = None
    Finger = None

app = Flask(__name__)
CORS(app)

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


def _resolve_service_port(default: int = 5000) -> int:
    """Resolve service port from environment variables."""
    raw_port = (
        os.getenv('ZKTECO_PYTHON_SERVICE_PORT')
        or os.getenv('PYTHON_SERVICE_PORT')
        or str(default)
    )
    try:
        return int(raw_port)
    except (TypeError, ValueError):
        return default


SERVICE_HOST = os.getenv('ZKTECO_PYTHON_SERVICE_HOST', '0.0.0.0')
SERVICE_PORT = _resolve_service_port()


class ZKTecoService:
    """ZKTeco Device Service using pyzk library"""
    
    def __init__(self, ip, port=4370, password=0, timeout=300, force_udp=None, ommit_ping=None):
        self.ip = ip
        self.port = port
        self.password = password
        self.timeout = timeout
        self.force_udp = force_udp
        self.ommit_ping = ommit_ping
        self.conn = None
        
    def connect(self):
        """Connect to ZKTeco device"""
        try:
            if ZK is None:
                raise Exception("pyzk library not installed")
            
            # تحديد إعدادات الاتصال بناءً على نوع التوصيل
            # إذا لم يتم تحديد force_udp، استخدم False (TCP) كافتراضي
            # إذا لم يتم تحديد ommit_ping، استخدم True لتجاوز مشكلة الـ ping
            force_udp = self.force_udp if self.force_udp is not None else False
            ommit_ping = self.ommit_ping if self.ommit_ping is not None else True
            
            logger.info(f"Connecting to device {self.ip}:{self.port} with force_udp={force_udp}, ommit_ping={ommit_ping}, timeout={self.timeout}")
            
            # محاولة الاتصال مع الإعدادات المحددة
            zk = ZK(self.ip, port=self.port, timeout=self.timeout, password=self.password, 
                   force_udp=force_udp, ommit_ping=ommit_ping, encoding='CP1256')
            self.conn = zk.connect()
            
            if self.conn:
                logger.info(f"✅ Connected to device {self.ip}:{self.port}")
                return True
            
            logger.warning(f"Connection returned None for {self.ip}:{self.port}")
            return False
            
        except Exception as e:
            logger.error(f"Connection failed to {self.ip}:{self.port}: {str(e)}")
            # محاولة بديلة: إذا فشل الاتصال مع الإعدادات الافتراضية، جرب UDP
            if not force_udp and self.force_udp is None:
                try:
                    logger.info(f"Retrying connection with UDP for {self.ip}:{self.port}")
                    zk = ZK(self.ip, port=self.port, timeout=self.timeout, password=self.password, 
                           force_udp=True, ommit_ping=ommit_ping, encoding='CP1256')
                    self.conn = zk.connect()
                    if self.conn:
                        logger.info(f"✅ Connected to device {self.ip}:{self.port} using UDP")
                        return True
                except Exception as udp_error:
                    logger.error(f"UDP connection also failed: {str(udp_error)}")
            
            raise
    
    def disconnect(self):
        """Disconnect from device"""
        if self.conn:
            self.conn.disconnect()
            logger.info("Disconnected from device")
    
    def test_template_upload_support(self):
        """Test if device supports template upload"""
        try:
            if not self.conn:
                raise Exception("Not connected to device")
            
            # Try to get device info
            firmware = self.conn.get_firmware_version()
            
            logger.info(f"Device firmware: {firmware}")
            
            # Check if device has template upload capability
            # pyzk library uses different approach - may work better
            return {
                'supported': True,  # Will test with actual upload
                'firmware': firmware,
                'method': 'pyzk'
            }
            
        except Exception as e:
            logger.error(f"Error testing support: {str(e)}")
            return {
                'supported': False,
                'error': str(e)
            }
    
    def export_template(self, uid, finger_id, template_data):
        """
        Export single fingerprint template to device using pyzk
        
        Args:
            uid: User ID on device
            finger_id: Finger index (0-9)
            template_data: Base64 encoded template data (full template with header)
        
        Returns:
            dict: Result with success status
        """
        try:
            if not self.conn:
                raise Exception("Not connected to device")
            
            if User is None or Finger is None:
                raise Exception("pyzk classes not available")
            
            # Decode base64 template
            template_bytes = base64.b64decode(template_data)
            
            logger.info(f"Exporting template via pyzk: UID={uid}, Finger={finger_id}, Size={len(template_bytes)}")
            
            # Get user object
            users = self.conn.get_users()
            target_user = None
            
            for user in users:
                if user.uid == uid:
                    target_user = user
                    break
            
            if not target_user:
                raise Exception(f"User with UID {uid} not found on device")
            
            logger.info(f"Found user: {target_user.name} (UID={uid})")
            
            # Parse template data to extract raw template (skip 6-byte header)
            if len(template_bytes) >= 6:
                # Template structure: [2 bytes size][2 bytes uid][1 byte finger][1 byte flag][template data]
                raw_template = template_bytes[6:]  # Skip header
                logger.info(f"Raw template size (after header): {len(raw_template)}")
            else:
                raw_template = template_bytes
            
            # Create Finger object
            finger_obj = Finger(
                uid=uid,
                fid=finger_id,
                valid=1,
                template=raw_template
            )
            
            logger.info(f"Created Finger object: UID={uid}, FID={finger_id}")
            
            # Use pyzk's save_user_template method
            start_time = datetime.now()
            
            try:
                # This uses _CMD_SAVE_USERTEMPS (different from PHP's CMD_USER_TEMP_WRQ!)
                self.conn.save_user_template(target_user, [finger_obj])
                
                elapsed = (datetime.now() - start_time).total_seconds()
                
                logger.info(f"✅ Template saved successfully via pyzk! Elapsed: {elapsed}s")
                
                return {
                    'success': True,
                    'elapsed': elapsed,
                    'uid': uid,
                    'finger_id': finger_id,
                    'method': 'pyzk_save_user_template'
                }
                
            except Exception as save_error:
                elapsed = (datetime.now() - start_time).total_seconds()
                
                logger.error(f"pyzk save_user_template failed: {str(save_error)}")
                
                # If it timed out or took too long, device doesn't support
                if elapsed > 10:
                    return {
                        'success': False,
                        'error': f'Device timeout ({elapsed}s) - firmware does not support template upload',
                        'elapsed': elapsed,
                        'device_not_supported': True
                    }
                
                return {
                    'success': False,
                    'error': str(save_error),
                    'elapsed': elapsed
                }
            
        except Exception as e:
            logger.error(f"Template export failed: {str(e)}")
            return {
                'success': False,
                'error': str(e)
            }
    
    def get_users(self):
        """Get all users from device"""
        try:
            if not self.conn:
                raise Exception("Not connected")
            
            users = self.conn.get_users()
            
            result = []
            for user in users:
                result.append({
                    'uid': user.uid,
                    'user_id': user.user_id,
                    'name': user.name,
                    'privilege': user.privilege,
                    'password': user.password if hasattr(user, 'password') else '',
                    'card': user.card if hasattr(user, 'card') else 0
                })
            
            logger.info(f"Retrieved {len(result)} users from device")
            return result
            
        except Exception as e:
            logger.error(f"Get users failed: {str(e)}")
            raise
    
    def add_user(self, uid, user_id, name, password='', privilege=0, card=0):
        """Add or update user on device"""
        try:
            if not self.conn:
                raise Exception("Not connected")
            
            logger.info(f"Adding user: UID={uid}, UserID={user_id}, Name={name}")
            
            self.conn.set_user(
                uid=uid,
                name=name,
                privilege=privilege,
                password=password,
                group_id='',
                user_id=user_id,
                card=card
            )
            
            logger.info(f"✅ User added/updated successfully")
            return True
            
        except Exception as e:
            logger.error(f"Add user failed: {str(e)}")
            return False
    
    def add_users_batch(self, users_data):
        """
        Add multiple users to device efficiently (batch operation)
        Optimized to fetch existing users only once
        
        Args:
            users_data: List of dicts with keys: user_id, name, password, privilege, card
            
        Returns:
            dict with 'success', 'failed', 'errors' keys
        """
        try:
            if not self.conn:
                raise Exception("Not connected")
            
            success_count = 0
            failed_count = 0
            errors = []
            
            # ✅ Fetch existing users ONCE for all operations
            logger.info(f"Fetching existing users for batch operation ({len(users_data)} users to add)")
            
            try:
                existing_users = self.conn.get_users()
                user_id_to_uid_map = {}
                user_id_to_privilege_map = {}  # ✅ حفظ privilege للمستخدمين الموجودين
                user_id_to_password_map = {}   # ✅ حفظ password للمستخدمين الموجودين
                user_id_to_card_map = {}       # ✅ حفظ card للمستخدمين الموجودين
                existing_uids = set()
                
                for user in existing_users:
                    existing_uids.add(user.uid)
                    if hasattr(user, 'user_id') and user.user_id:
                        user_id_to_uid_map[user.user_id] = user.uid
                        # ✅ حفظ البيانات الموجودة
                        if hasattr(user, 'privilege'):
                            user_id_to_privilege_map[user.user_id] = user.privilege
                        if hasattr(user, 'password') and user.password:
                            user_id_to_password_map[user.user_id] = user.password
                        if hasattr(user, 'card') and user.card:
                            user_id_to_card_map[user.user_id] = user.card
                
                logger.info(f"Found {len(existing_users)} existing users on device")
                
            except Exception as e:
                logger.warning(f"Could not fetch existing users: {str(e)}")
                user_id_to_uid_map = {}
                user_id_to_privilege_map = {}
                user_id_to_password_map = {}
                user_id_to_card_map = {}
                existing_uids = set()
            
            # Process each user
            for index, user_data in enumerate(users_data):
                try:
                    user_id = user_data.get('user_id', '')
                    name = user_data.get('name', '')
                    password = user_data.get('password', '')
                    privilege = user_data.get('privilege', 0)
                    card = user_data.get('card', 0)
                    
                    # Determine UID using cached data
                    if user_id in user_id_to_uid_map:
                        # User exists, use their existing UID and data
                        uid = user_id_to_uid_map[user_id]
                        
                        # ✅ استخدام البيانات الموجودة في الجهاز إذا كان المستخدم موجود
                        if user_id in user_id_to_privilege_map:
                            privilege = user_id_to_privilege_map[user_id]
                        if user_id in user_id_to_password_map:
                            password = user_id_to_password_map[user_id]
                        if user_id in user_id_to_card_map:
                            card = user_id_to_card_map[user_id]
                            
                        logger.info(f"Using existing data for user_id={user_id}: UID={uid}, privilege={privilege}")
                    else:
                        # User doesn't exist, find next available UID
                        if existing_uids:
                            uid = 1
                            while uid in existing_uids:
                                uid += 1
                            # Add to existing UIDs to avoid duplicates in this batch
                            existing_uids.add(uid)
                            user_id_to_uid_map[user_id] = uid
                        else:
                            # No users exist, start with UID based on index
                            uid = index + 1
                            existing_uids.add(uid)
                            user_id_to_uid_map[user_id] = uid
                        
                        logger.info(f"Assigned new UID={uid} for user_id={user_id}")
                    
                    # Add/update user with determined UID
                    self.conn.set_user(
                        uid=uid,
                        name=name,
                        privilege=privilege,
                        password=password,
                        group_id='',
                        user_id=user_id,
                        card=card
                    )
                    
                    success_count += 1
                    logger.info(f"✅ User added in batch: {name} (UID={uid}, UserID={user_id})")
                    
                except Exception as e:
                    failed_count += 1
                    error_msg = f"Error adding {user_data.get('name', 'unknown')}: {str(e)}"
                    errors.append(error_msg)
                    logger.error(error_msg)
            
            logger.info(f"Batch operation complete: {success_count} success, {failed_count} failed")
            
            return {
                'success': success_count,
                'failed': failed_count,
                'errors': errors
            }
            
        except Exception as e:
            logger.error(f"Batch add users failed: {str(e)}")
            raise
    
    def delete_user(self, uid):
        """Delete user from device"""
        try:
            if not self.conn:
                raise Exception("Not connected")
            
            logger.info(f"Deleting user: UID={uid}")
            self.conn.delete_user(uid=uid)
            logger.info(f"✅ User deleted successfully")
            return True
            
        except Exception as e:
            logger.error(f"Delete user failed: {str(e)}")
            return False
    
    def get_attendance(self):
        """Get attendance logs from device"""
        try:
            if not self.conn:
                raise Exception("Not connected")
            
            logs = self.conn.get_attendance()
            
            result = []
            for log in logs:
                result.append({
                    'uid': log.uid,
                    'user_id': log.user_id,
                    'timestamp': log.timestamp.strftime('%Y-%m-%d %H:%M:%S') if log.timestamp else None,
                    'status': log.status,
                    'punch': log.punch
                })
            
            logger.info(f"Retrieved {len(result)} attendance logs")
            return result
            
        except Exception as e:
            logger.error(f"Get attendance failed: {str(e)}")
            raise
    
    def get_fingerprint_templates(self, uid):
        """Get all fingerprint templates for a user"""
        try:
            if not self.conn:
                raise Exception("Not connected")
            
            templates = self.conn.get_templates()
            
            user_templates = []
            for template in templates:
                if hasattr(template, 'uid') and template.uid == uid:
                    # Repack template with header
                    template_data = template.repack() if hasattr(template, 'repack') else template.template
                    
                    user_templates.append({
                        'uid': template.uid,
                        'fid': template.fid,
                        'valid': template.valid,
                        'template': base64.b64encode(template_data).decode('utf-8')
                    })
            
            logger.info(f"Retrieved {len(user_templates)} templates for UID {uid}")
            return user_templates
            
        except Exception as e:
            logger.error(f"Get templates failed: {str(e)}")
            raise
    
    def get_all_templates(self):
        """Get all fingerprint templates from device"""
        try:
            if not self.conn:
                raise Exception("Not connected")
            
            templates = self.conn.get_templates()
            
            result = []
            for template in templates:
                # Repack template with header
                template_data = template.repack() if hasattr(template, 'repack') else template.template
                
                result.append({
                    'uid': template.uid,
                    'fid': template.fid,
                    'valid': template.valid,
                    'template': base64.b64encode(template_data).decode('utf-8')
                })
            
            logger.info(f"Retrieved {len(result)} total templates from device")
            return result
            
        except Exception as e:
            logger.error(f"Get all templates failed: {str(e)}")
            raise
    
    def clear_attendance(self):
        """Clear all attendance logs from device"""
        try:
            if not self.conn:
                raise Exception("Not connected")
            
            logger.info("Clearing attendance logs")
            self.conn.clear_attendance()
            logger.info("✅ Attendance logs cleared")
            return True
            
        except Exception as e:
            logger.error(f"Clear attendance failed: {str(e)}")
            return False
    
    def get_device_info(self):
        """Get device information"""
        try:
            if not self.conn:
                raise Exception("Not connected")
            
            info = {
                'firmware': self.conn.get_firmware_version(),
                'serialnumber': self.conn.get_serialnumber() if hasattr(self.conn, 'get_serialnumber') else None,
                'platform': self.conn.get_platform() if hasattr(self.conn, 'get_platform') else None,
                'device_name': self.conn.get_device_name() if hasattr(self.conn, 'get_device_name') else None,
                'users_count': len(self.conn.get_users()),
                'attendance_count': len(self.conn.get_attendance()),
                'templates_count': len(self.conn.get_templates())
            }
            
            logger.info(f"Device info retrieved: {info}")
            return info
            
        except Exception as e:
            logger.error(f"Get device info failed: {str(e)}")
            raise


# API Endpoints

@app.route('/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({
        'status': 'ok',
        'service': 'ZKTeco Microservice',
        'version': '1.0.0',
        'pyzk_available': ZK is not None
    })


@app.route('/device/test-connection', methods=['POST'])
def test_connection():
    """Test connection to device"""
    try:
        data = request.json
        ip = data.get('ip')
        port = data.get('port', 4370)
        password = data.get('password', 0)
        
        if not ip:
            return jsonify({'success': False, 'error': 'IP address required'}), 400
        
        service = ZKTecoService(ip, port, password)
        connected = service.connect()
        
        if connected:
            # Test template upload support
            support_info = service.test_template_upload_support()
            service.disconnect()
            
            return jsonify({
                'success': True,
                'connected': True,
                'support_info': support_info
            })
        else:
            return jsonify({
                'success': False,
                'error': 'Could not connect to device'
            }), 500
            
    except Exception as e:
        logger.error(f"Test connection error: {str(e)}")
        return jsonify({
            'success': False,
            'error': str(e),
            'traceback': traceback.format_exc()
        }), 500


@app.route('/device/export-template', methods=['POST'])
def export_template():
    """
    Export single template to device
    
    Request body:
    {
        "ip": "192.168.10.240",
        "port": 4370,
        "password": 0,
        "uid": 1,
        "finger_id": 0,
        "template_data": "base64_encoded_template"
    }
    """
    try:
        data = request.json
        
        ip = data.get('ip')
        port = data.get('port', 4370)
        password = data.get('password', 0)
        uid = data.get('uid')
        finger_id = data.get('finger_id')
        template_data = data.get('template_data')
        
        if not all([ip, uid is not None, finger_id is not None, template_data]):
            return jsonify({
                'success': False,
                'error': 'Missing required parameters'
            }), 400
        
        service = ZKTecoService(ip, port, password)
        
        if not service.connect():
            return jsonify({
                'success': False,
                'error': 'Could not connect to device'
            }), 500
        
        result = service.export_template(uid, finger_id, template_data)
        service.disconnect()
        
        if result.get('method_not_available'):
            return jsonify({
                'success': False,
                'error': 'pyzk library does not support template upload',
                'note': 'This is a library limitation. Consider using alternative SDK.',
                'result': result
            }), 501  # Not Implemented
        
        return jsonify(result)
        
    except Exception as e:
        logger.error(f"Export template error: {str(e)}")
        return jsonify({
            'success': False,
            'error': str(e),
            'traceback': traceback.format_exc()
        }), 500


@app.route('/device/export-templates-batch', methods=['POST'])
def export_templates_batch():
    """
    Export multiple templates to device
    
    Request body:
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
            ...
        ]
    }
    """
    try:
        data = request.json
        
        ip = data.get('ip')
        port = data.get('port', 4370)
        password = data.get('password', 0)
        templates = data.get('templates', [])
        
        if not ip or not templates:
            return jsonify({
                'success': False,
                'error': 'Missing required parameters'
            }), 400
        
        service = ZKTecoService(ip, port, password)
        
        if not service.connect():
            return jsonify({
                'success': False,
                'error': 'Could not connect to device'
            }), 500
        
        results = []
        success_count = 0
        failed_count = 0
        
        for template_info in templates:
            uid = template_info.get('uid')
            finger_id = template_info.get('finger_id')
            template_data = template_info.get('template_data')
            
            result = service.export_template(uid, finger_id, template_data)
            results.append(result)
            
            if result.get('success'):
                success_count += 1
            else:
                failed_count += 1
        
        service.disconnect()
        
        return jsonify({
            'success': True,
            'total': len(templates),
            'success_count': success_count,
            'failed_count': failed_count,
            'results': results
        })
        
    except Exception as e:
        logger.error(f"Batch export error: {str(e)}")
        return jsonify({
            'success': False,
            'error': str(e),
            'traceback': traceback.format_exc()
        }), 500


@app.route('/device/get-users', methods=['POST'])
def get_users():
    """Get users from device"""
    try:
        data = request.json
        
        ip = data.get('ip')
        port = data.get('port', 4370)
        password = data.get('password', 0)
        
        if not ip:
            return jsonify({'success': False, 'error': 'IP required'}), 400
        
        service = ZKTecoService(ip, port, password)
        
        if not service.connect():
            return jsonify({
                'success': False,
                'error': 'Could not connect'
            }), 500
        
        users = service.get_users()
        service.disconnect()
        
        return jsonify({
            'success': True,
            'users': users,
            'count': len(users)
        })
        
    except Exception as e:
        logger.error(f"Get users error: {str(e)}")
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500


@app.route('/device/add-user', methods=['POST'])
def add_user():
    """Add user to device"""
    try:
        data = request.json
        
        ip = data.get('ip')
        port = data.get('port', 4370)
        password = data.get('password', 0)
        uid = data.get('uid')
        user_id = data.get('user_id')
        name = data.get('name')
        user_password = data.get('user_password', '')
        privilege = data.get('privilege', 0)
        card = data.get('card', 0)
        
        if not all([ip, uid is not None, user_id, name]):
            return jsonify({'success': False, 'error': 'Missing required parameters'}), 400
        
        service = ZKTecoService(ip, port, password)
        
        if not service.connect():
            return jsonify({'success': False, 'error': 'Could not connect'}), 500
        
        result = service.add_user(uid, user_id, name, user_password, privilege, card)
        service.disconnect()
        
        return jsonify({'success': result})
        
    except Exception as e:
        logger.error(f"Add user error: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/delete-user', methods=['POST'])
def delete_user():
    """Delete user from device"""
    try:
        data = request.json
        
        ip = data.get('ip')
        port = data.get('port', 4370)
        password = data.get('password', 0)
        uid = data.get('uid')
        
        if not all([ip, uid is not None]):
            return jsonify({'success': False, 'error': 'Missing required parameters'}), 400
        
        service = ZKTecoService(ip, port, password)
        
        if not service.connect():
            return jsonify({'success': False, 'error': 'Could not connect'}), 500
        
        result = service.delete_user(uid)
        service.disconnect()
        
        return jsonify({'success': result})
        
    except Exception as e:
        logger.error(f"Delete user error: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/get-attendance', methods=['POST'])
def get_attendance():
    """Get attendance logs from device"""
    try:
        data = request.json
        
        ip = data.get('ip')
        port = data.get('port', 4370)
        password = data.get('password', 0)
        timeout = data.get('timeout', 300)
        force_udp = data.get('force_udp')  # None = auto, True = force UDP, False = force TCP
        ommit_ping = data.get('ommit_ping')  # None = auto, True = skip ping, False = use ping
        
        if not ip:
            return jsonify({'success': False, 'error': 'IP required'}), 400
        
        logger.info(f"Getting attendance from {ip}:{port} with timeout={timeout}, force_udp={force_udp}, ommit_ping={ommit_ping}")
        
        service = ZKTecoService(ip, port, password, timeout, force_udp, ommit_ping)
        
        if not service.connect():
            return jsonify({'success': False, 'error': 'Could not connect to device'}), 500
        
        # Get attendance logs first and save them before disconnect
        # This ensures we return the data even if disconnect() fails
        logs = service.get_attendance()
        logs_count = len(logs)
        
        # Try to disconnect, but don't fail if it throws an exception
        disconnect_error = None
        try:
            service.disconnect()
        except Exception as disconnect_ex:
            disconnect_error = str(disconnect_ex)
            logger.warning(f"Disconnect failed after successful data retrieval: {disconnect_error}")
            # Continue anyway - we already have the data
        
        logger.info(f"Retrieved {logs_count} attendance logs from {ip}:{port}")
        
        # Return success with data even if disconnect failed
        response = {
            'success': True,
            'attendance': logs,
            'count': logs_count
        }
        
        # Add warning if disconnect failed (for logging purposes, but still return success)
        if disconnect_error:
            response['disconnect_warning'] = f"Data retrieved successfully, but disconnect failed: {disconnect_error}"
            logger.info(f"Returning success with data despite disconnect error: {disconnect_error}")
        
        return jsonify(response)
        
    except Exception as e:
        logger.error(f"Get attendance error: {str(e)}")
        logger.error(traceback.format_exc())
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/get-templates', methods=['POST'])
def get_templates():
    """Get all templates from device or for specific user"""
    try:
        data = request.json
        
        ip = data.get('ip')
        port = data.get('port', 4370)
        password = data.get('password', 0)
        uid = data.get('uid')  # Optional - if provided, get only for this user
        
        if not ip:
            return jsonify({'success': False, 'error': 'IP required'}), 400
        
        service = ZKTecoService(ip, port, password)
        
        if not service.connect():
            return jsonify({'success': False, 'error': 'Could not connect'}), 500
        
        if uid:
            templates = service.get_fingerprint_templates(uid)
        else:
            templates = service.get_all_templates()
        
        service.disconnect()
        
        return jsonify({
            'success': True,
            'templates': templates,
            'count': len(templates)
        })
        
    except Exception as e:
        logger.error(f"Get templates error: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/clear-attendance', methods=['POST'])
def clear_attendance():
    """Clear attendance logs from device"""
    try:
        data = request.json
        
        ip = data.get('ip')
        port = data.get('port', 4370)
        password = data.get('password', 0)
        
        if not ip:
            return jsonify({'success': False, 'error': 'IP required'}), 400
        
        service = ZKTecoService(ip, port, password)
        
        if not service.connect():
            return jsonify({'success': False, 'error': 'Could not connect'}), 500
        
        result = service.clear_attendance()
        service.disconnect()
        
        return jsonify({'success': result})
        
    except Exception as e:
        logger.error(f"Clear attendance error: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/info', methods=['POST'])
def get_device_info():
    """Get device information"""
    try:
        data = request.json
        
        ip = data.get('ip')
        port = data.get('port', 4370)
        password = data.get('password', 0)
        
        if not ip:
            return jsonify({'success': False, 'error': 'IP required'}), 400
        
        service = ZKTecoService(ip, port, password)
        
        if not service.connect():
            return jsonify({'success': False, 'error': 'Could not connect'}), 500
        
        info = service.get_device_info()
        service.disconnect()
        
        return jsonify({
            'success': True,
            'info': info
        })
        
    except Exception as e:
        logger.error(f"Get device info failed: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/adms-config', methods=['POST'])
def get_adms_config():
    """Get ADMS push configuration for devices"""
    try:
        data = request.json
        
        ip = data.get('ip')
        port = data.get('port', 4370)
        password = data.get('password', 0)
        server_url = data.get('server_url', 'http://YOUR_SERVER_IP/api/fingerprint-devices/adms-push')
        
        if not ip:
            return jsonify({'success': False, 'error': 'IP required'}), 400
        
        service = ZKTecoService(ip, port, password)
        
        if not service.connect():
            return jsonify({'success': False, 'error': 'Could not connect'}), 500
        
        info = service.get_device_info()
        service.disconnect()
        
        return jsonify({
            'success': True,
            'adms_info': {
                'server_url': server_url,
                'device_info': info,
                'instructions': {
                    'ar': 'قم بضبط هذا الرابط في إعدادات ADMS على الجهاز',
                    'en': 'Set this URL in ADMS settings on the device'
                }
            }
        })
        
    except Exception as e:
        logger.error(f"Get ADMS config failed: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/add-users-batch', methods=['POST'])
def add_users_batch():
    """Add multiple users to device in batch - with smart UID management"""
    try:
        data = request.json
        
        ip = data.get('ip')
        port = data.get('port', 4370)
        password = data.get('password', 0)
        users_data = data.get('users', [])
        
        if not ip:
            return jsonify({'success': False, 'error': 'IP required'}), 400
        
        if not users_data:
            return jsonify({'success': False, 'error': 'No users provided'}), 400
        
        logger.info(f"Batch add users request: {len(users_data)} users")
        
        service = ZKTecoService(ip, port, password)
        
        if not service.connect():
            return jsonify({'success': False, 'error': 'Could not connect'}), 500
        
        # Use the optimized batch add method
        result = service.add_users_batch(users_data)
        service.disconnect()
        
        return jsonify({
            'success': True,
            'success_count': result['success'],
            'failed_count': result['failed'],
            'errors': result['errors']
        })
        
    except Exception as e:
        logger.error(f"Batch add users error: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500


if __name__ == '__main__':
    logger.info("Starting ZKTeco Microservice...")
    logger.info(f"pyzk available: {ZK is not None}")
    
    if ZK is None:
        logger.warning("⚠️ pyzk not installed. Run: pip install pyzk")
        logger.warning("Service will start but template operations will fail")
    
    logger.info(f"Listening on {SERVICE_HOST}:{SERVICE_PORT}")
    app.run(host=SERVICE_HOST, port=SERVICE_PORT, debug=False)

