<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\ExportJobService;
use support\Request;

class ExportController extends BaseController
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
                'name' => '导出任务',
                'slug' => 'export-job-menu',
                'path' => '/system-config/exports',
                'icon' => 'documentation',
                'component' => 'views/system/export/index',
                'sort' => 7,
            ],
            'apis' => [
                ['name' => '导出任务列表', 'slug' => 'export:list', 'path' => '/admin/exports', 'method' => 'GET', 'sort' => 1],
                ['name' => '创建导出任务', 'slug' => 'export:create', 'path' => '/admin/exports', 'method' => 'POST', 'sort' => 2],
                ['name' => '导出任务进度', 'slug' => 'export:show', 'path' => '/admin/exports/*', 'method' => 'GET', 'sort' => 3],
                ['name' => '删除导出任务', 'slug' => 'export:delete', 'path' => '/admin/exports/*', 'method' => 'DELETE', 'sort' => 4],
            ],
        ];
    }

    protected ExportJobService $service;

    public function __construct(?ExportJobService $service = null)
    {
        $this->service = $service ?? new ExportJobService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['export_type', 'status', 'operator_id', 'start_date', 'end_date']);

        return success($this->service->list($page, $limit, $filters));
    }

    public function store(Request $request)
    {
        $type = (string) $request->post('export_type', $request->post('type', ''));
        $filters = $request->post('filters', []);
        if (!is_array($filters)) {
            $filters = [];
        }

        // 兼容把筛选字段直接放在 body 顶层
        if ($filters === []) {
            $payload = $request->post();
            if (is_array($payload)) {
                unset($payload['export_type'], $payload['type'], $payload['filters']);
                $filters = $payload;
            }
        }

        return success(
            $this->service->createJob($type, $filters, $this->adminId($request)),
            '导出任务已创建'
        );
    }

    public function show(Request $request, int $id)
    {
        return success($this->service->showJob($id));
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->deleteJob($id);

        return success(null, '删除成功');
    }
}
