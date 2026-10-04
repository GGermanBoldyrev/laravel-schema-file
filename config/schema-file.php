<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Automatic Generation
    |--------------------------------------------------------------------------
    |
    | Whether the schema file is rewritten after migrations run or are
    | rolled back. Null means only in the "local" environment, so that
    | tests, CI and production never touch the file. The schema:generate
    | command works regardless of this setting.
    |
    */

    'enabled' => env('SCHEMA_FILE_ENABLED'),

    /*
    |--------------------------------------------------------------------------
    | Schema File Path
    |--------------------------------------------------------------------------
    |
    | Where the schema file is written. The --path option of the
    | schema:generate command overrides this for a single run.
    |
    */

    'path' => env('SCHEMA_FILE_PATH', database_path('schema.php')),

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | The connection whose schema is written to the file. Null means the
    | application's default connection. The --database option of the
    | schema:generate command overrides this for a single run.
    |
    */

    'connection' => env('SCHEMA_FILE_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Excluded Tables
    |--------------------------------------------------------------------------
    |
    | Tables that are left out of the schema file, by exact name or by a
    | pattern where * matches anything, such as "telescope_*". The table
    | Laravel uses to track migrations is always left out.
    |
    */

    'except' => [],

];
