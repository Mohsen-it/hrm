#!/usr/bin/env python3
"""
Hikvision ISAPI Microservice
Python Flask service for Hikvision attendance/access control devices.
Uses ISAPI protocol with HTTP Digest Authentication.

Supports:
  - Device info & connection test
  - User management (CRUD)
  - Attendance log retrieval
  - Fingerprint template import/export
  - Face photo download
"""

import json
import logging
import os
import time
import traceback
import base64
import xml.etree.ElementTree as ET
from datetime import datetime, timedelta
from urllib.parse import urljoin

import requests
from requests.auth import HTTPDigestAuth
from flask import Flask, request, jsonify
from flask_cors import CORS

app = Flask(__name__)
CORS(app)

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(name)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

SERVICE_HOST = os.getenv('HIKVISION_SERVICE_HOST', '0.0.0.0')
SERVICE_PORT = int(os.getenv('HIKVISION_SERVICE_PORT', '5001'))


class HikvisionService:
    """Hikvision ISAPI Device Service"""

    def __init__(self, ip, port=80, username='admin', password='', timeout=30):
        self.ip = ip
        self.port = port
        self.username = username
        self.password = password
        self.timeout = timeout
        self.base_url = f"http://{self.ip}:{self.port}"
        self.auth = HTTPDigestAuth(self.username, self.password)
        self.session = requests.Session()
        self.session.auth = self.auth
        self.session.verify = False
        self.session.headers.update({'Content-Type': 'application/json'})

    def _isapi(self, path, method='GET', data=None, params=None, raw=False):
        """Make an ISAPI request"""
        url = f"{self.base_url}/ISAPI{path}"
        try:
            resp = self.session.request(
                method, url,
                json=data,
                params=params,
                timeout=self.timeout
            )
            if resp.status_code == 401:
                raise Exception("Authentication failed - check username/password")
            if resp.status_code >= 400:
                raise Exception(f"ISAPI error {resp.status_code}: {resp.text[:500]}")
            if raw:
                return resp
            ct = resp.headers.get('Content-Type', '')
            if 'json' in ct:
                return resp.json()
            if 'xml' in ct:
                return self._parse_xml(resp.text)
            return resp.text
        except requests.exceptions.ConnectionError:
            raise Exception(f"Cannot connect to {self.base_url}")
        except requests.exceptions.Timeout:
            raise Exception(f"Connection timeout after {self.timeout}s")

    def _parse_xml(self, xml_text):
        """Parse ISAPI XML response to dict"""
        try:
            root = ET.fromstring(xml_text)
            return self._xml_to_dict(root)
        except ET.ParseError:
            return {'raw': xml_text}

    def _xml_to_dict(self, element):
        """Convert XML element to dictionary"""
        result = {}
        if element.attrib:
            result['@attributes'] = dict(element.attrib)
        children = list(element)
        if not children:
            text = (element.text or '').strip()
            if text:
                return text
            return result
        for child in children:
            child_dict = self._xml_to_dict(child)
            tag = child.tag.split('}')[-1] if '}' in child.tag else child.tag
            if tag in result:
                if not isinstance(result[tag], list):
                    result[tag] = [result[tag]]
                result[tag].append(child_dict)
            else:
                result[tag] = child_dict
        return result

    def test_connection(self):
        """Test connection to device"""
        try:
            info = self._isapi('/System/deviceInfo')
            return {
                'connected': True,
                'device_name': info.get('deviceName', ''),
                'serial_number': info.get('serialNumber', ''),
                'model': info.get('model', ''),
                'firmware': info.get('firmwareVersion', ''),
                'manufacturer': info.get('manufacturer', ''),
            }
        except Exception as e:
            return {'connected': False, 'error': str(e)}

    def get_device_info(self):
        """Get full device information"""
        try:
            info = self._isapi('/System/deviceInfo')

            user_count = self._get_user_count()
            fp_count = self._get_fingerprint_count()

            return {
                'success': True,
                'info': {
                    'firmware': info.get('firmwareVersion', ''),
                    'serialnumber': info.get('serialNumber', ''),
                    'platform': info.get('model', ''),
                    'device_name': info.get('deviceName', ''),
                    'manufacturer': info.get('manufacturer', ''),
                    'users_count': user_count,
                    'fingerprint_count': fp_count,
                    'attendance_count': 0,
                }
            }
        except Exception as e:
            return {'success': False, 'error': str(e)}

    def _get_user_count(self):
        """Get total user count from device"""
        try:
            result = self._isapi('/AccessControl/UserInfo/Count', params={'format': 'json'})
            logger.info(f"User count response: {result}")
            if isinstance(result, dict):
                return int(result.get('UserInfoCount', {}).get('numOfMatches', 0))
            return 0
        except Exception as e:
            logger.error(f"Get user count failed: {e}")
            return 0

    def _get_fingerprint_count(self):
        """Get total fingerprint count"""
        users = self.get_users()
        total = 0
        for u in users:
            total += u.get('num_of_fp', 0)
        return total

    def get_users(self, max_per_page=32):
        """Get all users from device with pagination and retry on auth failure.
        Creates a fresh session on each page to avoid throttling."""
        all_users = []
        position = 0
        seen_ids = set()
        total_matches = 0
        page = 0
        max_retries = 8

        while True:
            search_cond = {
                'UserInfoSearchCond': {
                    'searchID': '1',
                    'searchResultPosition': position,
                    'maxResults': max_per_page,
                    'userType': 'all',
                }
            }

            success = False
            for attempt in range(1, max_retries + 1):
                try:
                    # Create fresh session for each attempt
                    self.session = requests.Session()
                    self.session.auth = HTTPDigestAuth(self.auth.username, self.auth.password)
                    self.session.verify = False
                    self.session.headers.update({'Content-Type': 'application/json'})

                    result = self._isapi('/AccessControl/UserInfo/search', method='POST',
                                         data=search_cond, params={'format': 'json'})
                    success = True
                    break
                except Exception as e:
                    wait = min(5 * attempt, 30)
                    logger.error(f"Error at position {position} (attempt {attempt}/{max_retries}): {e}. Waiting {wait}s...")
                    time.sleep(wait)

            if not success:
                logger.error(f"Failed to fetch position {position} after {max_retries} attempts, stopping")
                break

            search_info = result.get('UserInfoSearch', {})
            users = search_info.get('UserInfo', [])
            if isinstance(users, dict):
                users = [users]

            total_matches = int(search_info.get('totalMatches', 0))
            response_status = search_info.get('responseStatusStrg', '')
            page += 1

            logger.info(f"get_users: position={position}, page={page}, page_size={len(users)}, total={total_matches}, status={response_status}")

            new_in_page = 0
            for user in users:
                eno = user.get('employeeNo', '')
                if eno in seen_ids:
                    continue
                seen_ids.add(eno)
                new_in_page += 1

                valid_info = user.get('Valid', {})
                if isinstance(valid_info, dict):
                    valid_begin = valid_info.get('beginTime', '')
                    valid_end = valid_info.get('endTime', '')
                else:
                    valid_begin = ''
                    valid_end = ''

                all_users.append({
                    'uid': int(user.get('employeeNo', 0)) if str(user.get('employeeNo', '')).isdigit() else hash(user.get('employeeNo', '')) % (2**31),
                    'user_id': user.get('employeeNo', ''),
                    'name': user.get('name', ''),
                    'privilege': 0,
                    'password': '',
                    'card': 0,
                    'gender': user.get('gender', 'unknown'),
                    'num_of_fp': int(user.get('numOfFP', 0)),
                    'num_of_face': int(user.get('numOfFace', 0)),
                    'num_of_card': int(user.get('numOfCard', 0)),
                    'user_type': user.get('userType', 'normal'),
                    'valid_begin': valid_begin,
                    'valid_end': valid_end,
                })

            if response_status != 'MORE' or position >= total_matches:
                break

            position += max_per_page
            time.sleep(1)

        logger.info(f"Retrieved {len(all_users)} unique users from device (total_matches={total_matches})")
        return all_users

    def add_user(self, employee_no, name, password='', gender='unknown', user_type='normal'):
        """Add a new user to device"""
        user_data = {
            'UserInfo': {
                'employeeNo': str(employee_no),
                'name': name,
                'userType': user_type,
                'Valid': {
                    'enable': True,
                    'beginTime': '2020-01-01T00:00:00',
                    'endTime': '2037-12-31T23:59:59',
                    'timeType': 'local',
                },
                'doorRight': '1',
                'RightPlan': [{'doorNo': 1, 'planTemplateNo': '1'}],
                'gender': gender,
            }
        }
        if password:
            user_data['UserInfo']['password'] = password

        try:
            self._isapi('/AccessControl/UserInfo/Record', method='POST',
                        data=user_data, params={'format': 'json'})
            return True
        except Exception as e:
            logger.error(f"Add user failed: {e}")
            return False

    def delete_user(self, employee_no):
        """Delete a user from device"""
        data = {
            'UserInfoDelCond': {
                'EmployeeNoList': [{'employeeNo': str(employee_no)}]
            }
        }
        try:
            self._isapi('/AccessControl/UserInfo/Delete', method='PUT',
                        data=data, params={'format': 'json'})
            return True
        except Exception as e:
            logger.error(f"Delete user failed: {e}")
            return False

    def get_attendance(self, start_time=None, end_time=None, max_per_page=32):
        """Get attendance records with pagination and time chunking.
        Queries all minor event types (card, fingerprint, face, password, combos).
        """
        if not start_time:
            start_time = (datetime.now() - timedelta(days=30)).strftime('%Y-%m-%dT%H:%M:%S+03:00')
        if not end_time:
            end_time = datetime.now().strftime('%Y-%m-%dT%H:%M:%S+03:00')

        # Hikvision requires major+minor. minor values for access control (major=5):
        # 1=card, 3=card+pw, 4=card+fp, 5=card+face, 7=fp, 8=fp+pw,
        # 9=face+fp, 10=face+pw, 11=fp+pw, 75=face, 77=face+fp+pw
        minor_types = [1, 3, 4, 5, 7, 8, 9, 10, 11, 75, 77]
        all_records = []
        seen_serials = set()

        for minor in minor_types:
            position = 0
            while True:
                search_cond = {
                    'AcsEventCond': {
                        'searchID': '1',
                        'searchResultPosition': position,
                        'maxResults': max_per_page,
                        'major': 5,
                        'minor': minor,
                        'startTime': start_time,
                        'endTime': end_time,
                    }
                }

                try:
                    result = self._isapi('/AccessControl/AcsEvent', method='POST',
                                         data=search_cond, params={'format': 'json'})
                except Exception as e:
                    logger.error(f"Error fetching attendance (minor={minor}) at position {position}: {e}")
                    break

                acs_event = result.get('AcsEvent', {})
                records = acs_event.get('InfoList', [])
                if isinstance(records, dict):
                    records = [records]

                total_matches = int(acs_event.get('totalMatches', 0))
                response_status = acs_event.get('responseStatusStrg', '')

                for rec in records:
                    serial = rec.get('serialNo', 0)
                    if serial in seen_serials:
                        continue
                    seen_serials.add(serial)

                    all_records.append({
                        'uid': 0,
                        'user_id': rec.get('employeeNoString', ''),
                        'timestamp': rec.get('time', ''),
                        'status': str(rec.get('major', '')),
                        'punch': self._map_verify_mode(rec.get('currentVerifyMode', '')),
                        'attendance_status': rec.get('attendanceStatus', ''),
                        'name': rec.get('name', ''),
                        'card_no': rec.get('cardNo', ''),
                        'door_no': rec.get('doorNo', ''),
                        'major': rec.get('major', 0),
                        'minor': rec.get('minor', 0),
                        'serial_no': serial,
                        'verify_mode': rec.get('currentVerifyMode', ''),
                    })

                if response_status != 'MORE' or position >= total_matches:
                    break
                position += len(records)

        logger.info(f"Retrieved {len(all_records)} attendance records")
        return all_records

    def _map_verify_mode(self, mode_str):
        """Map Hikvision verify mode to punch type (0=checkin, 1=checkout)"""
        mode = mode_str.lower() if mode_str else ''
        if 'face' in mode or 'fp' in mode or 'fingerprint' in mode or 'card' in mode or 'password' in mode:
            return 0
        return 0

    def get_fingerprint_templates(self, employee_no):
        """Get fingerprint templates for a specific user via FingerPrintUpload POST"""
        try:
            data = {
                'FingerPrintCond': {
                    'searchID': '1',
                    'employeeNo': str(employee_no),
                }
            }

            result = self._isapi('/AccessControl/FingerPrintUpload', method='POST',
                                 data=data, params={'format': 'json'})

            fp_info = result.get('FingerPrintInfo', {})
            fp_list = fp_info.get('FingerPrintList', [])
            if isinstance(fp_list, dict):
                fp_list = [fp_list]

            templates = []
            for fp in fp_list:
                templates.append({
                    'uid': int(employee_no) if str(employee_no).isdigit() else 0,
                    'fid': int(fp.get('fingerPrintID', 0)),
                    'valid': 1,
                    'template': fp.get('fingerData', ''),
                    'finger_type': fp.get('fingerType', 'normalFP'),
                })

            return templates
        except Exception as e:
            logger.error(f"Get fingerprints for {employee_no} failed: {e}")
            return []

    def _is_base64(self, s):
        """Check if string is valid base64"""
        try:
            if isinstance(s, str) and len(s) > 0:
                base64.b64decode(s)
                return True
        except Exception:
            pass
        return False

    def get_user_detail(self, employee_no):
        """Get single user detail including Valid block from ISAPI.
        Tries raw XML search to find Valid block."""
        # Try raw XML search with format removed
        try:
            search_cond = {
                'UserInfoSearchCond': {
                    'searchID': '1',
                    'searchResultPosition': 0,
                    'maxResults': 300,
                    'userType': 'all',
                }
            }
            resp = self._isapi('/AccessControl/UserInfo/search', method='POST',
                               data=search_cond, raw=True)
            ct = resp.headers.get('Content-Type', '')
            if 'xml' in ct:
                parsed = self._parse_xml(resp.text)
                search_info = parsed.get('UserInfoSearch', {})
                users = search_info.get('UserInfo', [])
                if isinstance(users, dict):
                    users = [users]
                for u in users:
                    if u.get('employeeNo') == str(employee_no):
                        valid_info = u.get('Valid', {})
                        if isinstance(valid_info, dict):
                            return {
                                'user_id': u.get('employeeNo', str(employee_no)),
                                'name': u.get('name', ''),
                                'valid_begin': valid_info.get('beginTime', ''),
                                'valid_end': valid_info.get('endTime', ''),
                                'valid_enable': valid_info.get('enable', True),
                            }
            logger.info(f"XML search did not contain Valid block for {employee_no}, CT={ct}")
        except Exception as e:
            logger.warning(f"XML search for {employee_no} failed: {e}")

        return {
            'user_id': str(employee_no),
            'valid_begin': '',
            'valid_end': '',
            'valid_enable': True,
        }

    def get_all_user_validity(self, max_per_page=300):
        """Get all users with validity info in a single XML call."""
        all_users = []
        position = 0
        while True:
            search_cond = {
                'UserInfoSearchCond': {
                    'searchID': '1',
                    'searchResultPosition': position,
                    'maxResults': max_per_page,
                    'userType': 'all',
                }
            }
            try:
                resp = self._isapi('/AccessControl/UserInfo/search', method='POST',
                                   data=search_cond, raw=True)
                ct = resp.headers.get('Content-Type', '')
                if 'xml' in ct:
                    parsed = self._parse_xml(resp.text)
                    search_info = parsed.get('UserInfoSearch', {})
                    users = search_info.get('UserInfo', [])
                    if isinstance(users, dict):
                        users = [users]
                    for u in users:
                        valid_info = u.get('Valid', {})
                        all_users.append({
                            'user_id': u.get('employeeNo', ''),
                            'name': u.get('name', ''),
                            'valid_begin': valid_info.get('beginTime', '') if isinstance(valid_info, dict) else '',
                            'valid_end': valid_info.get('endTime', '') if isinstance(valid_info, dict) else '',
                        })
                    total = int(search_info.get('totalMatches', 0))
                    status = search_info.get('responseStatusStrg', '')
                    if status != 'MORE' or position >= total:
                        break
                    position += len(users)
                else:
                    break
            except Exception as e:
                logger.error(f"get_all_user_validity failed at position {position}: {e}")
                break
        logger.info(f"Retrieved validity info for {len(all_users)} users")
        return all_users

    def get_all_templates(self):
        """Get all fingerprint templates from all users"""
        users = self.get_users()
        all_templates = []
        for user in users:
            uid = user.get('user_id', '')
            if uid:
                try:
                    templates = self.get_fingerprint_templates(uid)
                    all_templates.extend(templates)
                except Exception as e:
                    logger.warning(f"Skip fingerprints for {uid}: {e}")
        logger.info(f"Retrieved {len(all_templates)} total fingerprint templates")
        return all_templates

    def set_fingerprint_template(self, employee_no, finger_id, template_data):
        """Set fingerprint template for a user"""
        fp_data = {
            'Fingerprint': {
                'fingerID': finger_id,
                'enable': True,
                'templateBin': template_data,
            }
        }
        try:
            path = f'/AccessControl/UserInfo/{employee_no}/Fingerprint'
            self._isapi(path, method='POST', data=fp_data, params={'format': 'json'})
            return True
        except Exception as e:
            logger.error(f"Set fingerprint for {employee_no} finger {finger_id} failed: {e}")
            return False

    def get_face_photos(self, max_per_page=32):
        """Get face photo URLs and feature data for all users"""
        all_photos = []
        position = 0

        while True:
            data = {
                'faceLibType': 'blackFD',
                'FDID': '1',
                'searchID': '1',
                'searchResultPosition': position,
                'maxResults': max_per_page,
            }

            try:
                result = self._isapi('/Intelligent/FDLib/FDSearch', method='POST',
                                     data=data, params={'format': 'json'})
            except Exception as e:
                logger.error(f"Error fetching face photos at position {position}: {e}")
                break

            total_matches = int(result.get('totalMatches', 0))
            response_status = result.get('responseStatusStrg', '')
            matches = result.get('MatchList', [])
            if isinstance(matches, dict):
                matches = [matches]

            for m in matches:
                face_url = m.get('faceURL', '')
                # Make URL absolute if relative
                if face_url and not face_url.startswith('http'):
                    face_url = f"http://{self.ip}:{self.port}{face_url}"

                all_photos.append({
                    'employee_no': m.get('FPID', ''),
                    'face_url': face_url,
                    'model_data': m.get('modelData', ''),
                })

            if response_status != 'MORE' or position >= total_matches:
                break
            position += len(matches)

        logger.info(f"Retrieved {len(all_photos)} face photos")
        return all_photos

    def download_face_photo(self, face_url):
        """Download a face photo by URL, return base64 encoded"""
        try:
            resp = self.session.get(face_url, timeout=self.timeout)
            if resp.status_code == 200 and resp.content:
                return base64.b64encode(resp.content).decode('utf-8')
            # Retry with fresh session if first attempt failed
            if resp.status_code != 200:
                fresh_session = requests.Session()
                fresh_session.auth = HTTPDigestAuth(self.username, self.password)
                fresh_session.verify = False
                resp2 = fresh_session.get(face_url, timeout=self.timeout)
                if resp2.status_code == 200 and resp2.content:
                    self.session = fresh_session
                    return base64.b64encode(resp2.content).decode('utf-8')
            logger.warning(f"Download face photo returned status {resp.status_code}, content_len={len(resp.content)}")
            return None
        except Exception as e:
            logger.error(f"Download face photo failed: {e}")
            return None

    def clear_attendance(self):
        """Clear all attendance logs from device"""
        try:
            self._isapi('/AccessControl/AcsEvent', method='DELETE',
                        params={'format': 'json'})
            return True
        except Exception as e:
            logger.error(f"Clear attendance failed: {e}")
            return False


