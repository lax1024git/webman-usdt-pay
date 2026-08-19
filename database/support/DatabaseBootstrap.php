<?php

declare(strict_types=1);

namespace database\support;

use app\support\AppTimezone;
use Illuminate\Database\Capsule\Manager as Capsule;

class DatabaseBootstrap
{
    private static bool $booted = false;

    public static function init(): Capsule
    {
        if (self::$booted) {
            return Capsule::getInstance();
        }

        if (file_exists(base_path() . '/.env')) {
            \Dotenv\Dotenv::createUnsafeImmutable(base_path())->load();
        }

        AppTimezone::applyPhp();

        $config = require config_path('database.php');
        $capsule = new Capsule();
        $capsule->addConnection($config['connections'][$config['default']]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        try {
            AppTimezone::applyToConnection($capsule->getConnection());
        } catch (\Throwable) {
            // ignore; caller will fail on queries if DB is unavailable
        }

        self::$booted = true;

        return $capsule;
    }
}
