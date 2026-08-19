<?php

declare(strict_types=1);

namespace app\middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use app\service\PermissionService;
use app\support\ErrorCode;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $token = $request->header('Authorization');
        if (!$token || !str_starts_with($token, 'Bearer ')) {
            return fail(ErrorCode::UNAUTHORIZED);
        }

        try {
            $jwt = substr($token, 7);
            $decoded = JWT::decode($jwt, new Key(env('JWT_SECRET', 'your-secret-key'), 'HS256'));

            $request->admin_id = (int) $decoded->admin_id;
            $request->token_jti = (string) ($decoded->jti ?? '');

            $tokenSecurity = new \app\support\Security\TokenSecurityService();
            if ($tokenSecurity->isAccessTokenBlacklisted($request->token_jti)) {
                return fail(ErrorCode::TOKEN_INVALID, 'Token 已失效，请重新登录');
            }

            $permissionService = new PermissionService();
            // 角色以数据库为准，避免 JWT 内旧 roles 导致授权后仍无旧权限
            $request->admin_roles = $permissionService->getAdminRoleSlugs($request->admin_id);

            // 白名单路由：登录后即可访问（不校验 API 权限）
            if ($this->isAuthWhitelisted($request->path(), $request->method())) {
                return $next($request);
            }

            if (in_array('super_admin', $request->admin_roles, true)) {
                return $next($request);
            }

            if (!$permissionService->checkApiPermission(
                $request->admin_id,
                $request->path(),
                $request->method()
            )) {
                return fail(ErrorCode::FORBIDDEN);
            }

            return $next($request);
        } catch (\Exception $e) {
            return fail(ErrorCode::TOKEN_INVALID);
        }
    }

    private function isAuthWhitelisted(string $path, string $method = 'GET'): bool
    {
        static $whitelist = [
            '/admin/me',
            '/admin/menus',
            '/admin/server-time',
            '/admin/logout',
            '/admin/dashboard/total',
            '/admin/dashboard/userAccessSource',
            '/admin/dashboard/weeklyUserActivity',
            '/admin/dashboard/monthlySales',
            '/admin/upload/presign',
            '/api/upload',
            '/admin/notifications/unread-count',
            '/admin/push/config',
            '/admin/notifications/read-all',
        ];

        if (in_array($path, $whitelist, true)) {
            return true;
        }

        $method = strtoupper($method);

        // 下拉/筛选项等只读辅助接口：登录即可访问（options / meta / cates）
        if (
            $method === 'GET'
            && preg_match('#^/admin/[a-z0-9\-]+/(options|meta|cates)$#i', $path) === 1
        ) {
            return true;
        }

        if (
            $method === 'GET'
            && preg_match('#^/admin/dicts/code/[a-z0-9_\-]+$#i', $path) === 1
        ) {
            return true;
        }

        if (
            $method === 'PUT'
            && preg_match('#^/admin/notifications/\d+/read$#', $path) === 1
        ) {
            return true;
        }

        return str_starts_with($path, '/admin/upload/');
    }
}
