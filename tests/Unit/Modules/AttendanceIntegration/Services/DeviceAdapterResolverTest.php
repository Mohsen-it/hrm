<?php

namespace Tests\Unit\Modules\AttendanceIntegration\Services;

use Modules\AttendanceIntegration\Drivers\Hikvision\HikvisionAdapter;
use Modules\AttendanceIntegration\Drivers\Suprema\SupremaAdapter;
use Modules\AttendanceIntegration\Drivers\ZKTeco\ZKTecoAdapter;
use Modules\AttendanceIntegration\Drivers\ZKTeco\ZKTecoAdmsParser;
use Modules\AttendanceIntegration\Drivers\ZKTeco\ZKTecoPunchNormalizer;
use Modules\AttendanceIntegration\Exceptions\UnsupportedDriverException;
use Modules\AttendanceIntegration\Services\DeviceAdapterResolver;
use Tests\TestCase;

class DeviceAdapterResolverTest extends TestCase
{
    private DeviceAdapterResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new DeviceAdapterResolver;
    }

    public function test_get_adapter_zkteco(): void
    {
        $adapter = $this->resolver->getAdapter('zkteco');
        $this->assertInstanceOf(ZKTecoAdapter::class, $adapter);
        $this->assertSame('zkteco', $adapter->getDriverName());
    }

    public function test_get_adapter_suprema(): void
    {
        $adapter = $this->resolver->getAdapter('suprema');
        $this->assertInstanceOf(SupremaAdapter::class, $adapter);
        $this->assertSame('suprema', $adapter->getDriverName());
    }

    public function test_get_adapter_hikvision(): void
    {
        $adapter = $this->resolver->getAdapter('hikvision');
        $this->assertInstanceOf(HikvisionAdapter::class, $adapter);
        $this->assertSame('hikvision', $adapter->getDriverName());
    }

    public function test_get_adapter_unsupported_throws(): void
    {
        $this->expectException(UnsupportedDriverException::class);
        $this->resolver->getAdapter('unknown_vendor');
    }

    public function test_get_adapter_returns_same_instance(): void
    {
        $a1 = $this->resolver->getAdapter('zkteco');
        $a2 = $this->resolver->getAdapter('zkteco');
        $this->assertSame($a1, $a2);
    }

    public function test_get_normalizer_zkteco(): void
    {
        $normalizer = $this->resolver->getNormalizer('zkteco');
        $this->assertInstanceOf(ZKTecoPunchNormalizer::class, $normalizer);
    }

    public function test_get_normalizer_unsupported_throws(): void
    {
        $this->expectException(UnsupportedDriverException::class);
        $this->resolver->getNormalizer('unknown_vendor');
    }

    public function test_get_parser_zkteco(): void
    {
        $parser = $this->resolver->getParser('zkteco');
        $this->assertInstanceOf(ZKTecoAdmsParser::class, $parser);
    }

    public function test_get_parser_unsupported_throws(): void
    {
        $this->expectException(UnsupportedDriverException::class);
        $this->resolver->getParser('unknown_vendor');
    }

    public function test_has_driver(): void
    {
        $this->assertTrue($this->resolver->hasDriver('zkteco'));
        $this->assertFalse($this->resolver->hasDriver('nonexistent'));
    }

    public function test_get_registered_drivers(): void
    {
        $drivers = $this->resolver->getRegisteredDrivers();
        $this->assertContains('zkteco', $drivers);
        $this->assertContains('suprema', $drivers);
        $this->assertContains('hikvision', $drivers);
    }
}
