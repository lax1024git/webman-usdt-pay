<?php

declare(strict_types=1);

namespace support\bootstrap;

use app\support\AppTimezone;
use Illuminate\Database\Capsule\Manager as Capsule;
use Webman\Bootstrap;

class Database implements Bootstrap
{
    public static function start($worker): void
    {
        AppTimezone::applyPhp();

        $config = config('database');
        $capsule = new Capsule();
        $capsule->addConnection($config['connections'][$config['default']]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        // INIT_COMMAND 失败或重连时兜底
        try {
            AppTimezone::applyToConnection($capsule->getConnection());
        } catch (\Throwable) {
            // 连接异常时由后续业务报错
        }
    }
}
