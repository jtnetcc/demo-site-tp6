<?php

return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'type' => 'mysql',
            'hostname' => env('DB_HOST', 'mysql'),
            'database' => env('DB_DATABASE', 'tp6_demo'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', 'root123'),
            'hostport' => env('DB_PORT', '3306'),
            'params' => [],
            'charset' => 'utf8mb4',
            'prefix' => '',
            'deploy' => 0,
            'rw_separate' => false,
            'master_num' => 1,
            'slave_no' => '',
            'fields_strict' => true,
            'break_reconnect' => false,
            'trigger_sql' => false,
            'fields_cache' => false,
        ],
    ],
];
