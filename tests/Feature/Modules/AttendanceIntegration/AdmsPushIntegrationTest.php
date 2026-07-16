<?php

namespace Tests\Feature\Modules\AttendanceIntegration;

use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;
use Modules\Users\Models\User;
use Tests\TestCase;

class AdmsPushIntegrationTest extends TestCase
{
    private FingerprintDevice $device;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $type = FingerprintDeviceType::create([
            'name' => 'iClock680',
            'manufacturer' => 'ZKTeco',
            'protocol' => 'ADMS',
            'default_port' => 4370,
            'supports_fingerprint' => true,
            'supports_face' => false,
            'max_fingerprints' => 10,
            'max_users' => 1000,
            'is_active' => true,
        ]);

        $this->device = FingerprintDevice::create([
            'device_type_id' => $type->id,
            'name' => 'Main Entrance',
            'serial_number' => 'ZK_MAIN_001',
            'ip_address' => '192.168.1.100',
            'port' => 4370,
            'comm_key' => 0,
            'connection_type' => 'tcp',
            'timeout' => 30,
            'status' => 'online',
            'is_push_enabled' => true,
            'api_token' => 'zk_token_abc123',
        ]);

        $this->employee = User::create([
            'name' => 'Ahmed Mohammed',
            'employee_code' => 'EMP001',
            'email' => 'ahmed@hrm.local',
            'password' => bcrypt('password'),
            'status' => 1,
            'is_active_employee' => true,
        ]);
    }

    public function test_adms_text_body_parses_and_creates_raw_logs(): void
    {
        $response = $this->postJson(route('attendance-integration.push.adms'), [
            'SN' => 'ZK_MAIN_001',
            'Body' => "ATT\t\tEMP001\t2026-07-15 08:00:00\t0\n",
        ], [
            'Authorization' => 'Bearer zk_token_abc123',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 1,
                'processed' => 1,
                'skipped' => 0,
                'duplicates' => 0,
            ]);

        $this->assertDatabaseHas('raw_attendance_logs', [
            'device_id' => $this->device->id,
            'device_user_id' => 'EMP001',
            'source' => 'device',
        ]);
    }

    public function test_adms_body_preserves_raw_data(): void
    {
        $this->postJson(route('attendance-integration.push.adms'), [
            'SN' => 'ZK_MAIN_001',
            'Body' => "ATT\t\tEMP001\t2026-07-15 08:00:00\t0\n",
        ], [
            'Authorization' => 'Bearer zk_token_abc123',
        ]);

        $log = RawAttendanceLog::where('device_user_id', 'EMP001')->first();
        $this->assertNotNull($log);
        $this->assertIsArray($log->raw_data);
        $this->assertSame('zkteco', $log->raw_data['_driver'] ?? null);
    }

    public function test_adms_body_creates_attendance_session(): void
    {
        $this->postJson(route('attendance-integration.push.adms'), [
            'SN' => 'ZK_MAIN_001',
            'Body' => "ATT\t\tEMP001\t2026-07-15 08:00:00\t0\n",
        ], [
            'Authorization' => 'Bearer zk_token_abc123',
        ]);

        $session = AttendanceSession::where('user_id', $this->employee->id)->first();
        $this->assertNotNull($session);
        $this->assertSame('device', $session->source);
    }

    public function test_adms_multiple_lines_batch(): void
    {
        $response = $this->postJson(route('attendance-integration.push.adms'), [
            'SN' => 'ZK_MAIN_001',
            'Body' => "ATT\t\tEMP001\t2026-07-15 08:00:00\t0\nATT\t\tEMP001\t2026-07-15 12:00:00\t2\nATT\t\tEMP001\t2026-07-15 17:00:00\t1\n",
        ], [
            'Authorization' => 'Bearer zk_token_abc123',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 3,
            ]);
    }

    public function test_push_attendance_array_format(): void
    {
        $response = $this->postJson(route('attendance-integration.push'), [
            'SN' => 'ZK_MAIN_001',
            'attendance' => [
                ['user_id' => 'EMP001', 'timestamp' => '2026-07-15 08:00:00', 'status' => 0],
                ['user_id' => 'EMP001', 'timestamp' => '2026-07-15 17:00:00', 'status' => 1],
            ],
        ], [
            'Authorization' => 'Bearer zk_token_abc123',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 2,
                'processed' => 2,
            ]);
    }

    public function test_push_single_punch_format(): void
    {
        $response = $this->postJson(route('attendance-integration.push'), [
            'SN' => 'ZK_MAIN_001',
            'user_id' => 'EMP001',
            'timestamp' => '2026-07-15 08:00:00',
            'punch_type' => 'check_in',
            'status' => 0,
        ], [
            'Authorization' => 'Bearer zk_token_abc123',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 1,
                'processed' => 1,
            ]);
    }

    public function test_duplicate_punch_detected_and_skipped(): void
    {
        $payload = [
            'SN' => 'ZK_MAIN_001',
            'user_id' => 'EMP001',
            'timestamp' => '2026-07-15 08:00:00',
            'punch_type' => 'check_in',
            'status' => 0,
        ];
        $headers = ['Authorization' => 'Bearer zk_token_abc123'];

        $this->postJson(route('attendance-integration.push'), $payload, $headers);

        $response = $this->postJson(route('attendance-integration.push'), $payload, $headers);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 1,
                'duplicates' => 1,
            ]);
    }

    public function test_device_without_token_still_accepted(): void
    {
        $device = FingerprintDevice::create([
            'device_type_id' => $this->device->device_type_id,
            'name' => 'Secure Door',
            'serial_number' => 'ZK_SECURE_001',
            'ip_address' => '192.168.1.200',
            'port' => 4370,
            'comm_key' => 0,
            'connection_type' => 'tcp',
            'timeout' => 30,
            'status' => 'online',
            'is_push_enabled' => true,
            'api_token' => 'secure_token_xyz',
        ]);

        $response = $this->postJson(route('attendance-integration.push'), [
            'SN' => 'ZK_SECURE_001',
            'user_id' => 'EMP001',
            'timestamp' => '2026-07-15 08:00:00',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_invalid_token_still_accepted_when_auth_disabled(): void
    {
        $device = FingerprintDevice::create([
            'device_type_id' => $this->device->device_type_id,
            'name' => 'Secure Door 2',
            'serial_number' => 'ZK_SECURE_002',
            'ip_address' => '192.168.1.201',
            'port' => 4370,
            'comm_key' => 0,
            'connection_type' => 'tcp',
            'timeout' => 30,
            'status' => 'online',
            'is_push_enabled' => true,
            'api_token' => 'correct_token',
        ]);

        $response = $this->postJson(route('attendance-integration.push'), [
            'SN' => 'ZK_SECURE_002',
            'user_id' => 'EMP001',
            'timestamp' => '2026-07-15 08:00:00',
        ], [
            'Authorization' => 'Bearer wrong_token',
        ]);

        $response->assertOk();
    }

    public function test_deactivated_device_still_accepted(): void
    {
        $device = FingerprintDevice::create([
            'device_type_id' => $this->device->device_type_id,
            'name' => 'Retired Device',
            'serial_number' => 'ZK_RETIRED_001',
            'ip_address' => '192.168.1.250',
            'port' => 4370,
            'comm_key' => 0,
            'connection_type' => 'tcp',
            'timeout' => 30,
            'status' => 'deactivated',
            'is_push_enabled' => true,
        ]);

        $response = $this->postJson(route('attendance-integration.push'), [
            'SN' => 'ZK_RETIRED_001',
            'user_id' => 'EMP001',
            'timestamp' => '2026-07-15 08:00:00',
        ]);

        $response->assertOk();
    }

    public function test_correlation_id_returned_in_response(): void
    {
        $response = $this->postJson(route('attendance-integration.push'), [
            'SN' => 'ZK_MAIN_001',
            'user_id' => 'EMP001',
            'timestamp' => '2026-07-15 08:00:00',
        ], [
            'Authorization' => 'Bearer zk_token_abc123',
            'X-Request-Id' => 'test-correlation-id-123',
        ]);

        $response->assertOk()
            ->assertHeader('X-Correlation-Id', 'test-correlation-id-123');
    }

    public function test_empty_adms_body_returns_zero_received(): void
    {
        $response = $this->postJson(route('attendance-integration.push.adms'), [
            'SN' => 'ZK_MAIN_001',
        ], [
            'Authorization' => 'Bearer zk_token_abc123',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 0,
                'processed' => 0,
            ]);
    }

    public function test_invalid_punch_type_rejected_by_validation(): void
    {
        $response = $this->postJson(route('attendance-integration.push'), [
            'SN' => 'ZK_MAIN_001',
            'user_id' => 'EMP001',
            'timestamp' => '2026-07-15 08:00:00',
            'punch_type' => 'invalid_type',
        ], [
            'Authorization' => 'Bearer zk_token_abc123',
        ]);

        $response->assertStatus(422);
    }

    public function test_unknown_user_still_creates_raw_log(): void
    {
        $response = $this->postJson(route('attendance-integration.push'), [
            'SN' => 'ZK_MAIN_001',
            'user_id' => 'UNKNOWN_USER_99',
            'timestamp' => '2026-07-15 08:00:00',
            'punch_type' => 'check_in',
            'status' => 0,
        ], [
            'Authorization' => 'Bearer zk_token_abc123',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 1,
                'processed' => 0,
            ]);
    }

    public function test_device_without_token_accepts_requests(): void
    {
        $device = FingerprintDevice::create([
            'device_type_id' => $this->device->device_type_id,
            'name' => 'Open Door',
            'serial_number' => 'ZK_OPEN_001',
            'ip_address' => '192.168.1.150',
            'port' => 4370,
            'comm_key' => 0,
            'connection_type' => 'tcp',
            'timeout' => 30,
            'status' => 'online',
            'is_push_enabled' => true,
            'api_token' => null,
        ]);

        $response = $this->postJson(route('attendance-integration.push'), [
            'SN' => 'ZK_OPEN_001',
            'user_id' => 'EMP001',
            'timestamp' => '2026-07-15 08:00:00',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_response_includes_correlation_id_and_timing_header(): void
    {
        $response = $this->postJson(route('attendance-integration.push'), [
            'SN' => 'ZK_MAIN_001',
            'user_id' => 'EMP001',
            'timestamp' => '2026-07-15 08:00:00',
        ], [
            'Authorization' => 'Bearer zk_token_abc123',
        ]);

        $response->assertOk()
            ->assertHeader('X-Correlation-Id')
            ->assertHeader('X-Response-Time-Ms');
    }

    public function test_device_serial_in_header_instead_of_body(): void
    {
        $response = $this->postJson(route('attendance-integration.push'), [
            'user_id' => 'EMP001',
            'timestamp' => '2026-07-15 08:00:00',
            'punch_type' => 'check_in',
        ], [
            'X-Device-Serial' => 'ZK_MAIN_001',
            'Authorization' => 'Bearer zk_token_abc123',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 1,
                'processed' => 1,
            ]);
    }
}
