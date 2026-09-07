<?php

namespace Tests\Unit\Modules\FingerprintDevices;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\FingerprintDevices\Jobs\DistributeFaceTemplateSetJob;
use Modules\FingerprintDevices\Jobs\DistributeFingerprintJob;
use Modules\FingerprintDevices\Models\DeviceCommand;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\FingerprintDevices\Services\BridgeBiometricSyncService;
use Modules\FingerprintDevices\Services\FaceTemplateDistributionService;
use Modules\Users\Models\User;
use Tests\TestCase;

/**
 * Distribution safety suite.
 *
 * Guarantees that no backlog of distribution jobs — however large — can ever
 * storm the terminals again:
 *  - stale jobs (older than distribution_max_age_hours) self-delete silently
 *  - legacy rows serialized before `dispatchedAt` existed are treated as stale
 *  - kill-switch DEVICE_WRITES_ENABLED=false halts jobs + bulk commands
 *  - fresh jobs still distribute normally (new enrollments unaffected)
 */
class DistributionJobsSafetyTest extends TestCase
{
    use RefreshDatabase;

    // ── DistributeFingerprintJob (direct TCP — the dangerous one) ──────

    public function test_stale_fingerprint_job_self_deletes_without_touching_devices(): void
    {
        [$source] = $this->makeDevices(1);

        Http::fake();
        $job = new DistributeFingerprintJob('EMP-S1', $source->id, 0, 'tpl', [], time() - 73 * 3600);
        $job->handle(app(BridgeBiometricSyncService::class));

        Http::assertNothingSent();
    }

    public function test_legacy_fingerprint_job_without_timestamp_is_treated_as_stale(): void
    {
        [$source] = $this->makeDevices(1);

        Http::fake();
        // TRUE legacy shape: rows serialized before `dispatchedAt` existed
        // unserialize with the typed property UNINITIALIZED (not null) —
        // a direct read would throw, so the guard must use `??`.
        $job = (new \ReflectionClass(DistributeFingerprintJob::class))->newInstanceWithoutConstructor();
        $job->pin = 'EMP-S2';
        $job->sourceDeviceId = $source->id;
        $job->fingerId = 0;
        $job->templateData = 'tpl';
        $job->attributes = [];
        $job->handle(app(BridgeBiometricSyncService::class));

        Http::assertNothingSent();
    }

    public function test_fingerprint_job_kill_switch_blocks_all_device_writes(): void
    {
        [$source] = $this->makeDevices(1);
        config(['fingerprintdevices.device_writes_enabled' => false]);

        Http::fake();
        $job = new DistributeFingerprintJob('EMP-S3', $source->id, 0, 'tpl', []);
        $job->handle(app(BridgeBiometricSyncService::class));

        Http::assertNothingSent();
    }