# ===== API Endpoints =====

@app.route('/health', methods=['GET'])
def health_check():
    return jsonify({
        'status': 'ok',
        'service': 'Hikvision ISAPI Microservice',
        'version': '1.0.0',
    })


@app.route('/device/test-connection', methods=['POST'])
def test_connection():
    try:
        data = request.json
        ip = data.get('ip')
        port = data.get('port', 80)
        username = data.get('username', 'admin')
        password = data.get('password', '')

        if not ip:
            return jsonify({'success': False, 'error': 'IP required'}), 400

        service = HikvisionService(ip, port, username, password, data.get('timeout', 30))
        result = service.test_connection()

        return jsonify({'success': result.get('connected', False), **result})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/info', methods=['POST'])
def get_device_info():
    try:
        data = request.json
        ip = data.get('ip')
        port = data.get('port', 80)
        username = data.get('username', 'admin')
        password = data.get('password', '')

        if not ip:
            return jsonify({'success': False, 'error': 'IP required'}), 400

        service = HikvisionService(ip, port, username, password, data.get('timeout', 30))
        return jsonify(service.get_device_info())
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/get-users', methods=['POST'])
def get_users():
    try:
        data = request.json
        ip = data.get('ip')
        port = data.get('port', 80)
        username = data.get('username', 'admin')
        password = data.get('password', '')

        if not ip:
            return jsonify({'success': False, 'error': 'IP required'}), 400

        service = HikvisionService(ip, port, username, password, data.get('timeout', 30))

        # Also try raw XML to check if Valid block is included
        search_cond = {
            'UserInfoSearchCond': {
                'searchID': '1',
                'searchResultPosition': 0,
                'maxResults': 1,
                'userType': 'all',
            }
        }
        try:
            resp = service._isapi('/AccessControl/UserInfo/search', method='POST',
                                   data=search_cond, raw=True)
            logger.info(f"Raw XML search response (first user): {resp.text[:2000]}")
        except Exception as e:
            logger.error(f"Raw XML search failed: {e}")

        users = service.get_users()

        return jsonify({
            'success': True,
            'users': users,
            'count': len(users)
        })
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/debug-search', methods=['POST'])
def debug_search():
    """Debug: fetch users from a specific position"""
    try:
        data = request.json
        ip = data.get('ip')
        port = data.get('port', 80)
        username = data.get('username', 'admin')
        password = data.get('password', '')
        timeout = data.get('timeout', 30)
        start_pos = data.get('start_position', 0)
        max_results = data.get('max_results', 32)

        if not ip:
            return jsonify({'success': False, 'error': 'IP required'}), 400

        service = HikvisionService(ip, port, username, password, timeout)

        search_cond = {
            'UserInfoSearchCond': {
                'searchID': '1',
                'searchResultPosition': start_pos,
                'maxResults': max_results,
                'userType': 'all',
            }
        }

        result = service._isapi('/AccessControl/UserInfo/search', method='POST',
                                 data=search_cond, params={'format': 'json'})

        search_info = result.get('UserInfoSearch', {})
        users = search_info.get('UserInfo', [])
        if isinstance(users, dict):
            users = [users]

        total_matches = int(search_info.get('totalMatches', 0))
        response_status = search_info.get('responseStatusStrg', '')

        user_list = []
        for u in users:
            valid = u.get('Valid', {})
            user_list.append({
                'employeeNo': u.get('employeeNo', ''),
                'name': u.get('name', ''),
                'valid_end': valid.get('endTime', '') if isinstance(valid, dict) else '',
                'numOfFP': u.get('numOfFP', 0),
            })

        return jsonify({
            'success': True,
            'position': start_pos,
            'total_matches': total_matches,
            'status': response_status,
            'users_returned': len(users),
            'users': user_list,
        })
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/get-user-detail', methods=['POST'])
def get_user_detail():
    try:
        data = request.json
        ip = data.get('ip')
        port = data.get('port', 80)
        username = data.get('username', 'admin')
        password = data.get('password', '')
        employee_no = data.get('employee_no')

        if not all([ip, employee_no]):
            return jsonify({'success': False, 'error': 'IP and employee_no required'}), 400

        service = HikvisionService(ip, port, username, password, data.get('timeout', 30))
        detail = service.get_user_detail(employee_no)

        return jsonify({
            'success': True,
            'detail': detail,
        })
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/get-user-details-batch', methods=['POST'])
def get_user_details_batch():
    try:
        data = request.json
        ip = data.get('ip')
        port = data.get('port', 80)
        username = data.get('username', 'admin')
        password = data.get('password', '')
        employee_nos = data.get('employee_nos', [])

        if not all([ip, employee_nos]):
            return jsonify({'success': False, 'error': 'IP and employee_nos required'}), 400

        service = HikvisionService(ip, port, username, password, data.get('timeout', 30))

        # Fetch all users with validity in a single XML call
        all_validity = service.get_all_user_validity()
        validity_map = {str(v['user_id']): v for v in all_validity}

        details = []
        for eno in employee_nos:
            detail = validity_map.get(str(eno), {
                'user_id': str(eno),
                'valid_begin': '',
                'valid_end': '',
            })
            details.append(detail)

        return jsonify({
            'success': True,
            'details': details,
            'count': len(details),
        })
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500
def add_user():
    try:
        data = request.json
        ip = data.get('ip')
        port = data.get('port', 80)
        username = data.get('username', 'admin')
        password = data.get('password', '')
        employee_no = data.get('employee_no') or data.get('user_id')
        name = data.get('name', '')

        if not all([ip, employee_no, name]):
            return jsonify({'success': False, 'error': 'Missing required parameters'}), 400

        service = HikvisionService(ip, port, username, password, data.get('timeout', 30))
        result = service.add_user(
            employee_no=employee_no,
            name=name,
            password=data.get('password_value', ''),
            gender=data.get('gender', 'unknown'),
            user_type=data.get('user_type', 'normal'),
        )

        return jsonify({'success': result})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/delete-user', methods=['POST'])
