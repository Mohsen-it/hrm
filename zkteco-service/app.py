#!/usr/bin/env python3
"""
ZKTeco Microservice - High Performance Edition
Fixes: User update UID preservation, Bulk operations, Timeout handling.
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
import logging
import base64
import struct
import os
from datetime import datetime

try:
    from zk import ZK, const
    from zk.user import User
    from zk.finger import Finger
except ImportError:
    print("⚠️ pyzk not installed. Run: pip install pyzk")
    ZK = None

app = Flask(__name__)
CORS(app)

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(name)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

SERVICE_HOST = os.getenv('ZKTECO_PYTHON_SERVICE_HOST', '0.0.0.0')
SERVICE_PORT = int(os.getenv('ZKTECO_PYTHON_SERVICE_PORT', '5000'))


class ZKTecoService:
    def __init__(self, ip, port=4370, password=0, timeout=15, force_udp=False, ommit_ping=True):
        self.ip = ip
        self.port = port
        self.password = password
        self.timeout = timeout  # 15 seconds to prevent hanging
        self.force_udp = force_udp
        self.ommit_ping = ommit_ping
        self.conn = None
        
    def connect(self):
        try:
            if ZK is None:
                raise Exception("pyzk library not installed")
            
            logger.info(f"Connecting to {self.ip}:{self.port} (UDP={self.force_udp}, Timeout={self.timeout}s)")
            zk = ZK(self.ip, port=self.port, timeout=self.timeout, password=self.password, 
                   force_udp=self.force_udp, ommit_ping=self.ommit_ping, encoding='CP1256')
            self.conn = zk.connect()
            
            if self.conn:
                logger.info(f"✅ Connected to {self.ip}:{self.port}")
                return True
            return False
        except Exception as e:
            logger.error(f"Connection failed to {self.ip}: {str(e)}")
            # Fallback to UDP if TCP fails
            if not self.force_udp:
                try:
                    zk = ZK(self.ip, port=self.port, timeout=self.timeout, password=self.password, 
                           force_udp=True, ommit_ping=self.ommit_ping, encoding='CP1256')
                    self.conn = zk.connect()
                    if self.conn: return True
                except Exception as udp_error:
                    logger.error(f"UDP fallback failed: {str(udp_error)}")
            raise
    
    def disconnect(self):
        if self.conn:
            try:
                self.conn.disconnect()
            except:
                pass # Ignore disconnect errors to prevent script crash

    def get_users_map(self):
        """Fetch all users once and return as dictionaries for O(1) lookup"""
        users = self.conn.get_users()
        user_id_map = {str(u.user_id): u for u in users}
        uid_map = {u.uid: u for u in users}
        return user_id_map, uid_map, users

    def add_users_batch(self, users_data):
        """
        Handles Add and Update.
        If 'old_user_id' is provided, it finds the old UID to preserve biometrics.
        """
        try:
            if not self.conn: raise Exception("Not connected")
            success_count, failed_count, errors = 0, 0, []
            
            user_id_map, uid_map, _ = self.get_users_map()
            existing_uids = set(uid_map.keys())
            
            for user_data in users_data:
                try:
                    user_id = str(user_data.get('user_id', ''))
                    old_user_id = str(user_data.get('old_user_id', ''))
                    name = user_data.get('name', '')
                    password = user_data.get('password', '')
                    privilege = user_data.get('privilege', 0)
                    card = user_data.get('card', 0)
                    
                    target_uid = None
                    
                    # 1. Update mode: User ID changed, find by old ID
                    if old_user_id and old_user_id in user_id_map:
                        target_uid = user_id_map[old_user_id].uid
                        logger.info(f"Updating user: old_id={old_user_id} -> new_id={user_id}, UID={target_uid}")
                    
                    # 2. Exists: User ID same, find by current ID
                    elif user_id in user_id_map:
                        target_uid = user_id_map[user_id].uid
                        if not password: password = user_id_map[user_id].password or ''
                        privilege = user_id_map[user_id].privilege
                    
                    # 3. Create mode: New user, assign next available UID
                    else:
                        uid = 1
                        while uid in existing_uids:
                            uid += 1
                        target_uid = uid
                        existing_uids.add(target_uid)
                        logger.info(f"Assigning new UID={target_uid} for user_id={user_id}")
                    
                    self.conn.set_user(
                        uid=target_uid, name=name, privilege=privilege,
                        password=password, group_id='', user_id=user_id, card=card
                    )
                    success_count += 1
                except Exception as e:
                    failed_count += 1
                    errors.append(f"Error saving {user_data.get('name', 'unknown')}: {str(e)}")
            
            return {'success': success_count, 'failed': failed_count, 'errors': errors}
        except Exception as e:
            raise

    def export_templates_batch(self, templates):
        """Upload multiple templates. Fetches users only once."""
        try:
            if not self.conn: raise Exception("Not connected")
            _, uid_map, _ = self.get_users_map()
            
            results = []
            success_count, failed_count = 0, 0
            
            for template_info in templates:
                uid = template_info.get('uid')
                finger_id = template_info.get('finger_id')
                template_data = template_info.get('template_data')
                target_user = uid_map.get(uid)
                
                if target_user and template_data:
                    try:
                        template_bytes = base64.b64decode(template_data)
                        raw_template = template_bytes[6:] if len(template_bytes) >= 6 else template_bytes
                        finger_obj = Finger(uid=uid, fid=finger_id, valid=1, template=raw_template)
                        self.conn.save_user_template(target_user, [finger_obj])
                        results.append({'uid': uid, 'finger_id': finger_id, 'success': True})
                        success_count += 1
                    except Exception as e:
                        results.append({'uid': uid, 'success': False, 'error': str(e)})
                        failed_count += 1
                else:
                    results.append({'uid': uid, 'success': False, 'error': 'User or Template missing'})
                    failed_count += 1
            
            return {'success': True, 'total': len(templates), 'success_count': success_count, 'failed_count': failed_count, 'results': results}
        except Exception as e:
            raise

    def get_attendance(self):
        try:
            if not self.conn: raise Exception("Not connected")
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
            return result
        except Exception as e:
            raise

    def get_all_templates(self):
        """Get all fingerprint templates in one request"""
        try:
            if not self.conn: raise Exception("Not connected")
            templates = self.conn.get_templates()
            result = []
            for template in templates:
                template_data = template.repack() if hasattr(template, 'repack') else template.template
                result.append({
                    'uid': template.uid,
                    'fid': template.fid,
                    'valid': template.valid,
                    'template': base64.b64encode(template_data).decode('utf-8')
                })
            return result
        except Exception as e:
            raise

    def clear_attendance(self):
        try:
            if not self.conn: raise Exception("Not connected")
            self.conn.clear_attendance()
            return True
        except:
            return False

# ================================================================
# API Endpoints
# ================================================================

@app.route('/health', methods=['GET'])
def health_check():
    return jsonify({'status': 'ok', 'pyzk_available': ZK is not None})

@app.route('/device/test-connection', methods=['POST'])
def test_connection():
    try:
        data = request.json
        service = ZKTecoService(data.get('ip'), data.get('port', 4370), data.get('password', 0))
        if service.connect():
            service.disconnect()
            return jsonify({'success': True})
        return jsonify({'success': False, 'error': 'Could not connect'}), 500
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/device/add-users-batch', methods=['POST'])
def add_users_batch():
    """Add or Update users. Send 'old_user_id' to preserve biometrics on ID change."""
    try:
        data = request.json
        service = ZKTecoService(data.get('ip'), data.get('port', 4370), data.get('password', 0))
        if not service.connect():
            return jsonify({'success': False, 'error': 'Could not connect'}), 500
        
        result = service.add_users_batch(data.get('users', []))
        service.disconnect()
        return jsonify({'success': True, **result})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/device/export-templates-batch', methods=['POST'])
def export_templates_batch():
    """Push fingerprints to device. Highly optimized."""
    try:
        data = request.json
        service = ZKTecoService(data.get('ip'), data.get('port', 4370), data.get('password', 0))
        if not service.connect():
            return jsonify({'success': False, 'error': 'Could not connect'}), 500
        
        result = service.export_templates_batch(data.get('templates', []))
        service.disconnect()
        return jsonify(result)
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/device/get-attendance', methods=['POST'])
def get_attendance():
    try:
        data = request.json
        service = ZKTecoService(data.get('ip'), data.get('port', 4370), data.get('password', 0), timeout=20)
        if not service.connect():
            return jsonify({'success': False, 'error': 'Could not connect'}), 500
        
        logs = service.get_attendance()
        try: service.disconnect()
        except: pass
        return jsonify({'success': True, 'attendance': logs, 'count': len(logs)})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/device/get-all-templates', methods=['POST'])
def get_all_templates():
    try:
        data = request.json
        service = ZKTecoService(data.get('ip'), data.get('port', 4370), data.get('password', 0))
        if not service.connect():
            return jsonify({'success': False, 'error': 'Could not connect'}), 500
        templates = service.get_all_templates()
        service.disconnect()
        return jsonify({'success': True, 'templates': templates, 'count': len(templates)})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/device/get-users', methods=['POST'])
def get_users():
    try:
        data = request.json
        service = ZKTecoService(data.get('ip'), data.get('port', 4370), data.get('password', 0))
        if not service.connect():
            return jsonify({'success': False, 'error': 'Could not connect'}), 500
        _, _, users = service.get_users_map()
        service.disconnect()
        
        result = [{'uid': u.uid, 'user_id': u.user_id, 'name': u.name, 'privilege': u.privilege, 'password': u.password if hasattr(u,'password') else '', 'card': u.card if hasattr(u,'card') else 0} for u in users]
        return jsonify({'success': True, 'users': result, 'count': len(result)})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/device/clear-attendance', methods=['POST'])
def clear_attendance():
    try:
        data = request.json
        service = ZKTecoService(data.get('ip'), data.get('port', 4370), data.get('password', 0))
        if not service.connect():
            return jsonify({'success': False, 'error': 'Could not connect'}), 500
        result = service.clear_attendance()
        service.disconnect()
        return jsonify({'success': result})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

if __name__ == '__main__':
    logger.info(f"Starting ZKTeco Microservice on {SERVICE_HOST}:{SERVICE_PORT}")
    # Use Gunicorn in production: gunicorn -w 4 -b 0.0.0.0:5000 app:app
    app.run(host=SERVICE_HOST, port=SERVICE_PORT, debug=False, threaded=True)