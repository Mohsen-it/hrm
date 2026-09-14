<?php

namespace Tests\Unit\Modules\AttendanceIntegration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\AttendanceIntegration\Services\BiodataIngestionService;
use Modules\FingerprintDevices\Jobs\DistributeFaceTemplateSetJob;
use Modules\FingerprintDevices\Jobs\DistributeFingerprintJob;
use Modules\FingerprintDevices\Jobs\DistributeFingerprintSetJob;
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

    public function test_it_queues_bridge_distribution_for_fingerprints_by_default(): void
    {
        Queue::fake();
        [$user, $device] = $this->makeUserAndDevice();

        $stats = app(BiodataIngestionService::class)->ingest(
            $device,
            $this->fingerprintRecords($user->employee_code, [0, 1]),
            'fp-batch-20010',
        );

        $this->assertSame(2, $stats['saved']);
        $this->assertDatabaseHas('user_fingerprints', [
            'user_id' => $user->id,
            'device_id' => $device->id,
            'template_type' => 'fingerprint',
            'template_index' => 1,
        ]);
        // Proven channel: direct-TCP bridge job per saved template.
        Queue::assertPushed(DistributeFingerprintJob::class, 2);
        // ADMS FINGERTMP job: one per PIN.
        Queue::assertPushed(DistributeFingerprintSetJob::class, 1);
        Queue::assertPushed(DistributeFingerprintSetJob::class, function (DistributeFingerprintSetJob $job) use ($user, $device): bool {
            return $job->userId === $user->id
                && $job->sourceDeviceId === $device->id
                && $job->sourceSerial === $device->serial_number;
        });
    }

    public function test_it_skips_adms_fingerprint_distribution_when_disabled(): void
    {
        config()->set('fingerprintdevices.distribute_fingerprint_via_adms', false);
        Queue::fake();
        [$user, $device] = $this->makeUserAndDevice();

        app(BiodataIngestionService::class)->ingest(
            $device,
            $this->fingerprintRecords($user->employee_code, [0, 1]),
            'fp-batch-adms-off',
        );

        Queue::assertNotPushed(DistributeFingerprintSetJob::class);
        Queue::assertPushed(DistributeFingerprintJob::class, 2);
    }

    public function test_it_treats_the_same_fingerprint_from_another_device_as_duplicate(): void
    {
        Queue::fake();
        [$user, $source] = $this->makeUserAndDevice();
        $target = FingerprintDevice::create([
            'device_type_id' => $source->device_type_id,
            'name' => 'Target 20010',
            'serial_number' => 'TARGET-20010',
            'ip_address' => '192.168.40.11',
            'port' => 4370,
            'comm_key' => '0',
            'timeout' => 30,
            'status' => 'online',
            'is_push_enabled' => true,
        ]);
        $records = $this->fingerprintRecords($user->employee_code, [0, 1]);
        $service = app(BiodataIngestionService::class);

        $first = $service->ingest($source, $records, 'fp-source');
        $second = $service->ingest($target, $records, 'fp-target');

        $this->assertSame(2, $first['saved']);
        $this->assertSame(0, $second['saved']);
        $this->assertSame(2, $second['duplicates']);
        $this->assertDatabaseCount('user_fingerprints', 2);
        Queue::assertPushed(DistributeFingerprintSetJob::class, 1);
    }

    public function test_it_skips_bridge_job_when_bridge_disabled(): void
    {
        config()->set('fingerprintdevices.distribute_fingerprint_via_bridge', false);
        Queue::fake();
        [$user, $device] = $this->makeUserAndDevice();

        app(BiodataIngestionService::class)->ingest(
            $device,
            $this->fingerprintRecords($user->employee_code, [0]),
            'fp-batch-bridge-off',
        );

        Queue::assertNotPushed(DistributeFingerprintJob::class);
    }

    public function test_it_stores_classic_operlog_fingerprint_without_throwing(): void
    {
        Queue::fake();
        [$user, $device] = $this->makeUserAndDevice();
        $service = app(BiodataIngestionService::class);

        $result = $service->ingestOperlogFingerprint(
            $device->id,
            $device->serial_number,
            $user->employee_code,
            4,
            1,
            'operlog-fp-template',
        );

        $this->assertSame('saved', $result);
        $this->assertDatabaseHas('user_fingerprints', [
            'user_id' => $user->id,
            'template_type' => 'fingerprint',
            'template_index' => 4,
            'template_format' => 'zkteco-fp-operlog',
        ]);

        $this->assertSame('duplicates', $service->ingestOperlogFingerprint(
            $device->id,
            $device->serial_number,
            $user->employee_code,
            4,
            1,
            'operlog-fp-template',
        ));

        // Unknown PINs are skipped, never fatal (no 500 on OPERLOG upload).
        $this->assertSame('skipped', $service->ingestOperlogFingerprint(
            $device->id,
            $device->serial_number,
            'NO-SUCH-PIN',
            0,
            1,
            'some-template',
        ));
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

    /** @param array<int, int> $indices */
    private function fingerprintRecords(string $pin, array $indices): array
    {
        // Real-device shape for Type=1: the finger slot (FID 0-9) arrives in
        // `No` while `Index` is always 0 (captured 2026-09-14, PIN 20716).
        return array_map(fn (int $index) => [
            'pin' => $pin,
            'type' => 1,
            'tmp' => "fp-template-{$pin}-{$index}",
            'major_ver' => 10,
            'minor_ver' => 0,
            'format' => 0,
            'extra_fields' => [
                'No' => $index,
                'Index' => 0,
                'Valid' => 1,
                'Duress' => 0,
            ],
            'raw' => "BIODATA Pin={$pin} No={$index} Index=0",
        ], $indices);
    }
}
