<?php

declare(strict_types=1);

use Webman\Route;

Route::get('/', function () {
    return response('Admin API shell. Use /admin/*.', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
});


require __DIR__ . '/routes/admin.php';
require __DIR__ . '/routes/api.php';
require __DIR__ . '/routes/merchant.php';
