<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\NotificationService;
use support\Request;

class NotificationController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '系统管理',
                'slug' => 'system-config',
                'path' => '/system-config',
                'icon' => 'setting',
                'sort' => 100,
            ],
            'menu' => [
                'name' => '系统通知',
                'slug' => 'notification-menu',
                'path' => '/system-config/notification',
                'icon' => 'bell',
                'component' => 'views/system/notification/index',
                'sort' => 5,
            ],
            'apis' => [
                ['name' => '通知列表', 'slug' => 'notification:list', 'path' => '/admin/notifications', 'method' => 'GET', 'sort' => 1],
                ['name' => '创建通知', 'slug' => 'notification:create', 'path' => '/admin/notifications', 'method' => 'POST', 'sort' => 2],
                ['name' => '标记已读', 'slug' => 'notification:read', 'path' => '/admin/notifications/*/read', 'method' => 'PUT', 'sort' => 3],
                ['name' => '全部已读', 'slug' => 'notification:read-all', 'path' => '/admin/notifications/read-all', 'method' => 'PUT', 'sort' => 4],
                ['name' => '未读数量', 'slug' => 'notification:unread-count', 'path' => '/admin/notifications/unread-count', 'method' => 'GET', 'sort' => 5],
                ['name' => '推送配置', 'slug' => 'notification:push-config', 'path' => '/admin/push/config', 'method' => 'GET', 'sort' => 6],
            ],
        ];
    }

    protected NotificationService $service;

    public function __construct(?NotificationService $service = null)
    {
        $this->service = $service ?? new NotificationService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->service->listForAdmin(
            $this->adminId($request),
            $page,
            $limit,
            $request->only(['is_read', 'type'])
        ));
    }

    public function unreadCount(Request $request)
    {
        $adminId = $this->adminId($request);

        return success([
            'count' => $this->service->unreadCount($adminId),
            'items' => $this->service->latestUnread($adminId, 20),
        ]);
    }

    public function pushConfig(Request $request)
    {
        return success($this->service->pushClientConfig());
    }

    public function store(Request $request)
    {
        $data = $request->only(['admin_id', 'title', 'content', 'type']);
        return success($this->service->create($data), '创建成功');
    }

    public function markRead(Request $request, int $id)
    {
        $this->service->markRead($id, $this->adminId($request));
        return success(null, '已标记为已读');
    }

    public function markAllRead(Request $request)
    {
        $this->service->markAllRead($this->adminId($request));
        return success(null, '全部已读');
    }
}