def delete_user():
    try:
        data = request.json
        ip = data.get('ip')
        port = data.get('port', 80)
        username = data.get('username', 'admin')
        password = data.get('password', '')
        employee_no = data.get('employee_no') or data.get('uid')

        if not all([ip, employee_no]):
            return jsonify({'success': False, 'error': 'Missing required parameters'}), 400

        service = HikvisionService(ip, port, username, password, data.get('timeout', 30))
        result = service.delete_user(str(employee_no))

        return jsonify({'success': result})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/get-attendance', methods=['POST'])
def get_attendance():
    try:
        data = request.json
        ip = data.get('ip')
        port = data.get('port', 80)
        username = data.get('username', 'admin')
        password = data.get('password', '')
        start_time = data.get('start_time')
        end_time = data.get('end_time')

        if not ip:
            return jsonify({'success': False, 'error': 'IP required'}), 400

        service = HikvisionService(ip, port, username, password, data.get('timeout', 30))
        logs = service.get_attendance(start_time, end_time)

        return jsonify({
            'success': True,
            'attendance': logs,
            'count': len(logs)
        })
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/get-templates', methods=['POST'])
def get_templates():
    try:
        data = request.json
        ip = data.get('ip')
        port = data.get('port', 80)
        username = data.get('username', 'admin')
        password = data.get('password', '')
        uid = data.get('uid') or data.get('employee_no')

        if not ip:
            return jsonify({'success': False, 'error': 'IP required'}), 400

        service = HikvisionService(ip, port, username, password, data.get('timeout', 30))

        if uid:
            templates = service.get_fingerprint_templates(str(uid))
        else:
            templates = service.get_all_templates()

        return jsonify({
            'success': True,
            'templates': templates,
            'count': len(templates)
        })
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/export-template', methods=['POST'])
def export_template():
    try:
        data = request.json
        ip = data.get('ip')
        port = data.get('port', 80)
        username = data.get('username', 'admin')
        password = data.get('password', '')
        employee_no = data.get('employee_no') or data.get('uid')
        finger_id = data.get('finger_id', 0)
        template_data = data.get('template_data', '')

        if not all([ip, employee_no, template_data]):
            return jsonify({'success': False, 'error': 'Missing required parameters'}), 400

        service = HikvisionService(ip, port, username, password, data.get('timeout', 30))
        result = service.set_fingerprint_template(str(employee_no), finger_id, template_data)

        return jsonify({'success': result})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/get-face-photos', methods=['POST'])
