import pymysql

conn = pymysql.connect(host='127.0.0.1', user='root', password='', database='hrmair', charset='utf8mb4')
cur = conn.cursor()

# Add margin columns
try:
    cur.execute("ALTER TABLE att_time_schedules ADD COLUMN in_ahead_margin SMALLINT UNSIGNED DEFAULT 0 AFTER early_margin")
    print("Added in_ahead_margin")
except Exception as e:
    print(f"in_ahead_margin: {e}")

try:
    cur.execute("ALTER TABLE att_time_schedules ADD COLUMN in_above_margin SMALLINT UNSIGNED DEFAULT 0 AFTER in_ahead_margin")
    print("Added in_above_margin")
except Exception as e:
    print(f"in_above_margin: {e}")

try:
    cur.execute("ALTER TABLE att_time_schedules ADD COLUMN out_ahead_margin SMALLINT UNSIGNED DEFAULT 0 AFTER in_above_margin")
    print("Added out_ahead_margin")
except Exception as e:
    print(f"out_ahead_margin: {e}")

try:
    cur.execute("ALTER TABLE att_time_schedules ADD COLUMN out_above_margin SMALLINT UNSIGNED DEFAULT 0 AFTER out_ahead_margin")
    print("Added out_above_margin")
except Exception as e:
    print(f"out_above_margin: {e}")

conn.commit()

# Verify
cur.execute("DESCRIBE att_time_schedules")
print("\n--- att_time_schedules schema ---")
for r in cur.fetchall():
    print(f"  {r[0]:25s} {r[1]}")

conn.close()
