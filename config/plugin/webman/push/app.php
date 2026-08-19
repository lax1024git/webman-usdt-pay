<?php

declare(strict_types=1);

/**
 * Push 频道上下线 webhook。
 * - 绝对地址：原样使用（如 http://127.0.0.1:8999/plugin/webman/push/hook）
 * - 相对路径：按 SERVER_LISTEN 端口拼成本机可访问地址（push 进程需 HTTP 回调 Webman）
 */
$resolveChannelHook = static function (string $hook): string {
    $hook = trim($hook);
    if ($hook === '') {
        $hook = '/plugin/webman/push/hook';
    }
    if (preg_match('#^https?://#i', $hook) === 1) {
        return $hook;
    }

    $path = str_starts_with($hook, '/') ? $hook : '/' . $hook;
    $listen = (string) env('SERVER_LISTEN', 'http://0.0.0.0:8787');
    $port = parse_url($listen, PHP_URL_PORT);
    if (!$port) {
        $port = 8787;
    }

    return 'http://127.0.0.1:' . $port . $path;
};

return [
    'enable'       => (bool) env('PUSH_ENABLE', true),
    'websocket'    => env('PUSH_WEBSOCKET', 'websocket://0.0.0.0:3131'),
    'api'          => env('PUSH_API', 'http://0.0.0.0:3232'),
    // 服务端触发推送请用 127.0.0.1，避免 0.0.0.0 无法连接
    'api_local'    => env('PUSH_API_LOCAL', 'http://127.0.0.1:3232'),
    'app_key'      => env('PUSH_APP_KEY', '3c89c415de8f06f467e31ebe2f0b86ed'),
    'app_secret'   => env('PUSH_APP_SECRET', '4955b8a417322c15f9114165a9e37b70'),
    'channel_hook' => $resolveChannelHook((string) env('PUSH_CHANNEL_HOOK', '/plugin/webman/push/hook')),
    'auth'         => '/plugin/webman/push/auth',
    // WebSocket 连接地址（浏览器访问；H5 生产建议 /wss，由 Nginx 反代）
    'client_url'   => env('PUSH_CLIENT_URL', '/wss'),
    // 管理端订阅频道
    'admin_channel'=> env('PUSH_ADMIN_CHANNEL', 'admin-audit'),
    // H5 公告公开频道（未登录可订阅）
    'notice_channel' => env('PUSH_NOTICE_CHANNEL', 'h5-notice'),
    // H5 站内信登录私有频道（全部发送）
    'message_channel' => env('PUSH_MESSAGE_CHANNEL', 'private-h5-inbox'),
];
