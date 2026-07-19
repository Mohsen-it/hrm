<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'zkteco_python' => [
        'url' => env('ZKTECO_PYTHON_SERVICE_URL', 'http://127.0.0.1:5000'),
        'host' => env('ZKTECO_PYTHON_SERVICE_HOST', '127.0.0.1'),
        'port' => (int) env('ZKTECO_PYTHON_SERVICE_PORT', 5000),
        'timeout' => (int) env('ZKTECO_PYTHON_SERVICE_TIMEOUT', 60),
        'pid_file' => env('ZKTECO_PYTHON_SERVICE_PID_FILE', storage_path('app/zkteco-service.pid')),
        'log_file' => env('ZKTECO_PYTHON_SERVICE_LOG_FILE', storage_path('logs/zkteco-service.log')),
        'start_script' => env('ZKTECO_PYTHON_SERVICE_SCRIPT', base_path('zkteco-service/start.bat')),
    ],

    'hikvision_python' => [
        'url' => env('HIKVISION_API_URL', 'http://127.0.0.1:5001'),
        'host' => env('HIKVISION_SERVICE_HOST', '127.0.0.1'),
        'port' => (int) env('HIKVISION_SERVICE_PORT', 5001),
        'timeout' => (int) env('HIKVISION_BRIDGE_TIMEOUT', 30),
        'username' => env('HIKVISION_USERNAME', 'admin'),
        'password' => env('HIKVISION_PASSWORD', ''),
        'start_script' => env('HIKVISION_SERVICE_SCRIPT', base_path('zkteco-service/start_hikvision.bat')),
    ],

];
