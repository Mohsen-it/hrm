<?php

namespace Modules\AttendanceIntegration\DTOs;

final class UserData
{
    public function __construct(
        public readonly int $uid,
        public readonly string $userId,
        public readonly string $name = '',
        public readonly string $password = '',
        public readonly int $privilege = 0,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            uid: (int) ($data['uid'] ?? 0),
            userId: (string) ($data['user_id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            password: (string) ($data['password'] ?? ''),
            privilege: (int) ($data['privilege'] ?? 0),
        );
    }
}
