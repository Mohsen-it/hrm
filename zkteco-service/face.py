from zk import ZK, const

SOURCE_DEVICE_IP = '10.10.250.8'
TARGET_DEVICE_IP = '10.10.250.3'
PORT = 4370

def sync_face_templates():
    print("--- start sync ---")
    zk_source = ZK(SOURCE_DEVICE_IP, port=PORT, timeout=10, force_udp=False)
    conn_source = None
    users = []
    face_templates = []
    try:
        print(f"connecting to source: {SOURCE_DEVICE_IP}...")
        conn_source = zk_source.connect()
        conn_source.disable_device()
        print("fetching data...")
        users = conn_source.get_users()
        try:
            face_templates = conn_source.get_templates()
        except Exception as e:
            print("note: face templates fetch failed or not supported.")
        conn_source.enable_device()
        print(f"fetched {len(users)} users.")
    except Exception as e:
        print(f"source error: {e}")
        return
    finally:
        if conn_source:
            conn_source.disconnect()
    if not users:
        print("no data to sync.")
        return
    zk_target = ZK(TARGET_DEVICE_IP, port=PORT, timeout=10, force_udp=False)
    conn_target = None
    try:
        print(f"connecting to target: {TARGET_DEVICE_IP}...")
        conn_target = zk_target.connect()
        conn_target.disable_device()
        print("uploading users...")
        for user in users:
            conn_target.set_user(
                user_id=user.user_id,
                name=user.name,
                privilege=user.privilege,
                password=user.password,
                card_idx=user.card_idx,
                user_id_string=user.user_id_string
            )
        if face_templates:
            print("uploading templates...")
            for template in face_templates:
                conn_target.save_template(template)
        conn_target.enable_device()
        print("sync completed successfully!")
    except Exception as e:
        print(f"target error: {e}")
    finally:
        if conn_target:
            conn_target.disconnect()

if __name__ == '__main__':
    sync_face_templates()
