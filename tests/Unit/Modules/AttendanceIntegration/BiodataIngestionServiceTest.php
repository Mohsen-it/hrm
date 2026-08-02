<?php

namespace Tests\Unit\Modules\AttendanceIntegration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\AttendanceIntegration\Services\BiodataIngestionService;
use Modules\FingerprintDevices\Jobs\DistributeFaceTemplateSetJob;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;
use Modules\Users\Models\User;
use Tests\TestCase;

class BiodataIngestionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_distribution_only_after_a_complete_face_enrollment_set_is_saved(): void
    {
        Queue::fake();
        [$user, $device] = $this->makeUserAndDevice();

        $stats = app(BiodataIngestionService::class)->ingest(
            $device,
            $this->faceRecords($user->employee_code, range(0, 14)),
            'face-set-20010',
        );

        $this->assertSame(15, $stats['saved']);
        $this->assertDatabaseCount('user_fingerprints', 15);
        $this->assertDatabaseHas('user_fingerprints', [
            'user_id' => $user->id,
            'device_id' => $device->id,
            'template_index' => 14,
            'face_template_set_id' => 'face-set-20010',
        ]);
        Queue::assertPushed(DistributeFaceTemplateSetJob::class, function (DistributeFaceTemplateSetJob $job) use ($user, $device): bool {
            return $job->userId === $user->id
                && $job->sourceDeviceId === $device->id
                && $job->setId === 'face-set-20010';
        });
    }

    public function test_it_does_not_queue_distribution_for_an_incomplete_face_enrollment_set(): void
    {
        Queue::fake();
        [$user, $device] = $this->makeUserAndDevice();

        app(BiodataIngestionService::class)->ingest(
            $device,
            $this->faceRecords($user->employee_code, [0, 1]),
            'incomplete-face-set',
        );

        Queue::assertNotPushed(DistributeFaceTemplateSetJob::class);
    }

    /** @return array{User, FingerprintDevice} */
    private function makeUserAndDevice(): array
    {
        $type = FingerprintDeviceType::create([
            'name' => 'ZKTeco Face',
            'manufacturer' => 'ZKTeco',
            'default_port' => 4370,
            'protocol' => 'zkteco',
        ]);
        $device = FingerprintDevice::create([
            'device_type_id' => $type->id,
            'name' => 'Source 20010',
            'serial_number' => 'SOURCE-20010',
            'ip_address' => '192.168.40.10',
            'port' => 4370,
            'comm_key' => '0',
            'timeout' => 30,
            'status' => 'online',
            'is_push_enabled' => true,
        ]);
        $user = User::create([
            'employee_code' => '20010',
            'name' => 'Employee 20010',
            'full_name_ar' => 'Employee 20010',
            'email' => 'employee-20010@tests.local',
            'password' => bcrypt('password'),
            'status' => 1,
            'is_active_employee' => true,
        ]);

        return [$user, $device];
    }

    /** @param array<int, int> $indices */
    private function faceRecords(string $pin, array $indices): array
    {
        return array_map(fn (int $index) => [
            'pin' => $pin,
            'type' => 2,
            'tmp' => "face-component-{$index}",
            'major_ver' => 12,
            'minor_ver' => 0,
            'format' => 0,
            'extra_fields' => [
                'No' => 0,
                'Index' => $index,
                'Valid' => 1,
                'Duress' => 0,
            ],
            'raw' => "BIODATA Pin={$pin} Index={$index}",
        ], $indices);
    }
}
