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
import threading

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
        self._templates_cache = None
        
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
    
    def _get_all_templates_cached(self):
        """Get all templates from device, with caching per connection"""
        if self._templates_cache is not None:
            return self._templates_cache
        
        self._templates_cache = self.conn.get_templates()
        return self._templates_cache

    def get_fingerprint_templates(self, uid):
        """Get all fingerprint templates for a user"""
        try:
            if not self.conn:
                raise Exception("Not connected")
            
            templates = self._get_all_templates_cached()
            
            user_templates = []
            for template in templates:
                if hasattr(template, 'uid') and template.uid == uid:
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
    
    # ================================================================
    # Face Template Methods (ZKTeco Face IDs: 50-54)
    # ================================================================

    def get_all_face_templates(self):
        """
        Get all face templates from device.
        Face templates on ZKTeco iFace devices are stored as binary data
        accessible via read_with_buffer with FCT values 3 or similar.
        """
        try:
            if not self.conn:
                raise Exception("Not connected")

            result = []
            self.conn.read_sizes()
            face_count = self.conn.faces
            logger.info(f"Device reports {face_count} face templates")

            if face_count == 0:
                return result

            # Method 1: Try bulk read via read_with_buffer with FCT_FACE
            # Try FCT values 3, 9, 10 for face data
            for fct in [3, 9, 10, 11, 12]:
                try:
                    logger.info(f"Trying bulk face read with FCT={fct}")
                    face_data, size = self.conn.read_with_buffer(7, fct)  # CMD_DB_RRQ=7
                    logger.info(f"FCT={fct}: got {size} bytes")

                    if size >= 4:
                        total_size = unpack('i', face_data[0:4])[0]
                        logger.info(f"FCT={fct}: total_size={total_size}")
                        face_data = face_data[4:]

                        while total_size >= 6:
                            tpl_size, uid, fid, valid = unpack('HHbb', face_data[:6])
                            if tpl_size < 6 or tpl_size > total_size:
                                logger.info(f"FCT={fct}: invalid tpl_size={tpl_size}, stopping")
                                break
                            template = unpack("%is" % (tpl_size - 6), face_data[6:tpl_size])[0]
                            result.append({
                                'uid': uid,
                                'fid': fid,
                                'valid': valid,
                                'template': base64.b64encode(template).decode('utf-8'),
                                'tpl_size': tpl_size,
                            })
                            face_data = face_data[tpl_size:]
                            total_size -= tpl_size

                        if result:
                            logger.info(f"Retrieved {len(result)} face templates via FCT={fct}")
                            return result
                except Exception as e:
                    logger.info(f"FCT={fct} failed: {str(e)[:200]}")
                    continue

            # Method 2: Try CMD_USERTEMP_RRQ (9) with face IDs 50-54 per user
            if not result:
                try:
                    logger.info("Trying per-user face read via CMD_USERTEMP_RRQ")
                    users = self.conn.get_users()
                    for user in users:
                        for face_id in range(50, 55):
                            try:
                                cmd = 9  # CMD_USERTEMP_RRQ
                                uid_bytes = pack('<H', user.uid)
                                face_id_byte = bytes([face_id])
                                command_string = uid_bytes + face_id_byte

                                cmd_response = self.conn._ZK__send_command(cmd, command_string, response_size=1024)
                                if cmd_response.get('status'):
                                    template_data = self.conn._ZK__data
                                    if template_data and len(template_data) > 0:
                                        if any(b != 0 for b in template_data[:min(16, len(template_data))]):
                                            result.append({
                                                'uid': user.uid,
                                                'fid': face_id,
                                                'valid': 1,
                                                'template': base64.b64encode(template_data).decode('utf-8'),
                                                'tpl_size': len(template_data),
                                            })
                            except Exception:
                                continue
                except Exception as e:
                    logger.warning(f"Per-user face read failed: {str(e)}")

            logger.info(f"Retrieved {len(result)} face templates total")
            return result

        except Exception as e:
            logger.error(f"Get face templates failed: {str(e)}")
            raise

    def export_face_template(self, uid, face_id, template_data):
        """
        Export (upload) a face template to the device.

        Args:
            uid: User ID on device
            face_id: Face template ID (50-54)
            template_data: Base64 encoded face template data

        Returns:
            dict with success status
        """
        try:
            if not self.conn:
                raise Exception("Not connected")

            template_bytes = base64.b64decode(template_data)

            logger.info(f"Exporting face template: UID={uid}, FaceID={face_id}, Size={len(template_bytes)}")

            # Get user object
            users = self.conn.get_users()
            target_user = None
            for user in users:
                if user.uid == uid:
                    target_user = user
                    break

            if not target_user:
                raise Exception(f"User with UID {uid} not found on device")

            # Use CMD_USERTEMP_WRQ (10) to upload face template
            # Format: [size_lo][size_hi][uid_lo][uid_hi][face_id][flag][data...]
            template_size = len(template_bytes)
            prefix = pack('<HHBB', template_size, uid, face_id, 1)
            command_string = prefix + template_bytes

            cmd_response = self.conn._ZK__send_command(10, command_string, response_size=1024)

            if cmd_response.get('status'):
                logger.info(f"✅ Face template exported successfully: UID={uid}, FaceID={face_id}")
                return {'success': True, 'uid': uid, 'face_id': face_id}
            else:
                # Try chunked transfer for large templates
                if len(command_string) > 1024:
                    self.conn._ZK__send_command(1500, pack('<I', len(command_string)))  # CMD_PREPARE_DATA
                    for i in range(0, len(command_string), 1024):
                        chunk = command_string[i:i + 1024]
                        self.conn._ZK__send_command(1501, chunk)  # CMD_DATA
                    cmd_response = self.conn._ZK__send_command(10, b'')  # CMD_USERTEMP_WRQ
                    if cmd_response.get('status'):
                        logger.info(f"✅ Face template exported via chunked transfer: UID={uid}, FaceID={face_id}")
                        return {'success': True, 'uid': uid, 'face_id': face_id}

                raise Exception(f"Device returned error for face template upload")

        except Exception as e:
            logger.error(f"Export face template failed: {str(e)}")
            return {'success': False, 'error': str(e)}

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

    def set_user_face(self, uid, photo_data):
        """
        Upload a face photo (JPEG) to the device.

        Args:
            uid: User ID on device (internal uid)
            photo_data: Raw JPEG bytes

        Returns:
            dict with success status
        """
        try:
            if not self.conn:
                raise Exception("Not connected")

            logger.info(f"Uploading face photo: UID={uid}, Size={len(photo_data)} bytes")

            # Use CMD_USERPIC_WRQ (1107) to upload face photo
            # Protocol: [uid_lo][uid_hi][0x00][photo_size_4_bytes][photo_data...]
            uid_bytes = struct.pack('<H', uid)
            size_bytes = struct.pack('<I', len(photo_data))
            command_string = uid_bytes + b'\x00' + size_bytes + photo_data

            # Send via CMD_PREPARE_DATA first for large payloads
            prep = struct.pack('<IH', len(command_string), 0)
            self.conn._ZK__send_command(1500, prep)  # CMD_PREPARE_DATA
            cmd_response = self.conn._ZK__send_command(1501, command_string)  # CMD_DATA

            logger.info(f"✅ Face photo uploaded: UID={uid}")
            return {'success': True, 'uid': uid, 'size': len(photo_data)}

        except Exception as e:
            logger.error(f"Upload face photo failed: {str(e)}")
            return {'success': False, 'error': str(e)}
    
    def get_device_info(self):
        """Get device information - fast version (no template counting)"""
        try:
            if not self.conn:
                raise Exception("Not connected")
            
            info = {
                'firmware': self.conn.get_firmware_version(),
                'serialnumber': self.conn.get_serialnumber() if hasattr(self.conn, 'get_serialnumber') else None,
                'platform': self.conn.get_platform() if hasattr(self.conn, 'get_platform') else None,
                'device_name': self.conn.get_device_name() if hasattr(self.conn, 'get_device_name') else None,
                'users_count': 0,
                'attendance_count': 0,
                'templates_count': 0,
                'faces_count': 0,
                'faces_cap': 0,
            }
            
            # Get counts from read_sizes
            try:
                self.conn.read_sizes()
                info['users_count'] = self.conn.users
                info['faces_count'] = self.conn.faces
                info['faces_cap'] = self.conn.faces_cap
                info['templates_count'] = self.conn.fingers
                logger.info(f"read_sizes: users={self.conn.users}, faces={self.conn.faces}/{self.conn.faces_cap}, fingers={self.conn.fingers}")
            except Exception as e:
                logger.warning(f"read_sizes failed: {str(e)}")
            
            # Get users count (fast)
            try:
                users = self.conn.get_users()
                info['users_count'] = len(users)
            except Exception as e:
                logger.warning(f"Could not count users: {str(e)}")
            
            # Skip attendance/templates count here - too slow for large devices
            # These counts are fetched during the actual sync steps instead
            
            logger.info(f"Device info retrieved: {info}")
            return info
            
        except Exception as e:
            logger.error(f"Get device info failed: {str(e)}")
            raise


