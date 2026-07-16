<?php

namespace Modules\AttendanceIntegration\DTOs;

final class FingerprintTemplateData
{
    public function __construct(
        public readonly int $uid,
        public readonly int $fingerId,
        public readonly string $templateData,
    ) {}
}
