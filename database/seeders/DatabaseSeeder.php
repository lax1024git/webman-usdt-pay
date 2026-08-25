<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as DB;
use app\support\Menu\AdminMenuSynchronizer;

return new class {
    public function run(): void
    {
        $rolesSeeded = DB::table('sy_roles')->where('slug', 'super_admin')->exists();

        if (!$rolesSeeded) {
            echo "  -> Seeding roles...\n";
            $superAdminRoleId = DB::table('sy_roles')->insertGetId([
                'name' => '超级管理员',
                'slug' => 'super_admin',
                'description' => '拥有所有权限',
                'data_scope' => 'all',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $editorRoleId = DB::table('sy_roles')->insertGetId([
                'name' => '编辑员',
                'slug' => 'editor',
                'description' => '只读/基础系统权限',
                'data_scope' => 'self',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            echo "  -> Seeding permissions...\n";
            (new AdminMenuSynchronizer())->sync(false);

            echo "  -> Seeding admin user...\n";
            $adminId = DB::table('sy_admins')->insertGetId([
                'username' => 'admin',
                'password' => password_hash('admin123', PASSWORD_BCRYPT),
                'nickname' => '超级管理员',
                'avatar' => '',
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            DB::table('sy_admin_roles')->insert(['admin_id' => $adminId, 'role_id' => $superAdminRoleId]);

            \database\support\PermissionSeeder::assignEditorPermissions($editorRoleId);

            echo "  -> Seeding welcome notification...\n";
            DB::table('sy_admin_notifications')->insert([
                'admin_id' => 0,
                'title' => '欢迎使用后台管理系统',
                'content' => '当前为纯管理壳：登录、RBAC、系统配置、字典、日志、上传等。',
                'type' => 'announcement',
                'is_read' => 0,
                'read_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            echo "  -> Seeding settings...\n";
            DB::table('sy_settings')->insert([
                ['key' => 'name', 'value' => json_encode('USDT Admin'), 'description' => '应用名称', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
                ['key' => 'logo', 'value' => json_encode(''), 'description' => '网站 logo', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
                ['key' => 'admin_icon', 'value' => json_encode(''), 'description' => '网站 icon', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
                ['key' => 'admin_google_auth_status', 'value' => json_encode('1'), 'description' => '后台操作谷歌验证', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
                ['key' => 's3_config', 'value' => json_encode([
                    'credentials_key' => '',
                    'credentials_secret' => '',
                    'region' => 'ap-east-1',
                    'bucket' => '',
                    'url' => '',
                    'proxy' => null,
                    'presign_expires' => 900,
                ], JSON_UNESCAPED_UNICODE), 'description' => 'S3 对象存储配置', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ]);
        }

        \database\seeders\PaySeeder::run();
    }
};
