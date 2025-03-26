<?php

use Illuminate\Support\Str;

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'sistema_onix' => [ // Conexión adicional para sistema_onix
            'driver' => 'mysql',
            'host' => env('ONIX_DB_HOST', 'sgt.logex.com.ec'),
            'port' => env('ONIX_DB_PORT', '3306'),
            'database' => env('ONIX_DB_DATABASE', 'sistema_onix'),
            'username' => env('ONIX_DB_USERNAME', 'restrella'),
            'password' => env('ONIX_DB_PASSWORD', 'LogeX-?2028*'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],
        'tms' => [
            'driver' => 'mysql',
            'host' => env('TMS_DB_HOST', 'sgt.logex.com.ec'),
            'port' => env('TMS_DB_PORT', '3306'),
            'database' => env('TMS_DB_DATABASE', 'tms'),
            'username' => env('TMS_DB_USERNAME', 'restrella'),
            'password' => env('TMS_DB_PASSWORD', 'LogeX-?2028*'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],

        'tms1' => [
            'driver'   => 'mysql',
            'host'     => env('DB_TMS1_HOST', 'tms1.logex.com.ec'),
            'port' => env('TMS1_DB_PORT', '3306'),
            'database' => env('DB_TMS1_DATABASE', 'tms1'),
            'username' => env('DB_TMS1_USERNAME', 'restrella'),
            'password' => env('DB_TMS1_PASSWORD', 'Onix210320#'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],


    ],
    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_') . '_database_'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
