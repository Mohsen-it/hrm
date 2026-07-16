<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('attendance.live', function ($user) {
    return $user !== null && $user->hasPermissionTo('view-attendance');
});