@app.route('/device/debug-face', methods=['POST'])
def debug_face():
    """Debug endpoint to test face photo download - final attempt with raw protocol"""
    import threading
    import socket
    import struct as struct_mod
    
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
        
        conn = service.conn
        conn.read_sizes()
        
        debug_info = {
            'faces': conn.faces,
            'faces_cap': conn.faces_cap,
            'fingers': conn.fingers,
            'users': conn.users,
            'face_version': None,
            'tests': []
        }
        
        try:
            debug_info['face_version'] = conn.get_face_version()
        except Exception as e:
            debug_info['face_version'] = f'error: {str(e)[:100]}'

        # Get users list to find uid for a user with face
        users = conn.get_users()
        user_with_face = None
        for u in users:
            if hasattr(u, 'uid'):
                user_with_face = u
                break
        
        test_uid = user_with_face.uid if user_with_face else 1
        test_pin = user_with_face.user_id if user_with_face else '1'
        debug_info['test_user'] = {'uid': test_uid, 'pin': test_pin}

        # Method 1: CMD_USERTEMP_RRQ (9) - the documented protocol command
        # Protocol format: pack('<Hb', user_sn, finger_index) where finger_index=50 for face
        CMD_USERTEMP_RRQ = 9
        for temp_id in [50, 0]:
            try:
                command_string = struct_mod.pack('<Hb', test_uid, temp_id)
                cmd_response = conn._ZK__send_command(CMD_USERTEMP_RRQ, command_string, response_size=1024+8)
                data_result = None
                try:
                    data_result = conn._ZK__recieve_chunk()
                except:
                    pass
                debug_info['tests'].append({
                    'method': f'CMD_USERTEMP_RRQ(9) uid={test_uid} temp_id={temp_id}',
                    'status': cmd_response.get('status'),
                    'code': cmd_response.get('code'),
                    'response': conn._ZK__response,
                    'data_len': len(data_result) if data_result else 0,
                    'data_hex': data_result[:80].hex() if data_result else 'empty',
                })
            except Exception as e:
                debug_info['tests'].append({
                    'method': f'CMD_USERTEMP_RRQ(9) uid={test_uid} temp_id={temp_id}',
                    'error': str(e)[:200],
                })
            
            # Reconnect if needed
            try:
                service.disconnect()
                import time
                time.sleep(2)
                service = ZKTecoService(ip, port, password)
                if not service.connect():
                    break
                conn = service.conn
            except:
                break

        try:
            service.disconnect()
        except:
            pass
        
        return jsonify({'success': True, 'debug': debug_info})
        
    except Exception as e:
        logger.error(f"Debug face error: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500


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


@app.route('/device/get-all-templates', methods=['POST'])
def get_all_templates():
    """Get ALL templates from device once - much faster than per-user calls"""
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
        
        templates = service.get_all_templates()
        
        service.disconnect()
        
        return jsonify({
            'success': True,
            'templates': templates,
            'count': len(templates)
        })
        
    except Exception as e:
        logger.error(f"Get all templates error: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/get-all-face-templates', methods=['POST'])
def get_all_face_templates():
    """Get ALL face templates from device"""
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

        templates = service.get_all_face_templates()

        service.disconnect()

        return jsonify({
            'success': True,
            'templates': templates,
            'count': len(templates)
        })

    except Exception as e:
        logger.error(f"Get face templates error: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/export-face-template', methods=['POST'])
def export_face_template():
    """Export (upload) a face template to device"""
    try:
        data = request.json

        ip = data.get('ip')
        port = data.get('port', 4370)
        password = data.get('password', 0)
        uid = data.get('uid')
        face_id = data.get('face_id', 50)
        template_data = data.get('template_data')

        if not ip or uid is None or not template_data:
            return jsonify({'success': False, 'error': 'IP, uid, and template_data required'}), 400

        service = ZKTecoService(ip, port, password)

        if not service.connect():
            return jsonify({'success': False, 'error': 'Could not connect'}), 500

        result = service.export_face_template(uid, face_id, template_data)

        service.disconnect()

        return jsonify(result)

    except Exception as e:
        logger.error(f"Export face template error: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/push-face-photo', methods=['POST'])
def push_face_photo():
    """Push a face photo (JPEG) to device"""
    try:
        data = request.json

        ip = data.get('ip')
        port = data.get('port', 4370)
        password = data.get('password', 0)
        uid = data.get('uid')
        photo_base64 = data.get('photo_base64')

        if not ip or uid is None or not photo_base64:
            return jsonify({'success': False, 'error': 'IP, uid, and photo_base64 required'}), 400

        photo_data = base64.b64decode(photo_base64)

        service = ZKTecoService(ip, port, password)

        if not service.connect():
            return jsonify({'success': False, 'error': 'Could not connect'}), 500

        result = service.set_user_face(uid, photo_data)

        service.disconnect()

        return jsonify(result)

    except Exception as e:
        logger.error(f"Push face photo error: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/push-face-photos-batch', methods=['POST'])
def push_face_photos_batch():
    """Push multiple face photos to device"""
    try:
        data = request.json

        ip = data.get('ip')
        port = data.get('port', 4370)
        password = data.get('password', 0)
        photos = data.get('photos', [])

        if not ip or not photos:
            return jsonify({'success': False, 'error': 'IP and photos array required'}), 400

        service = ZKTecoService(ip, port, password)

        if not service.connect():
            return jsonify({'success': False, 'error': 'Could not connect'}), 500

        results = []
        for photo in photos:
            uid = photo.get('uid')
            photo_base64 = photo.get('photo_base64')

            if uid is None or not photo_base64:
                results.append({'uid': uid, 'success': False, 'error': 'Missing uid or photo'})
                continue

            photo_data = base64.b64decode(photo_base64)
            result = service.set_user_face(uid, photo_data)
            results.append(result)

        service.disconnect()

        success_count = sum(1 for r in results if r.get('success'))
        return jsonify({
            'success': True,
            'total': len(photos),
            'success_count': success_count,
            'fail_count': len(photos) - success_count,
            'results': results,
        })

    except Exception as e:
        logger.error(f"Push face photos batch error: {str(e)}")
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
    app.run(host=SERVICE_HOST, port=SERVICE_PORT, debug=False, threaded=True)

