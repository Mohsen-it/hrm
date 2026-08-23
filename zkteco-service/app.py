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
        Export template to device (supports both Fingerprint and Face BioData)
        """
        try:
            if not self.conn:
                raise Exception("Not connected to device")
            
            # Decode base64 template
            template_bytes = base64.b64decode(template_data)
            
            logger.info(f"Exporting template via pyzk: UID={uid}, FID={finger_id}, Size={len(template_bytes)}")
            return self._export_fingerprint_template(uid, finger_id, template_bytes)

        except Exception as e:
            logger.error(f"Template export failed: {str(e)}")
            return {'success': False, 'error': str(e)}

    def _export_face_biodata(self, uid, finger_id, template_bytes):
        """Deprecated face path.

        The previous implementation imported ``CMD_DB_WRQ`` from
        ``zk.const`` which does not exist in this pyzk version, so every
        face export crashed with an ImportError before reaching the
        device.  Face templates are stored through the exact same pyzk
        write as fingerprints (slot = fid, faces live at fid >= 50);
        that is the mechanism proven to work on the iFace880 Plus fleet.
        """
        return self._export_fingerprint_template(uid, finger_id, template_bytes)

    def _export_fingerprint_template(self, uid, finger_id, template_bytes):
        """Standard Fingerprint Export using pyzk's save_user_template"""
        if User is None or Finger is None:
            raise Exception("pyzk classes not available")
            
        users = self.conn.get_users()
        target_user = next((u for u in users if u.uid == int(uid)), None)
        
        if not target_user:
            raise Exception(f"User with UID {uid} not found on device")
        
        # Remove header if present (6 bytes)
        raw_template = template_bytes[6:] if len(template_bytes) > 6 else template_bytes
        
        finger_obj = Finger(uid=int(uid), fid=int(finger_id), valid=1, template=raw_template)
        self.conn.save_user_template(target_user, [finger_obj])
        
        return {'success': True, 'method': 'pyzk_save_user_template'}
    
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
    
    def _save_user_without_deleting_biometrics(self, current_user, uid, user_id, name=None,
                                                password=None, privilege=None, card=None):
        """Write USERINFO at an existing UID without issuing any delete command."""
        old_user_id = str(getattr(current_user, 'user_id', '')).strip() if current_user else ''
        if current_user:
            password = getattr(current_user, 'password', '') if password is None else password
            privilege = getattr(current_user, 'privilege', 0) if privilege is None else privilege
            card = getattr(current_user, 'card', 0) if card is None else card
            name = getattr(current_user, 'name', '') if name is None else name
            group_id = getattr(current_user, 'group_id', '')
        else:
            password = '' if password is None else password
            privilege = 0 if privilege is None else privilege
            card = 0 if card is None else card
            group_id = ''

        if not name:
            raise ValueError("name must not be empty")

        # pyzk's set_user uses CMD_USER_WRQ (SetUser) for this exact UID.
        # Do not replace this with delete_user + set_user: terminals keep
        # fingerprints, face templates and user photos under the UID.
        self.conn.set_user(
            uid=uid,
            name=name,
            privilege=privilege,
            password=password,
            group_id=str(group_id or ''),
            user_id=user_id,
            card=card,
        )

        return {
            'success': True,
            'uid': uid,
            'previous_user_id': old_user_id or None,
            'user_id': user_id,
            'renamed': bool(current_user and old_user_id != user_id),
        }

    def update_user_code(self, uid, user_id, name=None, password=None, privilege=None, card=None):
        """Rename an existing device user while preserving all biometric data.

        A valid, existing device ``uid`` is mandatory. Refusing an unknown UID
        is deliberate: creating a replacement user would leave the employee's
        fingerprints, face templates and photo attached to the old UID.
        """
        try:
            if not self.conn:
                raise Exception("Not connected")

            uid = int(uid)
            if uid <= 0:
                raise ValueError("uid must be a positive existing device UID")
            user_id = str(user_id).strip()
            if not user_id:
                raise ValueError("user_id must not be empty")

            users = self.conn.get_users()
            current_user = next((user for user in users if int(user.uid) == uid), None)
            if current_user is None:
                raise ValueError(f"User with UID {uid} was not found; refusing unsafe replacement")

            duplicate = next(
                (user for user in users if str(getattr(user, 'user_id', '')).strip() == user_id and int(user.uid) != uid),
                None,
            )
            if duplicate:
                raise ValueError(f"Employee code {user_id!r} is already assigned to UID {duplicate.uid}")

            old_user_id = str(getattr(current_user, 'user_id', '')).strip()
            logger.info(
                "Renaming device user at UID=%s: PIN %r -> %r; biometric data is retained",
                uid, old_user_id, user_id,
            )
            return self._save_user_without_deleting_biometrics(
                current_user, uid, user_id, name, password, privilege, card,
            )
        except Exception as e:
            logger.error("Update user code failed: %s", str(e))
            return {'success': False, 'error': str(e)}

    def add_user(self, uid, user_id, name, password=None, privilege=None, card=None):
        """Create a user, or safely update an existing UID without deleting data."""
        try:
            if not self.conn:
                raise Exception("Not connected")

            uid = int(uid)
            user_id = str(user_id).strip()
            if uid <= 0:
                raise ValueError("uid must be a positive device UID")
            if not user_id:
                raise ValueError("user_id must not be empty")

            users = self.conn.get_users()
            current_user = next((user for user in users if int(user.uid) == uid), None)
            if current_user:
                return self.update_user_code(uid, user_id, name, password, privilege, card)

            duplicate = next(
                (user for user in users if str(getattr(user, 'user_id', '')).strip() == user_id),
                None,
            )
            if duplicate:
                raise ValueError(f"Employee code {user_id!r} is already assigned to UID {duplicate.uid}")

            logger.info("Creating device user at UID=%s with PIN %r", uid, user_id)
            return self._save_user_without_deleting_biometrics(
                None, uid, user_id, name, password, privilege, card,
            )
        except Exception as e:
            logger.error("Add user failed: %s", str(e))
            return {'success': False, 'error': str(e)}
    
    def add_users_batch(self, users_data):
        """
        Add multiple users to device efficiently (batch operation)
        Optimized to fetch existing users only once
        
        Args:
            users_data: List of dicts with keys: user_id, name, password,
                privilege, card and, for a code rename, the existing uid or
                previous_user_id (old_user_id is accepted as an alias).
            
        Returns:
            dict with 'success', 'failed', 'errors' keys
        """
        try:
            if not self.conn:
                raise Exception("Not connected")
            
            success_count = 0
            failed_count = 0
            errors = []
            uid_map = {}
            
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
                    user_id = str(user_data.get('user_id', '')).strip()
                    name = user_data.get('name', '')
                    password = user_data.get('password')
                    privilege = user_data.get('privilege')
                    card = user_data.get('card')
                    requested_uid = user_data.get('uid')
                    previous_user_id = str(user_data.get('previous_user_id', user_data.get('old_user_id', ''))).strip()
                    is_rename = bool(user_data.get('rename') or previous_user_id)
                    if not user_id:
                        raise ValueError('user_id must not be empty')
                    
                    # UID is the stable biometric key. A caller changing an
                    # employee code must send uid (or previous_user_id), so we
                    # never allocate a fresh UID and orphan biometric records.
                    if requested_uid is not None and int(requested_uid) > 0:
                        uid = int(requested_uid)
                        if is_rename and uid not in existing_uids:
                            raise ValueError(f'UID {uid} was not found; refusing unsafe employee-code replacement')
                        existing_uids.add(uid)
                    elif previous_user_id and previous_user_id in user_id_to_uid_map:
                        uid = user_id_to_uid_map[previous_user_id]
                    elif user_id in user_id_to_uid_map:
                        # User exists, use their existing UID and data
                        uid = user_id_to_uid_map[user_id]
                        
                        # ✅ استخدام البيانات الموجودة في الجهاز إذا كان المستخدم موجود
                        if user_id in user_id_to_privilege_map and privilege is None:
                            privilege = user_id_to_privilege_map[user_id]
                        if user_id in user_id_to_password_map and password is None:
                            password = user_id_to_password_map[user_id]
                        if user_id in user_id_to_card_map and card is None:
                            card = user_id_to_card_map[user_id]
                            
                        logger.info(f"Using existing data for user_id={user_id}: UID={uid}, privilege={privilege}")
                    elif is_rename:
                        raise ValueError('A rename requires an existing uid or previous_user_id')
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
                    
                    rename_result = self.add_user(uid, user_id, name, password, privilege, card)
                    if not rename_result['success']:
                        raise RuntimeError(rename_result['error'])
                    user_id_to_uid_map[user_id] = uid
                    uid_map[user_id] = uid
                    
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
                # Keep the original response keys and expose the names used
                # by Laravel's batch adapter.
                'success_count': success_count,
                'failed_count': failed_count,
                'uid_map': uid_map,
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
        """Get all templates (fingerprint + face) from device"""
        try:
            if not self.conn:
                raise Exception("Not connected")
            
            # Using get_templates() usually returns fingerprints
            # Modern ZK devices might return faces here too with fid >= 50
            templates = self.conn.get_templates()
            
            result = []
            for template in templates:
                # Repack template with header
                template_data = template.repack() if hasattr(template, 'repack') else template.template
                
                # Determine if it's a face or finger
                # Standard Fingerprint size is around 400-600 bytes
                # Face template size is usually much larger (1000-3000 bytes)
                is_face = template.fid >= 50 or len(template_data) > 1000
                
                result.append({
                    'uid': template.uid,
                    'fid': template.fid,
                    'valid': template.valid,
                    'template': base64.b64encode(template_data).decode('utf-8'),
                    'is_face': is_face
                })
            
            logger.info(f"Retrieved {len(result)} total templates from device (Detected faces based on size/fid)")
            return result
            
        except Exception as e:
            logger.error(f"Get all templates failed: {str(e)}")
            raise

    def get_face_templates(self):
        """Get all face templates using the BioData protocol (Type 2) with Command 1100"""
        try:
            if not self.conn:
                raise Exception("Not connected")
            
            logger.info("Fetching face templates via BioData protocol Type 2 (Command 1100)...")
            
            result = []
            
            # ZK protocol: BioData Type 2 = Face, Type 1 = Fingerprint
            # Command 1100 is specifically for BioData requests
            try:
                # Method 1: Try get_bio_data() which should use Command 1100
                from zk.const import CMD_DB_RRQ
                
                logger.info("Attempting to fetch BioData Type 2 (Face)...")
                
                # Call get_bio_data with Type 2 for Face
                faces = self.conn.get_bio_data(2)
                
                if faces:
                    logger.info(f"BioData Type 2 returned {len(faces)} records")
                    
                    for face in faces:
                        try:
                            template_data = face.repack() if hasattr(face, 'repack') else face.template
                            result.append({
                                'uid': face.uid,
                                'fid': face.fid if hasattr(face, 'fid') else 50,
                                'valid': 1,
                                'template': base64.b64encode(template_data).decode('utf-8'),
                                'is_face': True,
                                'size': len(template_data)
                            })
                        except Exception as face_error:
                            logger.warning(f"Error processing face record: {face_error}")
                            continue
                    
                    logger.info(f"Successfully extracted {len(result)} face templates from BioData Type 2")
                else:
                    logger.warning("get_bio_data(2) returned empty result")
                    
            except Exception as bio_error:
                logger.warning(f"get_bio_data() failed: {bio_error}. Trying fallback...")
                
                # Method 2: Fallback to scanning standard templates for high FID
                logger.info("Trying fallback: scanning standard templates for FID >= 50...")
                templates = self.conn.get_templates()
                
                for template in templates:
                    try:
                        # Check if this might be a face (high FID or large size)
                        if template.fid >= 50 or len(template.template) > 800:
                            template_data = template.repack() if hasattr(template, 'repack') else template.template
                            result.append({
                                'uid': template.uid,
                                'fid': template.fid,
                                'valid': template.valid,
                                'template': base64.b64encode(template_data).decode('utf-8'),
                                'is_face': True,
                                'size': len(template_data)
                            })
                    except Exception as template_error:
                        logger.warning(f"Error processing template: {template_error}")
                        continue
                
                logger.info(f"Fallback scan found {len(result)} potential face templates")
            
            # Method 3: Direct Command 1100 implementation if needed
            if len(result) == 0:
                logger.info("Trying direct Command 1100 implementation...")
                try:
                    # Low-level Command 1100 for BioData Type 2
                    # This sends the raw command to the device
                    from zk.const import CMD_DB_RRQ
                    
                    # Prepare command: [Command][Type][Language][Index][Count]
                    # Type 2 = Face
                    # Language 0 = Auto
                    # Index 0 = Start from beginning
                    # Count 0 = All
                    
                    command = bytes([CMD_DB_RRQ, 2, 0, 0, 0])
                    response = self.conn._send_command(command, 1000)
                    
                    if response and len(response) > 10:
                        logger.info("Command 1100 response received")
                        # Parse response manually
                        # Response format: [Command][Count][Records...]
                        # Each record: [UID][Size][Data...]
                        
                        response_data = response[2:]  # Skip command and count
                        record_count = response_data[0]
                        
                        logger.info(f"Parsing {record_count} BioData records...")
                        
                        pos = 1
                        for i in range(record_count):
                            if pos + 1 > len(response_data):
                                break
                            
                            uid = response_data[pos]
                            size = response_data[pos + 1]
                            pos += 2
                            
                            if pos + size <= len(response_data):
                                template_bytes = response_data[pos:pos + size]
                                result.append({
                                    'uid': uid,
                                    'fid': 50 + i,  # Face FIDs typically 50-54
                                    'valid': 1,
                                    'template': base64.b64encode(template_bytes).decode('utf-8'),
                                    'is_face': True,
                                    'size': size
                                })
                                pos += size
                            
                            if pos >= len(response_data):
                                break
                        
                        logger.info(f"Direct Command 1100 extracted {len(result)} face templates")
                        
                except Exception as cmd_error:
                    logger.error(f"Direct Command 1100 failed: {cmd_error}")
            
            logger.info(f"Total face templates retrieved: {len(result)}")
            return result
            
        except Exception as e:
            logger.error(f"Get face templates failed: {str(e)}")
            logger.error(traceback.format_exc())
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
            }
            
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
        # `password` is the terminal communication key.  Keep a user's own
        # password separate, otherwise changing the employee code could
        # overwrite it with the connection credential.
        user_password = data.get('user_password')
        privilege = data.get('privilege')
        card = data.get('card')
        
        if not all([ip, uid is not None, user_id, name]):
            return jsonify({'success': False, 'error': 'Missing required parameters'}), 400
        
        service = ZKTecoService(ip, port, password)
        
        if not service.connect():
            return jsonify({'success': False, 'error': 'Could not connect'}), 500
        
        result = service.add_user(uid, user_id, name, user_password, privilege, card)
        service.disconnect()
        
        return jsonify({
            'success': result['success'],
            'device_uid': result.get('uid'),
            'renamed': result.get('renamed', False),
            'previous_user_id': result.get('previous_user_id'),
            'error': result.get('error'),
        }), (200 if result['success'] else 422)
        
    except Exception as e:
        logger.error(f"Add user error: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/update-user-code', methods=['POST'])
def update_user_code():
    """Rename an employee code at an existing UID without deleting biometrics.

    Required JSON: ``ip``, ``uid`` and the new ``user_id``.  The endpoint
    intentionally rejects an unknown UID instead of creating another user.
    """
    try:
        data = request.json or {}
        ip, uid, user_id = data.get('ip'), data.get('uid'), data.get('user_id')
        if not ip or uid is None or not user_id:
            return jsonify({'success': False, 'error': 'ip, uid and user_id are required'}), 400

        service = ZKTecoService(ip, data.get('port', 4370), data.get('password', 0))
        if not service.connect():
            return jsonify({'success': False, 'error': 'Could not connect'}), 500
        try:
            result = service.update_user_code(
                uid, user_id, data.get('name'), data.get('user_password'),
                data.get('privilege'), data.get('card'),
            )
        finally:
            service.disconnect()
        return jsonify(result), (200 if result['success'] else 422)
    except Exception as e:
        logger.error("Update user code error: %s", str(e))
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
    """
    Get all face templates from device (fid >= 50).
    
    Request body:
    {
        "ip": "192.168.10.240",
        "port": 4370,
        "password": 0
    }
    """
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
        
        templates = service.get_face_templates()
        
        service.disconnect()
        
        return jsonify({
            'success': True,
            'templates': templates,
            'count': len(templates)
        })
        
    except Exception as e:
        logger.error(f"Get face templates error: {str(e)}")
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


@app.route('/device/export-face-template', methods=['POST'])
def export_face_template():
    """
    Export a single face template to device.
    
    Face templates use finger_id >= 50 (typically 50-54).
    
    Request body:
    {
        "ip": "192.168.10.240",
        "port": 4370,
        "password": 0,
        "uid": 1,
        "finger_id": 50,
        "template_data": "base64..."
    }
    """
    try:
        data = request.json
        
        ip = data.get('ip')
        port = data.get('port', 4370)
        password = data.get('password', 0)
        uid = data.get('uid')
        finger_id = data.get('finger_id', 50)
        template_data = data.get('template_data')
        
        if not ip or not uid or not template_data:
            return jsonify({
                'success': False,
                'error': 'Missing required parameters (ip, uid, template_data)'
            }), 400
        
        service = ZKTecoService(ip, port, password)
        
        if not service.connect():
            return jsonify({
                'success': False,
                'error': 'Could not connect to device'
            }), 500
        
        result = service.export_template(uid, finger_id, template_data)
        service.disconnect()
        
        return jsonify(result)
        
    except Exception as e:
        logger.error(f"Export face template error: {str(e)}")
        return jsonify({
            'success': False,
            'error': str(e),
            'traceback': traceback.format_exc()
        }), 500


@app.route('/device/push-face-templates-batch', methods=['POST'])
def push_face_templates_batch():
    """
    Push face templates to device.
    
    Face templates use finger_id >= 50 (typically 50-54).
    The pyzk library handles them the same way as fingerprint templates.
    
    Request body:
    {
        "ip": "192.168.10.240",
        "port": 4370,
        "password": 0,
        "templates": [
            {
                "uid": 1,
                "user_id": "EMP001",
                "finger_id": 50,
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
                'error': 'Missing required parameters (ip, templates)'
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
        
        for tpl in templates:
            uid = tpl.get('uid')
            finger_id = tpl.get('finger_id', 50)
            template_data = tpl.get('template_data')
            
            if not uid or not template_data:
                failed_count += 1
                results.append({'success': False, 'error': f'Missing uid or template_data'})
                continue
            
            try:
                result = service.export_template(uid, finger_id, template_data)
                results.append(result)
                
                if result.get('success'):
                    success_count += 1
                else:
                    failed_count += 1
            except Exception as e:
                failed_count += 1
                results.append({'success': False, 'error': str(e)})
        
        service.disconnect()
        
        return jsonify({
            'success': True,
            'total': len(templates),
            'success_count': success_count,
            'failed_count': failed_count,
            'results': results
        })
        
    except Exception as e:
        logger.error(f"Push face templates error: {str(e)}")
        return jsonify({
            'success': False,
            'error': str(e),
            'traceback': traceback.format_exc()
        }), 500


@app.route('/device/push-face-photo', methods=['POST'])
def push_face_photo():
    """
    Push a single face photo (JPEG) to device.
    
    Request body:
    {
        "ip": "192.168.10.240",
        "port": 4370,
        "password": 0,
        "uid": 1,
        "photo_base64": "/9j/4AAQ..."
    }
    """
    try:
        data = request.json
        
        ip = data.get('ip')
        port = data.get('port', 4370)
        password = data.get('password', 0)
        uid = data.get('uid')
        photo_base64 = data.get('photo_base64')
        
        if not ip or not uid or not photo_base64:
            return jsonify({
                'success': False,
                'error': 'Missing required parameters (ip, uid, photo_base64)'
            }), 400
        
        service = ZKTecoService(ip, port, password)
        
        if not service.connect():
            return jsonify({
                'success': False,
                'error': 'Could not connect to device'
            }), 500
        
        try:
            import base64
            photo_bytes = base64.b64decode(photo_base64)
            
            user = service.conn.get_user(uid)
            if user:
                user.photo = photo_bytes
                service.conn.save_user(user)
                result = {'success': True, 'message': 'Photo saved'}
            else:
                result = {'success': False, 'error': f'User with uid {uid} not found'}
        except Exception as e:
            result = {'success': False, 'error': str(e)}
        
        service.disconnect()
        
        return jsonify(result)
        
    except Exception as e:
        logger.error(f"Push face photo error: {str(e)}")
        return jsonify({
            'success': False,
            'error': str(e),
            'traceback': traceback.format_exc()
        }), 500


@app.route('/device/push-face-photos-batch', methods=['POST'])
def push_face_photos_batch():
    """
    Push multiple face photos (JPEG) to device.
    
    Request body:
    {
        "ip": "192.168.10.240",
        "port": 4370,
        "password": 0,
        "photos": [
            {
                "uid": 1,
                "photo_base64": "/9j/4AAQ..."
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
        photos = data.get('photos', [])
        
        if not ip or not photos:
            return jsonify({
                'success': False,
                'error': 'Missing required parameters (ip, photos)'
            }), 400
        
        service = ZKTecoService(ip, port, password)
        
        if not service.connect():
            return jsonify({
                'success': False,
                'error': 'Could not connect to device'
            }), 500
        
        success_count = 0
        failed_count = 0
        errors = []
        
        import base64
        for photo_info in photos:
            uid = photo_info.get('uid')
            photo_base64 = photo_info.get('photo_base64')
            
            if not uid or not photo_base64:
                failed_count += 1
                errors.append(f'Missing uid or photo_base64')
                continue
            
            try:
                photo_bytes = base64.b64decode(photo_base64)
                user = service.conn.get_user(uid)
                if user:
                    user.photo = photo_bytes
                    service.conn.save_user(user)
                    success_count += 1
                else:
                    failed_count += 1
                    errors.append(f'User with uid {uid} not found')
            except Exception as e:
                failed_count += 1
                errors.append(str(e))
        
        service.disconnect()
        
        return jsonify({
            'success': True,
            'total': len(photos),
            'success_count': success_count,
            'failed_count': failed_count,
            'errors': errors
        })
        
    except Exception as e:
        logger.error(f"Push face photos batch error: {str(e)}")
        return jsonify({
            'success': False,
            'error': str(e),
            'traceback': traceback.format_exc()
        }), 500


if __name__ == '__main__':
    logger.info("Starting ZKTeco Microservice...")
    logger.info(f"pyzk available: {ZK is not None}")
    
    if ZK is None:
        logger.warning("⚠️ pyzk not installed. Run: pip install pyzk")
        logger.warning("Service will start but template operations will fail")
    
    logger.info(f"Listening on {SERVICE_HOST}:{SERVICE_PORT}")
    app.run(host=SERVICE_HOST, port=SERVICE_PORT, debug=False, threaded=True)
