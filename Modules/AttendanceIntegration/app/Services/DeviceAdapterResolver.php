<?php

namespace Modules\AttendanceIntegration\Services;

use Modules\AttendanceIntegration\Contracts\DeviceAdapterInterface;
use Modules\AttendanceIntegration\Contracts\DriverProviderInterface;
use Modules\AttendanceIntegration\Contracts\PunchNormalizerInterface;
use Modules\AttendanceIntegration\Contracts\PushPayloadParserInterface;
use Modules\AttendanceIntegration\Drivers\Hikvision\HikvisionAdapter;
use Modules\AttendanceIntegration\Drivers\Hikvision\HikvisionPunchNormalizer;
use Modules\AttendanceIntegration\Drivers\Suprema\SupremaAdapter;
use Modules\AttendanceIntegration\Drivers\ZKTeco\ZKTecoAdapter;
use Modules\AttendanceIntegration\Drivers\ZKTeco\ZKTecoAdmsParser;
use Modules\AttendanceIntegration\Drivers\ZKTeco\ZKTecoPunchNormalizer;
use Modules\AttendanceIntegration\Exceptions\UnsupportedDriverException;

class DeviceAdapterResolver
{
    private array $adapterInstances = [];

    private array $normalizerInstances = [];

    private array $parserInstances = [];

    private array $registeredProviders = [];

    public function __construct()
    {
        $this->registerDefaultProviders();
    }

    public function registerProvider(DriverProviderInterface $provider): void
    {
        $name = $provider->driverName();
        $this->registeredProviders[$name] = $provider;
    }

    public function getAdapter(string $driver): DeviceAdapterInterface
    {
        if (isset($this->adapterInstances[$driver])) {
            return $this->adapterInstances[$driver];
        }

        $provider = $this->registeredProviders[$driver] ?? null;

        if ($provider && $provider->providesAdapter()) {
            $class = $provider->adapterClass();

            return $this->adapterInstances[$driver] = new $class;
        }

        throw new UnsupportedDriverException($driver);
    }

    public function getNormalizer(string $driver): PunchNormalizerInterface
    {
        if (isset($this->normalizerInstances[$driver])) {
            return $this->normalizerInstances[$driver];
        }

        $provider = $this->registeredProviders[$driver] ?? null;

        if ($provider && $provider->providesNormalizer()) {
            $class = $provider->normalizerClass();

            return $this->normalizerInstances[$driver] = new $class;
        }

        throw new UnsupportedDriverException("No punch normalizer for driver: {$driver}");
    }

    public function getParser(string $driver): PushPayloadParserInterface
    {
        if (isset($this->parserInstances[$driver])) {
            return $this->parserInstances[$driver];
        }

        $provider = $this->registeredProviders[$driver] ?? null;

        if ($provider && $provider->providesPushParser()) {
            $class = $provider->pushParserClass();

            return $this->parserInstances[$driver] = new $class;
        }

        throw new UnsupportedDriverException("No push payload parser for driver: {$driver}");
    }

    public function getRegisteredDrivers(): array
    {
        return array_keys($this->registeredProviders);
    }

    public function hasDriver(string $driver): bool
    {
        return isset($this->registeredProviders[$driver]);
    }

    private function registerDefaultProviders(): void
    {
        $defaults = [
            'zkteco' => [
                'adapter' => ZKTecoAdapter::class,
                'normalizer' => ZKTecoPunchNormalizer::class,
                'parser' => ZKTecoAdmsParser::class,
            ],
            'suprema' => [
                'adapter' => SupremaAdapter::class,
                'normalizer' => null,
                'parser' => null,
            ],
            'hikvision' => [
                'adapter' => HikvisionAdapter::class,
                'normalizer' => HikvisionPunchNormalizer::class,
                'parser' => null,
            ],
        ];

        foreach ($defaults as $name => $classes) {
            $provider = new class($name, $classes) implements DriverProviderInterface
            {
                public function __construct(
                    private string $name,
                    private array $classes,
                ) {}

                public function driverName(): string
                {
                    return $this->name;
                }

                public function providesAdapter(): bool
                {
                    return $this->classes['adapter'] !== null;
                }

                public function providesNormalizer(): bool
                {
                    return $this->classes['normalizer'] !== null;
                }

                public function providesPushParser(): bool
                {
                    return $this->classes['parser'] !== null;
                }

                public function adapterClass(): string
                {
                    return $this->classes['adapter'];
                }

                public function normalizerClass(): string
                {
                    return $this->classes['normalizer'];
                }

                public function pushParserClass(): string
                {
                    return $this->classes['parser'];
                }
            };

            $this->registerProvider($provider);
        }
    }
}