def get_face_photos():
    try:
        data = request.json
        ip = data.get('ip')
        port = data.get('port', 80)
        username = data.get('username', 'admin')
        password = data.get('password', '')

        if not ip:
            return jsonify({'success': False, 'error': 'IP required'}), 400

        service = HikvisionService(ip, port, username, password, data.get('timeout', 30))
        photos = service.get_face_photos()

        # Optionally download photo images
        download = data.get('download', False)
        if download:
            import time
            for i, photo in enumerate(photos):
                if photo.get('face_url'):
                    b64 = service.download_face_photo(photo['face_url'])
                    photo['photo_base64'] = b64 or ''
                    if (i + 1) % 50 == 0:
                        logger.info(f"Downloaded {i + 1}/{len(photos)} face photos")
                    time.sleep(0.05)  # Small delay to avoid overwhelming device

        return jsonify({
            'success': True,
            'photos': photos,
            'count': len(photos)
        })
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/download-face-photo', methods=['POST'])
def download_face_photo():
    try:
        data = request.json
        ip = data.get('ip')
        port = data.get('port', 80)
        username = data.get('username', 'admin')
        password = data.get('password', '')
        face_url = data.get('face_url', '')

        if not all([ip, face_url]):
            return jsonify({'success': False, 'error': 'IP and face_url required'}), 400

        service = HikvisionService(ip, port, username, password, data.get('timeout', 30))
        b64 = service.download_face_photo(face_url)

        if b64:
            return jsonify({'success': True, 'photo_base64': b64})
        return jsonify({'success': False, 'error': 'Failed to download'}), 500
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/device/clear-attendance', methods=['POST'])
def clear_attendance():
    try:
        data = request.json
        ip = data.get('ip')
        port = data.get('port', 80)
        username = data.get('username', 'admin')
        password = data.get('password', '')

        if not ip:
            return jsonify({'success': False, 'error': 'IP required'}), 400

        service = HikvisionService(ip, port, username, password, data.get('timeout', 30))
        result = service.clear_attendance()

        return jsonify({'success': result})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500


if __name__ == '__main__':
    import urllib3
    urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

    logger.info("Starting Hikvision ISAPI Microservice...")
    logger.info(f"Listening on {SERVICE_HOST}:{SERVICE_PORT}")
    app.run(host=SERVICE_HOST, port=SERVICE_PORT, debug=False)
