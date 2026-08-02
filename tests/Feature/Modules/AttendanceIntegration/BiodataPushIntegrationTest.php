<?php

namespace Tests\Feature\Modules\AttendanceIntegration;

use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\Users\Models\User;
use Tests\TestCase;

class BiodataPushIntegrationTest extends TestCase
{
    private FingerprintDevice $device;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $type = FingerprintDeviceType::create([
            'name' => 'iFace880Plus',
            'manufacturer' => 'ZKTeco',
            'protocol' => 'ADMS',
            'default_port' => 4370,
            'supports_fingerprint' => true,
            'supports_face' => true,
            'max_fingerprints' => 10,
            'max_users' => 1000,
            'is_active' => true,
        ]);

        $this->device = FingerprintDevice::create([
            'device_type_id' => $type->id,
            'name' => 'Main Entrance',
            'serial_number' => 'ZK_FACE_001',
            'ip_address' => '192.168.1.100',
            'port' => 4370,
            'comm_key' => 0,
            'connection_type' => 'tcp',
            'timeout' => 30,
            'status' => 'online',
            'is_push_enabled' => true,
            'api_token' => 'zk_token_face123',
        ]);

        $this->employee = User::create([
            'name' => 'Ahmed Mohammed',
            'employee_code' => '20079',
            'email' => 'ahmed@hrm.local',
            'password' => bcrypt('password'),
            'status' => 1,
            'is_active_employee' => true,
        ]);
    }

    public function test_biodata_endpoint_receives_single_face_template(): void
    {
        $response = $this->postJson(route('attendance-integration.push.biodata'), [
            'SN' => 'ZK_FACE_001',
            'Body' => "BIODATA\nPin=20079\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=AAAA1111BBBB2222CCCC\n",
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 1,
                'saved' => 1,
                'duplicates' => 0,
                'skipped' => 0,
            ]);

        $this->assertDatabaseHas('user_fingerprints', [
            'user_id' => $this->employee->id,
            'device_id' => $this->device->id,
            'template_format' => 'zkteco-face-push',
            'template_data' => 'AAAA1111BBBB2222CCCC',
        ]);
    }

    public function test_local_adms_bridge_is_not_rate_limited(): void
    {
        config()->set('attendanceintegration.push.rate_limit', 1);
        config()->set('attendanceintegration.push.unlimited_ips', ['127.0.0.1', '::1']);

        for ($requestNumber = 0; $requestNumber < 3; $requestNumber++) {
            $this->postJson(route('attendance-integration.push.biodata'), [
                'SN' => 'ZK_FACE_001',
                'Body' => '',
            ])->assertOk();
        }
    }

    public function test_biodata_via_adms_push_detected_automatically(): void
    {
        $response = $this->postJson(route('attendance-integration.push.adms'), [
            'SN' => 'ZK_FACE_001',
            'Body' => "BIODATA\nPin=20079\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=FACE_TEMPLATE_DATA\n",
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 1,
                'saved' => 1,
            ]);

        $this->assertDatabaseHas('user_fingerprints', [
            'user_id' => $this->employee->id,
            'template_format' => 'zkteco-face-push',
            'template_data' => 'FACE_TEMPLATE_DATA',
        ]);
    }

    public function test_biodata_via_adms_push_with_table_query_param(): void
    {
        $response = $this->postJson(route('attendance-integration.push.adms').'?table=BIODATA&SN=ZK_FACE_001', [
            'Body' => "BIODATA\nPin=20079\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=QUERY_PARAM_TEMPLATE\n",
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 1,
                'saved' => 1,
            ]);
    }

    public function test_biodata_multiple_face_templates(): void
    {
        $body = "BIODATA\nPin=20079\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=FACE1\n\nBIODATA\nPin=20079\nType=2\nMajorVer=12\nMinorVer=1\nFormat=0\nTmp=FACE2\n";

        $response = $this->postJson(route('attendance-integration.push.biodata'), [
            'SN' => 'ZK_FACE_001',
            'Body' => $body,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 2,
                'saved' => 2,
                'duplicates' => 0,
            ]);

        $templates = UserFingerprint::where('user_id', $this->employee->id)
            ->where('template_format', 'zkteco-face-push')
            ->get();

        $this->assertCount(2, $templates);
    }

    public function test_biodata_fingerprint_type_0_is_skipped(): void
    {
        $response = $this->postJson(route('attendance-integration.push.biodata'), [
            'SN' => 'ZK_FACE_001',
            'Body' => "BIODATA\nPin=20079\nType=0\nMajorVer=9\nMinorVer=0\nFormat=0\nTmp=FPDATA\n",
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 1,
                'saved' => 0,
                'skipped' => 1,
            ]);

        $this->assertDatabaseMissing('user_fingerprints', [
            'user_id' => $this->employee->id,
            'template_format' => 'zkteco-face-push',
        ]);
    }

    public function test_biodata_duplicate_template_ignored(): void
    {
        $body = "BIODATA\nPin=20079\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=SAMETEMPLATE\n";

        $this->postJson(route('attendance-integration.push.biodata'), [
            'SN' => 'ZK_FACE_001',
            'Body' => $body,
        ]);

        $response = $this->postJson(route('attendance-integration.push.biodata'), [
            'SN' => 'ZK_FACE_001',
            'Body' => $body,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 1,
                'saved' => 0,
                'duplicates' => 1,
            ]);

        $templates = UserFingerprint::where('user_id', $this->employee->id)
            ->where('template_format', 'zkteco-face-push')
            ->get();

        $this->assertCount(1, $templates);
    }

    public function test_biodata_unknown_employee_skipped(): void
    {
        $response = $this->postJson(route('attendance-integration.push.biodata'), [
            'SN' => 'ZK_FACE_001',
            'Body' => "BIODATA\nPin=99999\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=ORPHANFACE\n",
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 1,
                'saved' => 0,
                'skipped' => 1,
            ]);

        $this->assertDatabaseMissing('user_fingerprints', [
            'template_data' => 'ORPHANFACE',
        ]);
    }

    public function test_biodata_empty_template_skipped(): void
    {
        $response = $this->postJson(route('attendance-integration.push.biodata'), [
            'SN' => 'ZK_FACE_001',
            'Body' => "BIODATA\nPin=20079\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=\n",
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 1,
                'saved' => 0,
                'skipped' => 1,
            ]);
    }

    public function test_biodata_empty_body_returns_zero(): void
    {
        $response = $this->postJson(route('attendance-integration.push.biodata'), [
            'SN' => 'ZK_FACE_001',
            'Body' => '',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 0,
                'saved' => 0,
            ]);
    }

    public function test_biodata_no_body_returns_zero(): void
    {
        $response = $this->postJson(route('attendance-integration.push.biodata'), [
            'SN' => 'ZK_FACE_001',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 0,
            ]);
    }

    public function test_corrupted_biodata_is_saved_to_the_debug_directory(): void
    {
        $debugDirectory = storage_path('app/debug/biodata');
        $before = glob($debugDirectory.DIRECTORY_SEPARATOR.'*.txt') ?: [];

        $response = $this->postJson(route('attendance-integration.push.biodata'), [
            'SN' => 'ZK_FACE_001',
            'Body' => "BIODATA\nType=2\nTmp=CORRUPTED_WITHOUT_PIN\n",
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'received' => 0,
            'saved' => 0,
        ]);

        $after = glob($debugDirectory.DIRECTORY_SEPARATOR.'*.txt') ?: [];
        $this->assertGreaterThan(count($before), count($after));
    }

    public function test_biodata_unknown_device_still_accepted(): void
    {
        $response = $this->postJson(route('attendance-integration.push.biodata'), [
            'SN' => 'UNKNOWN_DEVICE_999',
            'Body' => "BIODATA\nPin=20079\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=DATA\n",
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 1,
                'saved' => 1,
            ]);
    }

    public function test_biodata_via_adms_route_detected_as_biodata(): void
    {
        $response = $this->postJson(route('attendance-integration.push.adms'), [
            'SN' => 'ZK_FACE_001',
            'Body' => "BIODATA\nPin=20079\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=DETECTED_VIA_ADM\n",
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 1,
                'saved' => 1,
            ]);

        $this->assertDatabaseHas('user_fingerprints', [
            'user_id' => $this->employee->id,
            'template_data' => 'DETECTED_VIA_ADM',
        ]);
    }

    public function test_biodata_large_template(): void
    {
        $tmpData = str_repeat('A', 8000);

        $response = $this->postJson(route('attendance-integration.push.biodata'), [
            'SN' => 'ZK_FACE_001',
            'Body' => "BIODATA\nPin=20079\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp={$tmpData}\n",
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 1,
                'saved' => 1,
            ]);

        $this->assertDatabaseHas('user_fingerprints', [
            'user_id' => $this->employee->id,
            'template_data' => $tmpData,
        ]);
    }

    public function test_biodata_face_templates_use_finger_id_range_50_59(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('attendance-integration.push.biodata'), [
                'SN' => 'ZK_FACE_001',
                'Body' => "BIODATA\nPin=20079\nType=2\nMajorVer=12\nMinorVer={$i}\nFormat=0\nTmp=FACE_{$i}\n",
            ]);
        }

        $templates = UserFingerprint::where('user_id', $this->employee->id)
            ->where('template_format', 'zkteco-face-push')
            ->orderBy('finger_id')
            ->get();

        $this->assertCount(3, $templates);
        $this->assertSame(50, $templates[0]->finger_id);
        $this->assertSame(51, $templates[1]->finger_id);
        $this->assertSame(52, $templates[2]->finger_id);
    }

    public function test_biodata_first_face_template_is_master(): void
    {
        $this->postJson(route('attendance-integration.push.biodata'), [
            'SN' => 'ZK_FACE_001',
            'Body' => "BIODATA\nPin=20079\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=MASTER_FACE\n",
        ]);

        $template = UserFingerprint::where('user_id', $this->employee->id)
            ->where('template_format', 'zkteco-face-push')
            ->first();

        $this->assertTrue($template->is_master);
    }

    public function test_biodata_second_face_template_is_not_master(): void
    {
        for ($i = 0; $i < 2; $i++) {
            $this->postJson(route('attendance-integration.push.biodata'), [
                'SN' => 'ZK_FACE_001',
                'Body' => "BIODATA\nPin=20079\nType=2\nMajorVer=12\nMinorVer={$i}\nFormat=0\nTmp=FACE_{$i}\n",
            ]);
        }

        $templates = UserFingerprint::where('user_id', $this->employee->id)
            ->where('template_format', 'zkteco-face-push')
            ->orderBy('finger_id')
            ->get();

        $this->assertTrue($templates[0]->is_master);
        $this->assertFalse($templates[1]->is_master);
    }

    public function test_biodata_response_includes_correlation_id(): void
    {
        $response = $this->postJson(route('attendance-integration.push.biodata'), [
            'SN' => 'ZK_FACE_001',
            'Body' => "BIODATA\nPin=20079\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=DATA\n",
        ], [
            'X-Request-Id' => 'biodata-test-123',
        ]);

        $response->assertOk()
            ->assertJson([
                'correlation_id' => 'biodata-test-123',
            ]);
    }

    public function test_biodata_template_version_stored_correctly(): void
    {
        $this->postJson(route('attendance-integration.push.biodata'), [
            'SN' => 'ZK_FACE_001',
            'Body' => "BIODATA\nPin=20079\nType=2\nMajorVer=12\nMinorVer=5\nFormat=0\nTmp=VERSIONED\n",
        ]);

        $template = UserFingerprint::where('user_id', $this->employee->id)
            ->where('template_format', 'zkteco-face-push')
            ->first();

        $this->assertSame(125, $template->template_version);
    }

    public function test_biodata_mixed_with_att_on_same_route(): void
    {
        $response = $this->postJson(route('attendance-integration.push.adms'), [
            'SN' => 'ZK_FACE_001',
            'Body' => "BIODATA\nPin=20079\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=MIXED_FACE\n",
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'received' => 1,
                'saved' => 1,
            ]);
    }

    public function test_biodata_employee_resolved_by_numeric_id(): void
    {
        $numericUser = User::create([
            'name' => 'Numeric User',
            'employee_code' => '500',
            'email' => 'numeric@hrm.local',
            'password' => bcrypt('password'),
            'status' => 1,
            'is_active_employee' => true,
        ]);

        $response = $this->postJson(route('attendance-integration.push.biodata'), [
            'SN' => 'ZK_FACE_001',
            'Body' => "BIODATA\nPin=500\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=NUMERIC_FACE\n",
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'saved' => 1,
            ]);

        $this->assertDatabaseHas('user_fingerprints', [
            'user_id' => $numericUser->id,
            'template_data' => 'NUMERIC_FACE',
        ]);
    }

    public function test_different_devices_same_pin_both_saved(): void
    {
        $device2 = FingerprintDevice::create([
            'device_type_id' => $this->device->device_type_id,
            'name' => 'Side Door',
            'serial_number' => 'ZK_FACE_002',
            'ip_address' => '192.168.1.101',
            'port' => 4370,
            'comm_key' => 0,
            'connection_type' => 'tcp',
            'timeout' => 30,
            'status' => 'online',
            'is_push_enabled' => true,
        ]);

        $this->postJson(route('attendance-integration.push.biodata'), [
            'SN' => 'ZK_FACE_001',
            'Body' => "BIODATA\nPin=20079\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=DEVICE1_FACE\n",
        ]);

        $this->postJson(route('attendance-integration.push.biodata'), [
            'SN' => 'ZK_FACE_002',
            'Body' => "BIODATA\nPin=20079\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=DEVICE2_FACE\n",
        ]);

        $templates = UserFingerprint::where('user_id', $this->employee->id)
            ->where('template_format', 'zkteco-face-push')
            ->get();

        $this->assertCount(2, $templates);
    }
}
