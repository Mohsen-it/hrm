<?php

namespace Modules\AttendanceIntegration\DTOs;

enum VerifyMethod: string
{
    case Fingerprint = 'fingerprint';
    case Card = 'card';
    case Password = 'password';
    case Face = 'face';
    case Unknown = 'unknown';
}
