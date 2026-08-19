<?php

declare(strict_types=1);

namespace app\support\Menu;

use database\support\PermissionSeeder;
use Illuminate\Database\Capsule\Manager as DB;

class AdminMenuSynchronizer
{
    private AdminMenuScanner $scanner;
    private ?int $nextId = null;
    /** @var array<string, int> */
    private array $slugToId = [];
    /** @var array<string, int> API slug -> id，便于 path/method 变更时原地更新 */
    private array $apiSlugToId = [];
    /** @var array<string, array<string, mixed>> keyed by lookupKey */
    private array $existingByKey = [];
    /** @var array<int, array<string, mixed>> keyed by permission id */
    private array $existingById = [];

    public function __construct(?AdminMenuScanner $scanner = null)
    {
        $this->scanner = $scanner ?? new AdminMenuScanner();
    }

    /**
     * @return array{menus: int, apis: int, buttons: int, removed: int}
     */
    public function sync(bool $fresh = false): array
    {
        $data = $this->scanner->flatten();
        $now = date('Y-m-d H:i:s');
        $ids = [];
        $removed = 0;
        $this->slugToId = [];
        $this->apiSlugToId = [];

        DB::transaction(function () use ($data, $now, $fresh, &$ids, &$removed) {
            $this->loadExistingPermissions();
            $this->nextId = $this->computeNextId();

            foreach ($data['menus'] as $menu) {
                $menu = $this->resolveMenuParentId($menu);
                $menuId = $this->resolveId($menu, 'menu');
                $menu['id'] = $menuId;
                $ids[] = $menuId;
                $this->upsertPermission($menu, 'menu', $now);
                $this->slugToId[$menu['slug']] = $menuId;
            }

            foreach ($data['apis'] as $api) {
                $api = $this->resolveApiParentId($api);
                $apiId = $this->resolveId($api, 'api');
                $api['id'] = $apiId;
                $ids[] = $apiId;
                $this->upsertPermission($api, 'api', $now);
            }

            foreach ($data['buttons'] as $button) {
                $button = $this->resolveButtonParentId($button);
                $buttonId = $this->resolveId($button, 'button');
                $button['id'] = $buttonId;
                $ids[] = $buttonId;
                $this->upsertPermission($button, 'button', $now);
            }

            if ($fresh && $ids !== []) {
                $removed = (int) DB::table('sy_permissions')->whereNotIn('id', $ids)->count();
                DB::table('sy_role_permissions')
                    ->whereNotIn('permission_id', $ids)
                    ->delete();
                DB::table('sy_permissions')
                    ->whereNotIn('id', $ids)
                    ->delete();
            }
        });

        PermissionSeeder::clearRedisCache();

        return [
            'menus' => count($data['menus']),
            'apis' => count($data['apis']),
            'buttons' => count($data['buttons']),
            'removed' => $removed,
        ];
    }

