
import sys
import base64
import struct
from zk import ZK, const

def test_fetch_faces(ip, port=4370):
    zk = ZK(ip, port=port, timeout=60, password=0, force_udp=False, ommit_ping=True)
    conn = None
    try:
        print(f"Connecting to {ip}:{port} for Face BioData test...")
        conn = zk.connect()
        print("✅ Connected!")
        
        print("\n--- Attempting Command 1100 (BioData Type 2 - Face) ---")
        try:
            # Attempting to fetch face data specifically
            faces = conn.get_bio_data(2)
            
            if not faces:
                print("❌ No faces returned via get_bio_data(2).")
            else:
                print(f"🎉 Success! Found {len(faces)} face templates.")
                for i, face in enumerate(faces[:10]):  # Show first 10
                    template_data = face.repack() if hasattr(face, 'repack') else face.template
                    print(f"  [{i+1}] UID: {face.uid} | FID: {face.fid if hasattr(face, 'fid') else 'N/A'} | Size: {len(template_data)} bytes")
                
                if len(faces) > 10:
                    print(f"  ... and {len(faces) - 10} more.")
        
        except Exception as e:
            print(f"❌ Error during BioData fetch: {e}")
            
            print("\n--- Fallback: Manual Command 1100 Send ---")
            try:
                # Direct buffer send for CMD_DB_RRQ (1100)
                # Payload: [Type:2 (Face)][Language:0][Index:0][Count:0]
                payload = struct.pack("<BBII", 2, 0, 0, 0)
                response = conn._send_command(1100, payload)
                if response:
                    print(f"✅ Manual Command 1100 got response! Length: {len(response)} bytes")
                else:
                    print("❌ Manual Command 1100 got no response.")
            except Exception as e2:
                print(f"❌ Manual Command failed: {e2}")

    except Exception as e:
        print(f"❌ Connection Error: {e}")
    finally:
        if conn:
            conn.disconnect()
            print("\nDisconnected.")

if __name__ == "__main__":
    test_fetch_faces("10.10.250.8")
