<?php

namespace Modules\FingerprintDevices\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;

/**
 * FingerprintDeviceTypesSeeder — populates a baseline catalog of ZKTeco
 * device models so the create-device form has sensible defaults out of
 * the box. Idempotent: each type is matched by `(manufacturer, name)`.
 */
class FingerprintDeviceTypesSeeder extends Seeder
{
    /**
     * Default device types seeded on first install.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $defaults = [
        [
            'name' => 'iClock 680',
            'manufacturer' => 'ZKTeco',
            'protocol' => 'ADMS',
            'sdk_version' => '6.4.1',
            'default_port' => 4370,
            'supports_fingerprint' => true,
            'supports_face' => true,
            'max_fingerprints' => 8000,
            'max_users' => 20000,
            'description' => 'Multi-bio terminal with fingerprint, face, and RFID — 8" TFT.',
            'is_active' => true,
        ],
        [
            'name' => 'UA100',
            'manufacturer' => 'ZKTeco',
            'protocol' => 'ADMS',
            'sdk_version' => '6.4.1',
            'default_port' => 4370,
            'supports_fingerprint' => true,
            'supports_face' => false,
            'max_fingerprints' => 3000,
            'max_users' => 10000,
            'description' => 'Compact fingerprint + RFID terminal for indoor use.',
            'is_active' => true,
        ],
        [
            'name' => 'SpeedFace-V5L',
            'manufacturer' => 'ZKTeco',
            'protocol' => 'ADMS',
            'sdk_version' => '6.4.1',
            'default_port' => 4370,
            'supports_fingerprint' => true,
            'supports_face' => true,
            'max_fingerprints' => 6000,
            'max_users' => 10000,
            'description' => 'Visible-light facial recognition terminal with fingerprint and card support.',
            'is_active' => true,
        ],
        [
            'name' => 'K40',
            'manufacturer' => 'ZKTeco',
            'protocol' => 'ADMS',
            'sdk_version' => '6.4.1',
            'default_port' => 4370,
            'supports_fingerprint' => true,
            'supports_face' => false,
            'max_fingerprints' => 3000,
            'max_users' => 10000,
            'description' => 'Economy fingerprint terminal with 2.8" TFT — small offices and shops.',
            'is_active' => true,
        ],
        [
            'name' => 'ProFace X',
            'manufacturer' => 'ZKTeco',
            'protocol' => 'ADMS',
            'sdk_version' => '6.4.1',
            'default_port' => 4370,
            'supports_fingerprint' => true,
            'supports_face' => true,
            'max_fingerprints' => 50000,
            'max_users' => 30000,
            'description' => 'High-end dual-camera facial recognition with anti-spoofing.',
            'is_active' => true,
        ],
    ];

    public function run(): void
    {
        foreach ($this->defaults as $row) {
            FingerprintDeviceType::firstOrCreate(
                ['manufacturer' => $row['manufacturer'], 'name' => $row['name']],
                $row,
            );
        }
    }
}
