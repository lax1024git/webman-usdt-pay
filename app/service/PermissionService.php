<?php

declare(strict_types=1);

namespace app\service;

use app\model\sys\AdminModel;
use app\model\sys\PermissionModel;
use app\model\sys\RoleModel;
use Illuminate\Database\Eloquent\Builder;
use support\Redis;

class PermissionService
{
    public function checkApiPermission(int $adminId, string $path, string $method): bool
    {
        if (
            strtoupper($method) === 'GET'
            && preg_match('#^/admin/roles/\d+/permissions$#', $path) === 1
        ) {
            $slugs = $this->getAdminPermissions($adminId);
            if (in_array('role:assign', $slugs, true) || in_array('role:list', $slugs, true)) {
                return true;
            }
        }

        // 统一导出任务：拥有任一业务导出权限即可创建/查询
        if ($this->isExportJobPath($path, $method)) {
            $slugs = $this->getAdminPermissions($adminId);
            $exportSlugs = [
                'export:list', 'export:create', 'export:show', 'export:delete',
                'member:export', 'member:export-show',
                'member-water:export', 'member-water:export-show',
                'finance-withdraw:export', 'finance-withdraw:export-show',
                'member-rw:export', 'member-rw:export-show',
                'member-credit-record:export', 'member-credit-record:export-show',
                'member-wool:export', 'member-wool:export-show', 'member-wool:observe', 'member-wool:profile',
                'member-brokerage-water:export', 'member-brokerage-water:export-show',
                'article:export', 'article:export-show',
                'member-agent:export', 'member-agent:export-show',
            ];
            foreach ($exportSlugs as $slug) {
                if (in_array($slug, $slugs, true)) {
                    return true;
                }
            }
        }

        // 活动子配置：拥有「操作·子配置」即可访问各子配置接口
        if ($this->isActivityConfigPath($path, $method)) {
            $slugs = $this->getAdminPermissions($adminId);
            if (in_array('activity-list:config', $slugs, true)) {
                return true;
            }
        }

        $permissions = $this->getAdminApiPermissions($adminId);

        foreach ($permissions as $perm) {
            if (strtoupper($perm['method']) !== strtoupper($method)) {
                continue;
            }
            if ($this->matchPath($perm['path'], $path)) {
                return true;
            }
        }
        return false;
    }

    private function isExportJobPath(string $path, string $method): bool
    {
        $method = strtoupper($method);
        if ($path === '/admin/exports' && in_array($method, ['GET', 'POST'], true)) {
            return true;
        }

        return in_array($method, ['GET', 'DELETE'], true)
            && preg_match('#^/admin/exports/\d+$#', $path) === 1;
    }

    private function isActivityConfigPath(string $path, string $method): bool
    {
        return preg_match(
            '#^/admin/activities/\d+/(recharge-bonuses|passwords|invite-tiers|sign-days|invite-multipliers)(/\d+)?$#',
            $path
        ) === 1;
    }

    public function checkButtonPermission(int $adminId, string $slug): bool
    {
        if ($this->isSuperAdmin($adminId)) {
            return true;
        }

        return in_array($slug, $this->getAdminPermissions($adminId), true);
    }

    public function assertButtonPermission(int $adminId, string $slug): void
    {
        if (!$this->checkButtonPermission($adminId, $slug)) {
            throw new \app\exception\BusinessException(\app\support\ErrorCode::FORBIDDEN, '无按钮操作权限');
        }
    }

    public function getAdminPermissions(int $adminId): array
    {
        $cacheKey = "admin:permissions:{$adminId}";
        $cached = Redis::get($cacheKey);
        if ($cached) {
            return json_decode($cached, true);
        }

        if ($this->isSuperAdmin($adminId)) {
            $slugs = PermissionModel::pluck('slug')->unique()->values()->toArray();
        } else {
            $slugs = PermissionModel::whereHas('roles.admins', function ($q) use ($adminId) {
                $q->where('sy_admins.id', $adminId);
            })->pluck('slug')->unique()->values()->toArray();
        }

        Redis::setex($cacheKey, 3600, json_encode($slugs));
        return $slugs;
    }

    public function getAdminMenus(int $adminId, array $roles): array
    {
        // 版本戳随菜单 updated_at 变化，避免 sort 变更后仍命中旧缓存
        $menuStamp = (string) (PermissionModel::where('type', 'menu')->max('updated_at') ?? '');
        $cacheKey = 'admin:menus:v3:' . $adminId . ':' . md5($menuStamp);
        $cached = Redis::get($cacheKey);
        if ($cached) {
            return json_decode($cached, true);
        }

        if (in_array('super_admin', $roles, true)) {
            $menus = PermissionModel::where('type', 'menu')
                ->where('hidden', 0)
                ->orderBy('sort')
                ->orderBy('id')
                ->get();
        } else {
            $menus = PermissionModel::where('type', 'menu')
                ->where('hidden', 0)
                ->whereHas('roles.admins', function ($q) use ($adminId) {
                    $q->where('sy_admins.id', $adminId);
                })
                ->orderBy('sort')
                ->orderBy('id')
                ->get();
        }

        $tree = $this->buildTree($menus->toArray());
        Redis::setex($cacheKey, 3600, json_encode($tree));

        return $tree;
    }

