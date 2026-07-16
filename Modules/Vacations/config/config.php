<?php

return [
    'name' => 'Vacations',

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */

    'super_admin_id' => env('USERS_SUPER_ADMIN_ID', 10000),

    // Default number of days granted to a brand-new employee on the annual
    // vacation type if no per-user override exists.
    'default_annual_days' => (int) env('VACATIONS_DEFAULT_ANNUAL_DAYS', 21),

    // Code used for the built-in "annual" vacation type.
    'annual_code' => env('VACATIONS_ANNUAL_CODE', 'annual'),

    // Days carried over at the start of a new year when no carry policy is set.
    'default_carry_days' => (int) env('VACATIONS_DEFAULT_CARRY_DAYS', 0),

    /*
    |--------------------------------------------------------------------------
    | Approval workflow
    |--------------------------------------------------------------------------
    */

    'workflow' => [
        // Whether vacation requests require manager approval.
        'requires_approval' => (bool) env('VACATIONS_REQUIRES_APPROVAL', true),

        // Role whose members are allowed to approve vacation requests.
        'approver_role' => env('VACATIONS_APPROVER_ROLE', 'manager'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        // Channels the generic notification wrapper will route through.
        'channels' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('VACATIONS_NOTIFICATION_CHANNELS', 'mail,database'))
        ))),
    ],
];
