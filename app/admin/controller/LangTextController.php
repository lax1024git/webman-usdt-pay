<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\LangTextService;
use support\Request;

class LangTextController extends BaseController
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
                'name' => '翻译文案',
                'slug' => 'lang-text-menu',
                'path' => '/system-config/lang-text',
                'icon' => 'documentation',
                'component' => 'views/system/lang-text/index',
                'sort' => 4,
            ],
            'apis' => [
                ['name' => '文案列表', 'slug' => 'lang-text:list', 'path' => '/admin/lang-texts', 'method' => 'GET', 'sort' => 1],
                ['name' => '文案详情', 'slug' => 'lang-text:show', 'path' => '/admin/lang-texts/*', 'method' => 'GET', 'sort' => 2],
                ['name' => '保存文案', 'slug' => 'lang-text:save', 'path' => '/admin/lang-texts', 'method' => 'POST', 'sort' => 3],
                ['name' => '删除文案', 'slug' => 'lang-text:delete', 'path' => '/admin/lang-texts/*', 'method' => 'DELETE', 'sort' => 4],
                ['name' => '导出翻译', 'slug' => 'lang-text:export', 'path' => '/admin/lang-texts/export', 'method' => 'POST', 'sort' => 5],
                ['name' => '一键翻译', 'slug' => 'lang-text:translate', 'path' => '/admin/lang-texts/*/translate', 'method' => 'POST', 'sort' => 6],
                ['name' => '翻译预览', 'slug' => 'lang-text:translate-preview', 'path' => '/admin/lang-texts/translate', 'method' => 'POST', 'sort' => 7],
                ['name' => '导入翻译', 'slug' => 'lang-text:import', 'path' => '/admin/lang-texts/import', 'method' => 'POST', 'sort' => 8],
            ],
        ];
    }

    public function __construct(private ?LangTextService $service = null)
    {
        $this->service = $service ?? new LangTextService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->service->list($page, $limit, $request->only(['keyword', 'type'])));
    }

    public function show(Request $request, int $id)
    {
        return success($this->service->show($id));
    }

    public function store(Request $request)
    {
        try {
            return success($this->service->save($request->post()), '保存成功');
        } catch (\InvalidArgumentException $e) {
            return fail(42201, $e->getMessage());
        }
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->delete($id);
        return success($id, '删除成功');
    }

    public function export(Request $request)
    {
        $count = $this->service->export();
        return success(['files' => $count], '导出成功');
    }

    public function import(Request $request)
    {
        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return fail(42201, '请上传 PHP 或 JSON 文件');
        }

        $type = (string) $request->post('type', 'front');
        $lang = trim((string) $request->post('lang', ''));
        $overwrite = (int) $request->post('overwrite', 0) === 1;
        $originalName = (string) ($file->getUploadName() ?: $file->getName() ?: '');

        try {
            $payload = $this->service->parseImportFile($file->getPathname(), $originalName);
            if ($lang === '' && !array_is_list($payload)) {
                $lang = $this->service->guessLangFromFilename($originalName);
            }
            $stats = $this->service->import($payload, [
                'type' => $type,
                'lang' => $lang,
                'overwrite' => $overwrite,
            ]);
            return success($stats, sprintf(
                '导入完成：新建 %d，更新 %d，写入译文 %d，跳过 %d',
                $stats['created'],
                $stats['updated'],
                $stats['details'],
                $stats['skipped']
            ));
        } catch (\InvalidArgumentException $e) {
            return fail(42201, $e->getMessage());
        } catch (\Throwable $e) {
            return fail(50000, $e->getMessage());
        }
    }

    public function translate(Request $request, int $id)
    {
        $overwrite = (int) $request->post('overwrite', $request->get('overwrite', 0)) === 1;
        try {
            $data = $this->service->translateItem($id, $overwrite);
            $count = count($data['translated'] ?? []);
            return success($data, $count > 0 ? "已翻译 {$count} 种语言" : '无需翻译（译文已存在）');
        } catch (\InvalidArgumentException $e) {
            return fail(42201, $e->getMessage());
        } catch (\Throwable $e) {
            return fail(50000, $e->getMessage());
        }
    }

    public function translatePreview(Request $request)
    {
        $data = $request->only(['title', 'overwrite', 'existing']);
        try {
            $result = $this->service->translatePreview($data);
            $count = count($result['translated'] ?? []);
            return success($result, $count > 0 ? "已翻译 {$count} 种语言" : '无需翻译');
        } catch (\InvalidArgumentException $e) {
            return fail(42201, $e->getMessage());
        } catch (\Throwable $e) {
            return fail(50000, $e->getMessage());
        }
    }
}
