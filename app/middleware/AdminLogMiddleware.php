<?php

declare(strict_types=1);

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use app\service\LogService;

class AdminLogMiddleware implements MiddlewareInterface
{
    private const LOG_METHODS = ['POST', 'PUT', 'DELETE'];

    /** @var array<string, string> 路径模块 => 中文名（description 只用中文） */
    private const MODULE_LABELS = [
        'admins' => '管理员',
        'roles' => '角色',
        'permissions' => '权限',
        'menus' => '菜单',
        'me' => '个人中心',
        'logout' => '退出登录',
        'settings' => '系统设置',
        'system-config' => '系统配置',
        'users' => '会员',
        'members' => '会员',
        'agents' => '代理',
        'recharges' => '充值',
        'withdraws' => '提现',
        'platforms' => '支付通道',
        'pay-platforms' => '支付通道',
        'notices' => '公告',
        'articles' => '文章',
        'banners' => '轮播图',
        'activities' => '活动',
        'videos' => '视频',
        'customer-services' => '客服',
        'ad-configs' => '广告配置',
        'ad-products' => '广告产品',
        'investment-products' => '托管产品',
        'finance-products' => '理财产品',
        'reports' => '报表',
        'auth' => '认证',
        'logs' => '操作日志',
        'upload' => '上传',
        'uploads' => '上传',
        'ip-whitelists' => 'IP白名单',
        'langs' => '语言',
        'lang-texts' => '语言文案',
        'dicts' => '字典',
        'notifications' => '站内信',
        'dashboard' => '仪表盘',
        'unknown' => '未知模块',
    ];

    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        if (in_array($request->method(), self::LOG_METHODS, true) && isset($request->admin_id)) {
            $this->recordLog($request);
        }

        return $response;
    }

    private function recordLog(Request $request): void
    {
        $path = $request->path();
        $module = $this->extractModule($path);
        $action = $this->extractAction($request->method());
        $actionLabel = $this->actionLabel($action);
        $moduleLabel = $this->moduleLabel($module);

        (new LogService())->record(
            adminId: (int) $request->admin_id,
            module: $module,
            action: $action,
            description: "{$actionLabel}{$moduleLabel}",
            requestData: $request->all(),
            ip: $request->getRealIp(),
            userAgent: $request->header('User-Agent', '')
        );
    }

    private function extractModule(string $path): string
    {
        $parts = array_values(array_filter(explode('/', $path)));
        return $parts[1] ?? 'unknown';
    }

    private function extractAction(string $method): string
    {
        return match ($method) {
            'POST' => 'create',
            'PUT' => 'update',
            'DELETE' => 'delete',
            default => 'unknown',
        };
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'create' => '创建',
            'update' => '更新',
            'delete' => '删除',
            default => '操作',
        };
    }

    private function moduleLabel(string $module): string
    {
        // 未映射模块也不写入英文 slug，避免 description 出现英文
        return self::MODULE_LABELS[$module] ?? '相关数据';
    }
}
