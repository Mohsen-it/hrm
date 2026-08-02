
import sys
import base64
from zk import ZK, const

def debug_device(ip, port=4370, password=0):
    zk = ZK(ip, port=port, timeout=30, password=password, force_udp=False, ommit_ping=True)
    conn = None
    try:
        print(f"Connecting to {ip}:{port}...")
        conn = zk.connect()
        print("Connected!")
        
        # 1. Device Info
        print("\n--- Device Info ---")
        print(f"Firmware: {conn.get_firmware_version()}")
        print(f"Device Name: {conn.get_device_name()}")
        print(f"Serial Number: {conn.get_serialnumber()}")
        
        # 2. Users and Templates
        print("\n--- Users & Templates Scan ---")
        users = conn.get_users()
        templates = conn.get_templates()
        
        # Index templates by UID
        tpl_map = {}
        for t in templates:
            if t.uid not in tpl_map:
                tpl_map[t.uid] = []
            tpl_map[t.uid].append(t)
            
        print(f"Total Users Found: {len(users)}")
        print(f"Total Templates Found: {len(templates)}")
        
        # Sample some users with templates
        count = 0
        for u in users:
            user_tpls = tpl_map.get(u.uid, [])
            if user_tpls or count < 10: # Print first 10 or anyone with templates
                tpl_info = [f"FID:{t.fid}(Size:{len(t.template)})" for t in user_tpls]
                print(f"User: {u.user_id} (UID:{u.uid}) | Name: {u.name} | Templates: {len(user_tpls)} | Details: {tpl_info}")
                count += 1
            if count > 50: break # Don't flood output
            
        # 3. Check specifically for Face (BioData Type 2)
        print("\n--- Checking BioData (Face) ---")
        # Try to manually request face biodata if possible
        # This is a bit internal for pyzk
        try:
            # Command 1100 is often used for BioData
            print("Scanning for high-index templates (fid >= 50)...")
            faces = [t for t in templates if t.fid >= 50]
            print(f"Found {len(faces)} templates with FID >= 50")
            for f in faces[:5]:
                 print(f"  Face Sample: UID {f.uid}, FID {f.fid}, Size {len(f.template)}")
        except Exception as e:
            print(f"BioData scan error: {e}")

    except Exception as e:
        print(f"Error: {e}")
    finally:
        if conn:
            conn.disconnect()
            print("\nDisconnected.")

if __name__ == "__main__":
    ip = "10.10.250.8"
    debug_device(ip)
