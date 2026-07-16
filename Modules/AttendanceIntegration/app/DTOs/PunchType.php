<?php

namespace Modules\AttendanceIntegration\DTOs;

enum PunchType: string
{
    case CheckIn = 'check_in';
    case CheckOut = 'check_out';
    case BreakIn = 'break_in';
    case BreakOut = 'break_out';
    case Unknown = 'unknown';
}
