<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/..')->load();
}
