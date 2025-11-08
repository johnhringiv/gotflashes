<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('APP_ENV') === 'testing'
                ? ':memory:'
                : (env('DB_DATABASE') ?: database_path('data/database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),

            // Locking & Concurrency
            'busy_timeout' => 5000,           // 5 second retry on locks
            'journal_mode' => 'WAL',          // Write-Ahead Logging (better concurrency)
            'synchronous' => 'NORMAL',        // Good balance of safety and performance
            'transaction_mode' => 'DEFERRED', // Acquire locks only when needed

            // Performance (applied via pragmas array)
            'pragmas' => [
                'cache_size' => -64000,       // 64 MB cache (negative = KB)
                'temp_store' => 'MEMORY',     // Keep temp tables/sorts in RAM
                'mmap_size' => 268435456,     // 256 MB memory-mapped I/O (in bytes)
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

];
