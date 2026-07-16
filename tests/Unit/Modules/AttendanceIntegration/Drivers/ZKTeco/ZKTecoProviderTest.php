<?php

namespace Tests\Unit\Modules\AttendanceIntegration\Drivers\ZKTeco;

use Modules\AttendanceIntegration\Contracts\DeviceAdapterInterface;
use Modules\AttendanceIntegration\Contracts\PunchNormalizerInterface;
use Modules\AttendanceIntegration\Contracts\PushPayloadParserInterface;
use Modules\AttendanceIntegration\Drivers\ZKTeco\ZKTecoAdapter;
use Modules\AttendanceIntegration\Drivers\ZKTeco\ZKTecoAdmsParser;
use Modules\AttendanceIntegration\Drivers\ZKTeco\ZKTecoProvider;
use Modules\AttendanceIntegration\Drivers\ZKTeco\ZKTecoPunchNormalizer;
use Tests\TestCase;

class ZKTecoProviderTest extends TestCase
{
    public function test_provider_registers_correct_driver_name(): void
    {
        $provider = new ZKTecoProvider;
        $this->assertSame('zkteco', $provider->driverName());
    }

    public function test_provider_provides_adapter(): void
    {
        $provider = new ZKTecoProvider;
        $this->assertTrue($provider->providesAdapter());
        $this->assertSame(ZKTecoAdapter::class, $provider->adapterClass());
    }

    public function test_adapter_implements_device_adapter_interface(): void
    {
        $adapter = new ZKTecoAdapter;
        $this->assertInstanceOf(DeviceAdapterInterface::class, $adapter);
        $this->assertSame('zkteco', $adapter->getDriverName());
    }

    public function test_provider_provides_normalizer(): void
    {
        $provider = new ZKTecoProvider;
        $this->assertTrue($provider->providesNormalizer());
        $this->assertSame(ZKTecoPunchNormalizer::class, $provider->normalizerClass());
    }

    public function test_normalizer_implements_punch_normalizer_interface(): void
    {
        $normalizer = new ZKTecoPunchNormalizer;
        $this->assertInstanceOf(PunchNormalizerInterface::class, $normalizer);
        $this->assertSame('zkteco', $normalizer->getDriverName());
    }

    public function test_provider_provides_push_parser(): void
    {
        $provider = new ZKTecoProvider;
        $this->assertTrue($provider->providesPushParser());
        $this->assertSame(ZKTecoAdmsParser::class, $provider->pushParserClass());
    }

    public function test_parser_implements_push_payload_parser_interface(): void
    {
        $parser = new ZKTecoAdmsParser;
        $this->assertInstanceOf(PushPayloadParserInterface::class, $parser);
        $this->assertSame('zkteco', $parser->getDriverName());
    }
}