    private function loadExistingPermissions(): void
    {
        $this->existingByKey = [];
        $this->existingById = [];
        $this->apiSlugToId = [];

        $rows = DB::table('sy_permissions')->get();
        foreach ($rows as $row) {
            $item = (array) $row;
            $id = (int) ($item['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $this->existingById[$id] = $item;
            $type = (string) ($item['type'] ?? '');
            $key = $this->lookupKey(
                $type,
                (string) ($item['slug'] ?? ''),
                (string) ($item['path'] ?? ''),
                (string) ($item['method'] ?? '')
            );
            // 同 key 多条时保留较小 id，行为与原先 first() 接近
            if (!isset($this->existingByKey[$key]) || $id < (int) $this->existingByKey[$key]['id']) {
                $this->existingByKey[$key] = $item;
            }
            if ($type === 'menu') {
                $slug = (string) ($item['slug'] ?? '');
                if ($slug !== '' && !isset($this->slugToId[$slug])) {
                    $this->slugToId[$slug] = $id;
                }
            }
            if ($type === 'api') {
                $slug = (string) ($item['slug'] ?? '');
                if ($slug !== '' && (!isset($this->apiSlugToId[$slug]) || $id < $this->apiSlugToId[$slug])) {
                    $this->apiSlugToId[$slug] = $id;
                }
            }
        }
    }

    private function computeNextId(): int
    {
        $maxId = 0;
        foreach ($this->existingById as $id => $_) {
            if ($id > $maxId) {
                $maxId = $id;
            }
        }

        return $maxId + 1;
    }

    private function lookupKey(string $type, string $slug, string $path, string $method): string
    {
        if ($type === 'api') {
            return $type . "\0" . $slug . "\0" . $path . "\0" . strtoupper($method);
        }

        return $type . "\0" . $slug . "\0" . $path;
    }

    private function resolveButtonParentId(array $button): array
    {
        if (($button['parent_id'] ?? 0) > 0 || empty($button['parent_menu_slug'])) {
            return $button;
        }

        $button['parent_id'] = $this->lookupMenuIdBySlug((string) $button['parent_menu_slug']);
        return $button;
    }

    private function resolveMenuParentId(array $menu): array
    {
        if (($menu['parent_id'] ?? 0) > 0 || empty($menu['parent_slug'])) {
            return $menu;
        }

        $menu['parent_id'] = $this->lookupMenuIdBySlug((string) $menu['parent_slug']);
        return $menu;
    }

    private function resolveApiParentId(array $api): array
    {
        if (($api['parent_id'] ?? 0) > 0 || empty($api['parent_menu_slug'])) {
            return $api;
        }

        $api['parent_id'] = $this->lookupMenuIdBySlug((string) $api['parent_menu_slug']);
        return $api;
    }

    private function lookupMenuIdBySlug(string $slug): int
    {
        if ($slug === '') {
            return 0;
        }

        return (int) ($this->slugToId[$slug] ?? 0);
    }

    private function resolveId(array $item, string $type): int
    {
        $id = (int) ($item['id'] ?? 0);
        if ($id > 0) {
            return $id;
        }

        // API 优先按 slug 复用，避免仅 path/method 变更时插入重复 slug
        if ($type === 'api') {
            $slug = (string) ($item['slug'] ?? '');
            if ($slug !== '' && isset($this->apiSlugToId[$slug])) {
                return $this->apiSlugToId[$slug];
            }
        }

        $key = $this->lookupKey(
            $type,
            (string) ($item['slug'] ?? ''),
            (string) ($item['path'] ?? ''),
            (string) ($item['method'] ?? 'GET')
        );
        if (isset($this->existingByKey[$key])) {
            return (int) $this->existingByKey[$key]['id'];
        }

        if ($this->nextId === null) {
            $this->nextId = $this->computeNextId();
        }

        return $this->nextId++;
    }

    private function upsertPermission(array $item, string $type, string $now): void
    {
        $payload = [
            'name' => $item['name'],
            'slug' => $item['slug'],
            'path' => $item['path'],
            'method' => $type === 'api' ? $item['method'] : '',
            'parent_id' => $item['parent_id'],
            'type' => $type,
            'icon' => $type === 'menu' ? ($item['icon'] ?? '') : '',
            'component' => $type === 'menu' ? ($item['component'] ?? '') : '',
            'sort' => $item['sort'],
            'hidden' => 0,
            'updated_at' => $now,
        ];

        $id = (int) $item['id'];
        if (isset($this->existingById[$id])) {
            if (!$this->permissionChanged($this->existingById[$id], $payload)) {
                return;
            }
            DB::table('sy_permissions')->where('id', $id)->update($payload);
            $row = array_merge($this->existingById[$id], $payload, ['id' => $id]);
        } else {
            DB::table('sy_permissions')->insert(array_merge($payload, [
                'id' => $id,
                'created_at' => $now,
            ]));
            $row = array_merge($payload, ['id' => $id, 'created_at' => $now]);
        }

        $this->existingById[$id] = $row;
        $key = $this->lookupKey(
            $type,
            (string) $payload['slug'],
            (string) $payload['path'],
            (string) $payload['method']
        );
        $this->existingByKey[$key] = $row;
        if ($type === 'api') {
            $slug = (string) $payload['slug'];
            if ($slug !== '') {
                $this->apiSlugToId[$slug] = $id;
            }
        }
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $payload
     */
    private function permissionChanged(array $existing, array $payload): bool
    {
        foreach (['name', 'slug', 'path', 'method', 'type', 'icon', 'component'] as $field) {
            if ((string) ($existing[$field] ?? '') !== (string) ($payload[$field] ?? '')) {
                return true;
            }
        }
        if ((int) ($existing['parent_id'] ?? 0) !== (int) ($payload['parent_id'] ?? 0)) {
            return true;
        }
        if ((int) ($existing['sort'] ?? 0) !== (int) ($payload['sort'] ?? 0)) {
            return true;
        }
        if ((int) ($existing['hidden'] ?? 0) !== (int) ($payload['hidden'] ?? 0)) {
            return true;
        }

        return false;
    }

    public function reseedAll(): void
    {
        DB::transaction(function () {
            DB::table('sy_role_permissions')->delete();
            DB::table('sy_permissions')->delete();
            $this->sync(false);

            $editorRoleId = DB::table('sy_roles')->where('slug', 'editor')->value('id');
            if ($editorRoleId) {
                PermissionSeeder::assignEditorPermissions((int) $editorRoleId);
            }
        });

        PermissionSeeder::clearRedisCache();
    }
}
