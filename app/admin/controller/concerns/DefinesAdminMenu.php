<?php

declare(strict_types=1);

namespace app\admin\controller\concerns;

/**
 * 在 Admin 控制器中声明菜单与 API 权限，供 `php webman menu` 扫描同步。
 * id 可省略，同步时按 slug/path 自动分配或复用数据库 ID。
 *
 * @example
 * public static function menuConfig(): ?array
 * {
 *     return [
 *         'group' => ['name' => '权限管理', 'slug' => 'auth', 'path' => '/auth', 'icon' => 'lock', 'sort' => 90],
 *         'menu' => ['name' => '管理员', 'slug' => 'admin-menu', 'path' => '/auth/admin', 'icon' => 'user', 'component' => 'views/system/admin/index', 'sort' => 1],
 *         'apis' => [
 *             ['name' => '管理员列表', 'slug' => 'admin:list', 'path' => '/admin/admins', 'method' => 'GET', 'sort' => 1],
 *         ],
 *     ];
 * }
 *
 * 菜单英文标题维护在 config/admin_menu_i18n.php（按 slug），接口 /admin/menus 会下发 i18n，前端无需因改名重新打包。
 */
trait DefinesAdminMenu
{
    public static function menuConfig(): ?array
    {
        return null;
    }
}
