<?php

declare(strict_types=1);

/**
 * 权限由控制器 menuConfig 定义，php webman menu 同步到数据库。
 * 编辑员角色默认分配的 API 权限 slug（纯管理壳示例）。
 */
return [
    'editor_api_slugs' => [
        'admin:list',
        'role:list',
        'permission:list',
        'dict:list',
        'log:list',
        'notification:list',
    ],
];
