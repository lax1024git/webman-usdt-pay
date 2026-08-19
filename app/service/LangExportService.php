<?php

declare(strict_types=1);

namespace app\service;

use app\model\sys\LangItemDetailModel;
use app\model\sys\LangItemModel;
use app\model\sys\LangModel;

class LangExportService
{
    public function exportAll(): int
    {
        $dir = base_path('resource/translations');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("无法创建翻译目录: {$dir}");
        }

        $langs = LangModel::where('status', 1)->orderByDesc('is_default')->orderBy('sort')->get();
        // 仅导出前端文案到 resource/translations（供 app_lang / 用户端接口使用）
        $items = LangItemModel::query()
            ->where(function ($q) {
                $q->where('type', LangItemModel::TYPE_FRONT)
                    ->orWhereNull('type')
                    ->orWhere('type', '');
            })
            ->orderBy('id')
            ->get(['id', 'title']);
        $details = LangItemDetailModel::query()->get(['lang_id', 'item_id', 'text']);
        $detailMap = [];
        foreach ($details as $row) {
            $detailMap[(int) $row->lang_id . '_' . (int) $row->item_id] = (string) $row->text;
        }

        $count = 0;
        foreach ($langs as $lang) {
            $config = [];
            foreach ($items as $item) {
                $key = (int) $lang->id . '_' . (int) $item->id;
                if (isset($detailMap[$key]) && $detailMap[$key] !== '') {
                    $config[(string) $item->title] = $detailMap[$key];
                }
            }
            $file = $dir . '/' . $this->fileName((string) $lang->lang);
            // 数据库翻译为空时，不覆盖已有语言包（避免清空过渡/手工补全）
            if ($config === [] && is_file($file)) {
                continue;
            }
            $code = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
            file_put_contents($file, $code);
            $count++;
        }

        return $count;
    }

    public function loadForLocale(string $apiLocale): array
    {
        $data = [];
        // en-us / en_us 等别名回落到 en.php、overlays/en.php
        foreach (\app\support\Locale::aliases($apiLocale) as $code) {
            $file = base_path('resource/translations/' . $this->fileName($code));
            if (is_file($file)) {
                $loaded = include $file;
                if (is_array($loaded) && $loaded !== []) {
                    $data = array_merge($data, $loaded);
                }
            }

            // 运行时覆盖：优先于导出包（ErrorCode / 接口 msg / status_text 等）
            $overlay = base_path('resource/translations/overlays/' . $this->fileName($code));
            if (is_file($overlay)) {
                $extra = include $overlay;
                if (is_array($extra) && $extra !== []) {
                    $data = array_merge($data, $extra);
                }
            }
        }

        // sy_lang_items（front + admin）合并进来，保证后台/接口文案即时生效
        $fromDb = $this->loadFromDatabase($apiLocale);
        if ($fromDb !== []) {
            $data = array_merge($data, $fromDb);
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private function loadFromDatabase(string $apiLocale): array
    {
        $aliases = \app\support\Locale::aliases($apiLocale);
        $langIds = LangModel::whereIn('lang', $aliases)->pluck('id')->all();
        if ($langIds === []) {
            return [];
        }

        $details = LangItemDetailModel::query()
            ->whereIn('lang_id', $langIds)
            ->where('text', '!=', '')
            ->get(['item_id', 'text']);
        if ($details->isEmpty()) {
            return [];
        }

        $titles = LangItemModel::whereIn('id', $details->pluck('item_id')->unique()->all())
            ->pluck('title', 'id');

        $map = [];
        foreach ($details as $row) {
            $title = trim((string) ($titles[(int) $row->item_id] ?? ''));
            $text = trim((string) $row->text);
            if ($title !== '' && $text !== '') {
                $map[$title] = $text;
            }
        }

        return $map;
    }

    private function fileName(string $langCode): string
    {
        return str_replace('-', '_', strtolower($langCode)) . '.php';
    }
}