    public function test_fresh_fingerprint_job_still_distributes(): void
    {
        [$source, $target] = $this->makeDevices(2);
        $pin = 'EMP-FRESH';

        Http::fake(function ($request) use ($pin) {
            $url = (string) $request->url();
            if (str_contains($url, 'get-users')) {
                return Http::response(['users' => [['user_id' => $pin, 'uid' => 5]]], 200);
            }
            if (str_contains($url, 'get-templates')) {
                return Http::response(['templates' => [
                    ['fid' => 0, 'template' => 'AAA'],
                    ['fid' => 1, 'template' => 'BBB'],
                ]], 200);
            }
            if (str_contains($url, 'export-template')) {
                return Http::response(['success' => true], 200);
            }
            if (str_contains($url, 'add-user')) {
                return Http::response(['success' => true], 200);
            }

            return Http::response([], 200);
        });

        $job = new DistributeFingerprintJob($pin, $source->id, 0, 'AAA', []);
        $job->handle(app(BridgeBiometricSyncService::class));

        Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'export-template'));
        $this->assertSame($target->id, FingerprintDevice::find($target->id)->id);
    }

    // ── DistributeFaceTemplateSetJob (ADMS queue path) ─────────────────

    public function test_stale_face_job_queues_nothing(): void
    {
        $user = $this->makeUser('EMP-FS1');
        [$source, $target] = $this->makeDevices(2);
        $this->makeTemplate($user, $source, 'face-stale', null, 0);

        $job = new DistributeFaceTemplateSetJob($user->id, $source->id, $source->serial_number, 'set-stale', time() - 73 * 3600);
        $job->handle(app(FaceTemplateDistributionService::class));

        $this->assertSame(0, DeviceCommand::query()->count());
        $this->assertSame($target->id, FingerprintDevice::find($target->id)->id);
    }

    public function test_legacy_face_job_without_timestamp_is_treated_as_stale(): void
    {
        $user = $this->makeUser('EMP-FS0');
        [$source] = $this->makeDevices(2);
        $this->makeTemplate($user, $source, 'face-legacy', null, 0);

        // TRUE legacy shape (uninitialized `dispatchedAt`, see above).
        $job = (new \ReflectionClass(DistributeFaceTemplateSetJob::class))->newInstanceWithoutConstructor();
        $job->userId = $user->id;
        $job->sourceDeviceId = $source->id;
        $job->sourceSerial = $source->serial_number;
        $job->setId = 'set-legacy';
        $job->handle(app(FaceTemplateDistributionService::class));

        $this->assertSame(0, DeviceCommand::query()->count());
    }

    public function test_face_job_kill_switch_queues_nothing(): void
    {
        $user = $this->makeUser('EMP-FS2');
        [$source] = $this->makeDevices(2);
        $this->makeTemplate($user, $source, 'face-killed', null, 0);
        config(['fingerprintdevices.device_writes_enabled' => false]);

        $job = new DistributeFaceTemplateSetJob($user->id, $source->id, $source->serial_number, 'set-killed');
        $job->handle(app(FaceTemplateDistributionService::class));

        $this->assertSame(0, DeviceCommand::query()->count());
    }

    public function test_fresh_face_job_still_queues(): void
    {
        $user = $this->makeUser('EMP-FS3');
        [$source, $target] = $this->makeDevices(2);
        $this->makeTemplate($user, $source, 'face-fresh-a', null, 0);
        $this->makeTemplate($user, $source, 'face-fresh-b', null, 1);

        $job = new DistributeFaceTemplateSetJob($user->id, $source->id, $source->serial_number, 'set-fresh');
        $job->handle(app(FaceTemplateDistributionService::class));

        $this->assertSame(2, DeviceCommand::query()->where('device_id', $target->id)->count());
    }

    // ── Bulk console commands ──────────────────────────────────────────

    public function test_bulk_commands_abort_when_kill_switch_off(): void
    {
        config(['fingerprintdevices.device_writes_enabled' => false]);

        $this->artisan('fingerprints:distribute-missing-faces')->assertFailed();
        $this->artisan('fingerprints:distribute-all-faces --limit=5')->assertFailed();
        $this->artisan('fingerprints:push-faces')->assertFailed();
        $this->assertSame(0, DeviceCommand::query()->count());
    }

    public function test_safety_defaults_are_sane(): void
    {
        $this->assertTrue((bool) config('fingerprintdevices.device_writes_enabled', false));
        $this->assertSame(72, (int) config('fingerprintdevices.distribution_max_age_hours', 0));
    }

    // ── Helpers ────────────────────────────────────────────────────────

    /** @return array<int, FingerprintDevice> */
    private function makeDevices(int $count): array
    {
        $type = FingerprintDeviceType::create([
            'name' => 'ZKTeco Safety',
            'manufacturer' => 'ZKTeco',
            'default_port' => 4370,
            'protocol' => 'zkteco',
        ]);

        $devices = [];
        for ($i = 0; $i < $count; $i++) {
            $serial = 'SAFETY-'.$i.'-'.uniqid();
            $devices[] = FingerprintDevice::create([
                'device_type_id' => $type->id,
                'name' => "Safety Device {$i}",
                'serial_number' => $serial,
                'ip_address' => '192.168.98.'.($i + 10),
                'port' => 4370,
                'comm_key' => '0',
                'timeout' => 30,
                'status' => 'online',
                'is_push_enabled' => true,
            ]);
        }

        return $devices;
    }

    private function makeUser(string $employeeCode): User
    {
        // User first (no devices yet → observer queues nothing).
        return User::create([
            'employee_code' => $employeeCode,
            'name' => $employeeCode,
            'full_name_ar' => $employeeCode,
            'email' => strtolower($employeeCode).'@test.local',
            'password' => bcrypt('password'),
            'status' => 1,
            'is_active_employee' => true,
        ]);
    }

    private function makeTemplate(User $user, FingerprintDevice $source, string $data, ?string $setId, int $index): UserFingerprint
    {
        return UserFingerprint::create([
            'user_id' => $user->id,
            'device_id' => $source->id,
            'device_serial' => $source->serial_number,
            'finger_id' => 50 + $index,
            'template_data' => $data,
            'template_hash' => hash('sha256', $data),
            'template_format' => 'zkteco-face-push',
            'template_type' => 'face',
            'template_index' => $index,
            'face_template_set_id' => $setId,
            'template_version' => 120,
            'template_metadata' => [
                'No' => 1,
                'Index' => $index,
                'Valid' => 1,
                'Duress' => 0,
                'MajorVer' => 12,
                'MinorVer' => 0,
                'Format' => 0,
            ],
            'is_master' => true,
        ]);
    }
}
