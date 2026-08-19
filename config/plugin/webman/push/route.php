<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use support\Request;
use Webman\Route;
use Webman\Push\Api;

/**
 * 推送js客户端文件
 */
Route::get('/plugin/webman/push/push.js', function (Request $request) {
    return response()->file(base_path().'/vendor/webman/push/src/push.js');
});

/**
 * 私有频道鉴权：H5 站内信频道需会员 JWT
 */
Route::post(config('plugin.webman.push.app.auth'), function (Request $request) {
    $pusher = new Api(
        str_replace('0.0.0.0', '127.0.0.1', config('plugin.webman.push.app.api')),
        config('plugin.webman.push.app.app_key'),
        config('plugin.webman.push.app.app_secret')
    );
    $channelName = (string) $request->post('channel_name', '');
    $socketId = (string) $request->post('socket_id', '');

    if ($channelName === '' || $socketId === '') {
        return response('Bad Request', 400);
    }

    // 公开频道不应走私有鉴权；若误请求直接拒绝
    if (!str_starts_with($channelName, 'private-') && !str_starts_with($channelName, 'presence-')) {
        return response('Forbidden', 403);
    }

    $token = \app\middleware\MemberAuthMiddleware::extractToken($request);
    if ($token === '') {
        $token = (string) $request->post('token', '');
    }
    if ($token === '') {
        return response('Unauthorized', 401);
    }

    try {
        $decoded = \Firebase\JWT\JWT::decode(
            $token,
            new \Firebase\JWT\Key(env('JWT_SECRET', 'your-secret-key'), 'HS256')
        );
        if (($decoded->guard ?? '') !== 'member') {
            return response('Forbidden', 403);
        }
        $userId = (int) ($decoded->user_id ?? $decoded->sub ?? 0);
        if ($userId <= 0) {
            return response('Forbidden', 403);
        }

        $inboxChannel = (string) config('plugin.webman.push.app.message_channel', 'private-h5-inbox');
        $hasAuthority = false;
        if ($channelName === $inboxChannel) {
            $hasAuthority = true;
        } elseif (preg_match('/^private-user-(\d+)$/', $channelName, $m)) {
            $hasAuthority = ((int) $m[1] === $userId);
        }

        if (!$hasAuthority) {
            return response('Forbidden', 403);
        }

        return response($pusher->socketAuth($channelName, $socketId));
    } catch (\Firebase\JWT\ExpiredException|\Firebase\JWT\SignatureInvalidException|\Firebase\JWT\BeforeValidException|\UnexpectedValueException) {
        return response('Unauthorized', 401);
    } catch (\Webman\Push\PushException $e) {
        return response('Bad Request: ' . $e->getMessage(), 400);
    } catch (\Throwable) {
        return response('Unauthorized', 401);
    }
});

/**
 * 当频道上线以及下线时触发的回调
 * 频道上线：是指某个频道从没有连接在线到有连接在线的事件
 * 频道下线：是指某个频道的所有连接都断开触发的事件
 */
Route::post(parse_url(config('plugin.webman.push.app.channel_hook'), PHP_URL_PATH), function (Request $request) {

    // 没有x-pusher-signature头视为伪造请求
    if (!$webhook_signature = $request->header('x-pusher-signature')) {
        return response('401 Not authenticated', 401);
    }

    $body = $request->rawBody();

    // 计算签名，$app_secret 是双方使用的密钥，是保密的，外部无从得知
    $expected_signature = hash_hmac('sha256', $body, config('plugin.webman.push.app.app_secret'), false);

    // 安全校验，如果签名不一致可能是伪造的请求，返回401状态码
    if ($webhook_signature !== $expected_signature) {
        return response('401 Not authenticated', 401);
    }

    // 这里存储这上线 下线的channel数据
    $payload = json_decode($body, true);

    $channels_online = $channels_offline = [];

    foreach ($payload['events'] as $event) {
        if ($event['name'] === 'channel_added') {
            $channels_online[] = $event['channel'];
        } else if ($event['name'] === 'channel_removed') {
            $channels_offline[] = $event['channel'];
        }
    }

    // 业务根据需要处理上下线的channel，例如将在线状态写入数据库，通知其它channel等
    // 上线的所有channel
    echo 'online channels: ' . implode(',', $channels_online) . "\n";
    // 下线的所有channel
    echo 'offline channels: ' . implode(',', $channels_offline) . "\n";

    return 'OK';
});



