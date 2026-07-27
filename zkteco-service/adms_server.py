#!/usr/bin/env python3
"""
ZKTeco ADMS HTTP Server
Optimized Thread Pool and Non-Blocking forwarding to Laravel.
"""

import argparse
import json
import logging
import re
import urllib.request
from concurrent.futures import ThreadPoolExecutor
from http.server import ThreadingHTTPServer, BaseHTTPRequestHandler

logging.basicConfig(level=logging.INFO, format='%(asctime)s [%(levelname)s] %(message)s')
logger = logging.getLogger('adms')

LARAVEL_URL = 'http://127.0.0.1:8000'
# Increased workers to 50 to handle concurrent device pushes
executor = ThreadPoolExecutor(max_workers=50)

def forward_to_laravel(body_text, serial, ip):
    if not body_text or not body_text.strip():
        return False

    try:
        data = json.dumps({'SN': serial, 'Body': body_text}).encode('utf-8')
        req = urllib.request.Request(
            f'{LARAVEL_URL}/api/attendance-integration/push/adms',
            data=data,
            headers={'Content-Type': 'application/json'}
        )

        # Reduced timeout to 10s to prevent worker starvation if Laravel is slow
        with urllib.request.urlopen(req, timeout=10) as resp:
            result = json.loads(resp.read().decode())
            logger.info(f'Forwarded to Laravel: received={result.get("received")}, processed={result.get("processed")}')
            return True
    except Exception as e:
        logger.error(f'Forward failed: {e}')
        return False

def extract_attlog_lines(text):
    lines = text.replace('\r\n', '\n').replace('\r', '\n').strip().split('\n')
    att_lines = []
    for line in lines:
        line = line.strip()
        if not line: continue
        if re.match(r'^[A-Za-z0-9]+\s+\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}', line):
            att_lines.append(line)
    return att_lines

class ADMSHandler(BaseHTTPRequestHandler):
    def log_message(self, format, *args):
        logger.info(f'{self.client_address[0]} - {format % args}')

    def do_GET(self):
        serial = ''
        if 'SN=' in self.path:
            for part in self.path.split('&'):
                if part.startswith('SN='):
                    serial = part.split('=', 1)[-1]
                    break

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
        content_length = int(self.headers.get('Content-Length', 0))
        body = self.rfile.read(content_length) if content_length else b''

        serial = ''
        if 'SN=' in self.path:
            for part in self.path.split('&'):
                if part.startswith('SN='):
                    serial = part.split('=', 1)[-1]
                    break

        text = body.decode('utf-8', errors='ignore')

        table_type = ''
        if 'table=' in self.path:
            for part in self.path.split('&'):
                if part.startswith('table='):
                    table_type = part.split('=', 1)[-1]
                    break

        if table_type and table_type not in ('ATTLOG', ''):
            self.send_response(200)
            self.send_header('Content-Type', 'text/plain')
            self.end_headers()
            self.wfile.write(b'OK')
            return

        if text.strip():
            att_lines = extract_attlog_lines(text)
            if att_lines:
                body_text = 'ATT\t\t' + '\nATT\t\t'.join(att_lines) + '\n'
                # Fire and forget
                executor.submit(forward_to_laravel, body_text, serial, self.client_address[0])

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

    server = ThreadingHTTPServer((args.host, args.port), ADMSHandler)
    logger.info(f'ADMS HTTP Server started on {args.host}:{args.port}')
    logger.info(f'Forwarding to Laravel: {LARAVEL_URL}/api/attendance-integration/push/adms')

    try:
        server.serve_forever()
    except KeyboardInterrupt:
        logger.info('Shutting down...')
        server.shutdown()
        executor.shutdown(wait=False)

if __name__ == '__main__':
    main()