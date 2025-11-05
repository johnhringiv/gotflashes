<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Observability Settings
    |--------------------------------------------------------------------------
    |
    | These settings control query logging and performance monitoring.
    |
    */

    'log_queries' => env('LOG_QUERIES', false),
    'log_slow_queries' => env('LOG_SLOW_QUERIES', true),
    'slow_query_threshold_ms' => env('SLOW_QUERY_THRESHOLD_MS', 100),
    'slow_request_threshold_ms' => env('SLOW_REQUEST_THRESHOLD_MS', 300),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        // Structured logging for better observability
        'structured' => [
            'driver' => 'stack',
            'channels' => ['structured_file', 'stdout'],
            'ignore_exceptions' => false,
        ],

        'structured_file' => [
            'driver' => 'daily',
            'path' => storage_path('logs/structured.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'formatter' => Monolog\Formatter\JsonFormatter::class,
            'replace_placeholders' => true,
        ],

        // Performance logging channel
        'performance' => [
            'driver' => 'stack',
            'channels' => ['performance_file', 'stdout'],
            'ignore_exceptions' => false,
        ],

        'performance_file' => [
            'driver' => 'daily',
            'path' => storage_path('logs/performance.log'),
            'level' => 'info',
            'days' => 7,
            'formatter' => Monolog\Formatter\JsonFormatter::class,
        ],

        // Security and authentication logging
        'security' => [
            'driver' => 'stack',
            'channels' => ['security_file', 'stdout'],
            'ignore_exceptions' => false,
        ],

        'security_file' => [
            'driver' => 'daily',
            'path' => storage_path('logs/security.log'),
            'level' => 'info',
            'days' => 30,
            'formatter' => Monolog\Formatter\JsonFormatter::class,
        ],

        // Admin action logging
        'admin' => [
            'driver' => 'stack',
            'channels' => ['admin_file', 'stdout'],
            'ignore_exceptions' => false,
        ],

        'admin_file' => [
            'driver' => 'daily',
            'path' => storage_path('logs/admin.log'),
            'level' => 'info',
            'days' => 90,
            'formatter' => Monolog\Formatter\JsonFormatter::class,
        ],

        // Database query logging
        'query' => [
            'driver' => 'stack',
            'channels' => ['query_file', 'stdout'],
            'ignore_exceptions' => false,
        ],

        'query_file' => [
            'driver' => 'daily',
            'path' => storage_path('logs/queries.log'),
            'level' => 'debug',
            'days' => 3,
        ],

        'stdout' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => Monolog\Formatter\JsonFormatter::class,
            'handler_with' => [
                'stream' => 'php://stdout',
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

    ],

];
