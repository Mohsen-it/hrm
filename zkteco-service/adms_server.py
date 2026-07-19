#!/usr/bin/env python3
"""
ZKTeco ADMS HTTP Server
Handles HTTP push protocol from ZKTeco iClock devices.
Device sends POST with attendance data after GET handshake.
Usage: python adms_server.py --port 9000 --laravel http://127.0.0.1:8000
"""

import argparse
import json
import logging
import urllib.request
from http.server import HTTPServer, BaseHTTPRequestHandler

logging.basicConfig(level=logging.INFO, format='%(asctime)s [%(levelname)s] %(message)s')
logger = logging.getLogger('adms')

LARAVEL_URL = 'http://127.0.0.1:8000'


def forward_to_laravel(body_text, serial, ip):
    """Send attendance data to Laravel push endpoint."""
    if not body_text or not body_text.strip():
        return False
    
    try:
        body_line = f"ATT\t\t{body_text.strip()}\n"
        data = json.dumps({
            'SN': serial,
            'Body': body_line
        }).encode('utf-8')

        req = urllib.request.Request(
            f'{LARAVEL_URL}/api/attendance-integration/push/adms',
            data=data,
            headers={'Content-Type': 'application/json'}
        )

        with urllib.request.urlopen(req, timeout=10) as resp:
            result = json.loads(resp.read().decode())
            logger.info(f'Forwarded to Laravel: received={result.get("received")}, processed={result.get("processed")}')
            return True
    except Exception as e:
        logger.error(f'Forward failed: {e}')
        return False


class ADMSHandler(BaseHTTPRequestHandler):
    def log_message(self, format, *args):
        logger.info(f'{self.client_address[0]} - {format % args}')

    def do_GET(self):
        """Handle GET /iclock/getrequest?SN=... or /iclock/cdata?SN=..."""
        serial = 'MED7254500092'
        if 'SN=' in self.path:
            for part in self.path.split('&'):
                if part.startswith('SN='):
                    serial = part.split('=', 1)[-1]
                    break

        logger.info(f'GET {self.path} from {self.client_address[0]}')

        # ZKTeco push protocol response
        response = (
            f'GET OPTION FROM: {serial}\r\n'
            'ATTLOGStamp=None\r\n'
            'OPERLOGStamp=None\r\n'
            'ATTPHOTOStamp=None\r\n'
            'ErrorDelay=60\r\n'
            'Delay=30\r\n'
            'TransTimes=00:00;23:59\r\n'
            'TransInterval=1\r\n'
            'TransFlag=1111111111\r\n'
            'TimeZone=3\r\n'
            'Realtime=1\r\n'
            'Encrypt=0\r\n'
        )

        self.send_response(200)
        self.send_header('Content-Type', 'text/plain')
        self.end_headers()
        self.wfile.write(response.encode())

    def do_POST(self):
        """Handle POST /iclock/cdata?SN=... with attendance data."""
        content_length = int(self.headers.get('Content-Length', 0))
        body = self.rfile.read(content_length) if content_length else b''

        serial = 'MED7254500092'
        if 'SN=' in self.path:
            for part in self.path.split('&'):
                if part.startswith('SN='):
                    serial = part.split('=', 1)[-1]
                    break

        logger.info(f'POST {self.path} from {self.client_address[0]}, {len(body)} bytes')

        text = body.decode('utf-8', errors='ignore')
        logger.info(f'Body: {text[:500]}')

        if text.strip():
            # Parse tab-separated attendance records
            lines = text.replace('\r\n', '\n').replace('\r', '\n').strip().split('\n')
            att_lines = []
            for line in lines:
                line = line.strip()
                if line and not line.startswith('<') and not line.startswith('GET') and not line.startswith('POST'):
                    att_lines.append(line)

            if att_lines:
                body_text = '\n'.join(att_lines) + '\n'
                forward_to_laravel(body_text, serial, self.client_address[0])

        self.send_response(200)
        self.send_header('Content-Type', 'text/plain')
        self.end_headers()
        self.wfile.write(b'OK')


def main():
    parser = argparse.ArgumentParser(description='ZKTeco ADMS HTTP Server')
    parser.add_argument('--port', type=int, default=9000)
    parser.add_argument('--host', type=str, default='0.0.0.0')
    parser.add_argument('--laravel', type=str, default='http://127.0.0.1:8000')
    args = parser.parse_args()

    global LARAVEL_URL
    LARAVEL_URL = args.laravel.rstrip('/')

    server = HTTPServer((args.host, args.port), ADMSHandler)
    logger.info(f'ADMS HTTP Server started on {args.host}:{args.port}')
    logger.info(f'Forwarding to Laravel: {LARAVEL_URL}/api/attendance-integration/push/adms')
    logger.info('Waiting for device connections...')

    try:
        server.serve_forever()
    except KeyboardInterrupt:
        logger.info('Shutting down...')
        server.shutdown()


if __name__ == '__main__':
    main()
