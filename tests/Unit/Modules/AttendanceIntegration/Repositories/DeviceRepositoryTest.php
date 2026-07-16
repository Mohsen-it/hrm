<?php

namespace Tests\Unit\Modules\AttendanceIntegration\Repositories;

use Modules\AttendanceIntegration\Contracts\AttendanceDeviceInterface;
use Modules\AttendanceIntegration\Repositories\DeviceRepository;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;
use Modules\FingerprintDevices\Repositories\FingerprintDeviceRepository;
use Tests\TestCase;

class DeviceRepositoryTest extends TestCase
{
    private DeviceRepository $repository;

    private FingerprintDeviceRepository $fingerprintRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fingerprintRepo = $this->createMock(FingerprintDeviceRepository::class);
        $this->repository = new DeviceRepository($this->fingerprintRepo);
    }

    public function test_find_by_serial_returns_attendance_device_interface(): void
    {
        $type = new FingerprintDeviceType([
            'name' => 'Test',
            'manufacturer' => 'ZKTeco',
            'protocol' => 'ADMS',
            'default_port' => 4370,
        ]);

        $model = new FingerprintDevice([
            'name' => 'Test',
            'serial_number' => 'SN001',
            'ip_address' => '192.168.1.1',
            'port' => 4370,
            'comm_key' => '0',
            'timeout' => 30,
            'status' => 'online',
        ]);
        $model->id = 1;
        $model->setRelation('deviceType', $type);

        $this->fingerprintRepo->expects($this->once())
            ->method('findBySerial')
            ->with('SN001')
            ->willReturn($model);

        $device = $this->repository->findBySerial('SN001');

        $this->assertInstanceOf(AttendanceDeviceInterface::class, $device);
        $this->assertSame(1, $device->getId());
        $this->assertSame('Test', $device->getName());
        $this->assertSame('SN001', $device->getSerialNumber());
        $this->assertSame('192.168.1.1', $device->getIpAddress());
        $this->assertSame(4370, $device->getPort());
    }

    public function test_find_by_serial_returns_null_when_not_found(): void
    {
        $this->fingerprintRepo->expects($this->once())
            ->method('findBySerial')
            ->with('UNKNOWN')
            ->willReturn(null);

        $device = $this->repository->findBySerial('UNKNOWN');

        $this->assertNull($device);
    }

    public function test_mark_online_delegates_to_fingerprint_repository(): void
    {
        $type = new FingerprintDeviceType([
            'name' => 'Test',
            'manufacturer' => 'ZKTeco',
            'protocol' => 'ADMS',
            'default_port' => 4370,
        ]);

        $model = new FingerprintDevice([
            'name' => 'Test',
            'serial_number' => 'SN002',
            'ip_address' => '192.168.1.1',
            'port' => 4370,
            'status' => 'offline',
        ]);
        $model->id = 2;
        $model->setRelation('deviceType', $type);

        $this->fingerprintRepo->expects($this->once())
            ->method('findBySerial')
            ->with('SN002')
            ->willReturn($model);

        $this->fingerprintRepo->expects($this->once())
            ->method('markOnline');

        $device = $this->repository->findBySerial('SN002');
        $this->repository->markOnline($device);
    }
}
