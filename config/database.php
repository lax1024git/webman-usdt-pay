<?php

declare(strict_types=1);

use app\support\AppTimezone;

$mysqlOffset = AppTimezone::mysqlOffset();
$pdoOptions = [];
if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
    // 连接建立即设定 session 时区，避免依赖服务器 SYSTEM 时区
    $pdoOptions[\PDO::MYSQL_ATTR_INIT_COMMAND] = "SET time_zone = '{$mysqlOffset}', NAMES utf8mb4";
}

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'webman_admin'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
            // 供业务代码读取；实际生效靠 PDO INIT_COMMAND + bootstrap SET
            'timezone' => $mysqlOffset,
            'options' => $pdoOptions,
        ],
    ],
];
