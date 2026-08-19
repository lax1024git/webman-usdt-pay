<?php

declare(strict_types=1);

$onOff = static fn (string $on = '开启', string $off = '关闭') => [
    ['value' => '1', 'label' => $on],
    ['value' => '0', 'label' => $off],
];

return [
    'tabs' => [
        [
            'key' => 'base',
            'label' => '基本功能',
            'fields' => [
                ['key' => 'admin_google_auth_status', 'label' => '后台操作是否开启谷歌验证', 'type' => 'radio', 'options' => $onOff(), 'default' => '1', 'help' => 'admin_google_auth_status：开启后，后台敏感操作需校验谷歌验证码；关闭后跳过校验。不影响管理员登录验码。'],
                ['key' => 'system_default_lang', 'label' => '系统默认语言', 'type' => 'select', 'options_ref' => 'languages', 'default' => 'zh-cn', 'help' => 'system_default_lang：后台与多语言模块默认语言。'],
            ],
        ],
        [
            'key' => 'branding',
            'label' => '品牌配置',
            'fields' => [
                ['key' => 'admin_icon', 'label' => '网站 icon', 'type' => 'upload', 'accept' => 'image', 'default' => '', 'help' => 'admin_icon：浏览器标签页图标（favicon）。'],
                ['key' => 'logo', 'label' => '网站 logo', 'type' => 'upload', 'accept' => 'image', 'default' => '', 'help' => 'logo：后台侧栏、登录页与浏览器图标。'],
                ['key' => 'name', 'label' => '应用名称', 'type' => 'text', 'default' => '', 'help' => 'name：应用名称，展示在后台标题等位置。'],
            ],
        ],
        [
            'key' => 'storage',
            'label' => '存储配置',
            'fields' => [
                ['key' => 's3_config', 'label' => 'S3 对象存储', 'type' => 's3_config', 'default' => [
                    'credentials_key' => '',
                    'credentials_secret' => '',
                    'region' => 'ap-east-1',
                    'bucket' => '',
                    'url' => '',
                    'proxy' => null,
                    'presign_expires' => 900,
                ], 'help' => 's3_config：对象存储（S3 兼容）凭证与桶配置，用于上传文件；presign_expires 为预签名有效秒数。'],
            ],
        ],
    ],
];