    public function getTree(?string $type = null): array
    {
        $query = PermissionModel::query()->orderBy('sort')->orderBy('id');
        if ($type) {
            $query->where('type', $type);
        }
        return $this->buildTree($query->get()->toArray());
    }

    public function clearCache(int $adminId): void
    {
        $adminId = (int) $adminId;
        $toDelete = [
            "admin:permissions:{$adminId}",
            "admin:menus:{$adminId}",
            "admin:menus:v2:{$adminId}",
            "admin:roles:{$adminId}",
        ];

        try {
            // 必须走 facade：keys 会去掉 OPT_PREFIX，del 再统一加前缀，避免连删失败
            $menuKeys = Redis::keys("admin:menus:v3:{$adminId}:*");
            if ($menuKeys !== []) {
                $toDelete = array_merge($toDelete, $menuKeys);
            }
        } catch (\Throwable) {
            // Redis 不可用时忽略，后续登录/鉴权走库
        }

        try {
            Redis::del(...array_values(array_unique($toDelete)));
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * @return list<string>
     */
    public function getAdminRoleSlugs(int $adminId): array
    {
        $cacheKey = "admin:roles:{$adminId}";
        try {
            $cached = Redis::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    return array_values(array_map('strval', $decoded));
                }
            }
        } catch (\Throwable) {
            // fall through
        }

        $slugs = AdminModel::query()
            ->where('id', $adminId)
            ->with(['roles:id,slug'])
            ->first()
            ?->roles
            ?->pluck('slug')
            ->map(static fn ($slug) => (string) $slug)
            ->values()
            ->all() ?? [];

        try {
            Redis::setex($cacheKey, 3600, json_encode($slugs));
        } catch (\Throwable) {
            // ignore
        }

        return $slugs;
    }

    public function clearCacheForRole(int $roleId): void
    {
        $adminIds = AdminModel::whereHas('roles', static function ($query) use ($roleId) {
            $query->where('sy_roles.id', $roleId);
        })->pluck('id');

        foreach ($adminIds as $adminId) {
            $this->clearCache((int) $adminId);
        }
    }

    public function clearCacheForAll(): void
    {
        foreach (AdminModel::query()->pluck('id') as $adminId) {
            $this->clearCache((int) $adminId);
        }
    }

    public function getAdminDataScope(int $adminId): string
    {
        if ($this->isSuperAdmin($adminId)) {
            return 'all';
        }

        $scopes = RoleModel::whereHas('admins', static function ($query) use ($adminId) {
            $query->where('sy_admins.id', $adminId);
        })->pluck('data_scope')->filter()->unique()->values()->all();

        if (in_array('all', $scopes, true)) {
            return 'all';
        }

        return 'self';
    }

    public function applyDataScope(Builder $query, int $adminId, string $ownerColumn = 'author_id'): Builder
    {
        $scope = $this->getAdminDataScope($adminId);
        if ($scope === 'all') {
            return $query;
        }

        return $query->where($ownerColumn, $adminId);
    }

    public function canAccessOwnedRecord(int $adminId, int $ownerId): bool
    {
        $scope = $this->getAdminDataScope($adminId);
        if ($scope === 'all') {
            return true;
        }

        return $ownerId === $adminId;
    }

    private function isSuperAdmin(int $adminId): bool
    {
        return AdminModel::query()
            ->where('id', $adminId)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'super_admin'))
            ->exists();
    }

    private function getAdminApiPermissions(int $adminId): array
    {
        return PermissionModel::where('type', 'api')
            ->whereHas('roles.admins', function ($q) use ($adminId) {
                $q->where('sy_admins.id', $adminId);
            })
            ->get(['path', 'method'])
            ->toArray();
    }

    private function matchPath(string $pattern, string $path): bool
    {
        $regex = '#^' . str_replace('\*', '[^/]+', preg_quote($pattern, '#')) . '$#';
        return (bool) preg_match($regex, $path);
    }

    private function buildTree(array $items, int $parentId = 0): array
    {
        $i18nMap = (array) config('admin_menu_i18n', []);
        $tree = [];
        foreach ($items as $item) {
            if ((int) $item['parent_id'] === $parentId) {
                $children = $this->buildTree($items, (int) $item['id']);
                $name = (string) $item['name'];
                $slug = (string) $item['slug'];
                $en = (string) ($i18nMap[$slug]['en'] ?? $name);
                $node = [
                    'id' => $item['id'],
                    'name' => $name,
                    'slug' => $slug,
                    'type' => $item['type'],
                    'path' => $item['path'] ?? '',
                    'method' => $item['method'] ?? '',
                    'icon' => $item['icon'] ?? '',
                    'component' => $item['component'] ?? '',
                    'sort' => $item['sort'] ?? 0,
                    'parent_id' => (int) ($item['parent_id'] ?? 0),
                    'hidden' => (int) ($item['hidden'] ?? 0),
                    'i18n' => [
                        'zh-CN' => $name,
                        'en' => $en,
                    ],
                ];
                if ($children !== []) {
                    $node['children'] = $children;
                }
                $tree[] = $node;
            }
        }

        usort($tree, static fn (array $a, array $b): int => ($a['sort'] <=> $b['sort']) ?: ($a['id'] <=> $b['id']));

        return $tree;
    }
}
