<?php

declare(strict_types=1);

namespace app\service;

use app\model\sys\LangItemDetailModel;
use app\model\sys\LangItemModel;
use app\model\sys\LangModel;

class LangTextService
{
    public function __construct(private ?LangExportService $exportService = null)
    {
        $this->exportService = $exportService ?? new LangExportService();
    }

    /**
     * 语言文案只处理：启用 + 允许用户端切换 的语言。
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, LangModel>
     */
    private function workingLangs()
    {
        return LangModel::query()
            ->where('status', 1)
            ->where('switch_enabled', 1)
            ->orderByDesc('is_default')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = LangItemModel::query()->orderByDesc('id');
        if (!empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where('title', 'like', "%{$kw}%");
        }
        if (isset($filters['type']) && $filters['type'] !== '' && $filters['type'] !== null) {
            $query->where('type', LangItemModel::normalizeType($filters['type']));
        }

        $total = (clone $query)->count();
        $rows = $query->forPage($page, $limit)->get();
        $langs = $this->workingLangs();
        $itemIds = $rows->pluck('id')->all();

        $details = [];
        if ($itemIds !== []) {
            $detailRows = LangItemDetailModel::whereIn('item_id', $itemIds)->get();
            foreach ($detailRows as $detail) {
                $details[(int) $detail->item_id][(int) $detail->lang_id] = (string) $detail->text;
            }
        }

        $items = $rows->map(function (LangItemModel $row) use ($langs, $details): array {
            $texts = [];
            foreach ($langs as $lang) {
                $texts[(string) $lang->lang] = $details[(int) $row->id][(int) $lang->id] ?? '';
            }
            return [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'type' => LangItemModel::normalizeType($row->type ?? LangItemModel::TYPE_FRONT),
                'texts' => $texts,
                'created_at' => (string) $row->created_at,
                'updated_at' => (string) $row->updated_at,
            ];
        })->all();

        return [
            'total' => $total,
            'items' => $items,
            'langs' => $langs->map(fn (LangModel $l) => [
                'id' => (int) $l->id,
                'title' => (string) $l->title,
                'lang' => (string) $l->lang,
                'remark' => (string) ($l->remark ?? ''),
            ])->all(),
            'type_options' => collect(LangItemModel::typeOptions())
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
        ];
    }

    public function show(int $id): array
    {
        $row = LangItemModel::findOrFail($id);
        $langs = $this->workingLangs();
        $details = LangItemDetailModel::where('item_id', $id)->get()->keyBy('lang_id');

        $texts = [];
        foreach ($langs as $lang) {
            $texts[(string) $lang->lang] = (string) ($details[(int) $lang->id]->text ?? '');
        }

        return [
            'id' => (int) $row->id,
            'title' => (string) $row->title,
            'type' => LangItemModel::normalizeType($row->type ?? LangItemModel::TYPE_FRONT),
            'texts' => $texts,
        ];
    }

    public function save(array $data): array
    {
        $id = (int) ($data['id'] ?? 0);
        $title = trim((string) ($data['title'] ?? ''));
        $type = LangItemModel::normalizeType($data['type'] ?? LangItemModel::TYPE_FRONT);
        if ($title === '') {
            throw new \InvalidArgumentException('请填写文案键');
        }

        if ($id > 0) {
            $item = LangItemModel::findOrFail($id);
            $item->title = $title;
            $item->type = $type;
            $item->save();
        } else {
            $item = LangItemModel::firstOrCreate(
                ['title' => $title, 'type' => $type],
                ['title' => $title, 'type' => $type]
            );
        }

        $texts = (array) ($data['texts'] ?? []);
        $langs = $this->workingLangs();
        foreach ($langs as $lang) {
            $code = (string) $lang->lang;
            if (!array_key_exists($code, $texts)) {
                continue;
            }
            LangItemDetailModel::updateOrCreate(
                ['lang_id' => (int) $lang->id, 'item_id' => (int) $item->id],
                ['text' => trim((string) $texts[$code])]
            );
        }

        $this->exportService->exportAll();
        return $this->show((int) $item->id);
    }

    public function delete(int $id): void
    {
        LangItemDetailModel::where('item_id', $id)->delete();
        LangItemModel::where('id', $id)->delete();
        $this->exportService->exportAll();
    }

    public function export(): int
    {
        return $this->exportService->exportAll();
    }

    /**
     * 导入翻译文案。
     *
     * 支持：
     * 1) PHP/JSON 键值对：{"中文键":"译文"} —— 需指定 lang
     * 2) JSON 数组：[{"title":"...","type":"front","texts":{"en":"..."}}]
     *
     * @return array{created:int,updated:int,details:int,skipped:int}
     */
    public function import(array $mapOrRows, array $options = []): array
    {
        $type = LangItemModel::normalizeType($options['type'] ?? LangItemModel::TYPE_FRONT);
        $langCode = isset($options['lang']) ? trim((string) $options['lang']) : '';
        $overwrite = !empty($options['overwrite']);

        $stats = ['created' => 0, 'updated' => 0, 'details' => 0, 'skipped' => 0];

        // 规范化为行列表：title + texts
        $rows = $this->normalizeImportPayload($mapOrRows, $langCode);
        if ($rows === []) {
            throw new \InvalidArgumentException('导入内容为空或格式不正确');
        }

        $langs = $this->workingLangs()->keyBy(fn (LangModel $l) => (string) $l->lang);
        // 也按 normalize 别名索引
        $langByAlias = [];
        foreach ($langs as $code => $lang) {
            foreach (\app\support\Locale::aliases($code) as $alias) {
                $langByAlias[strtolower($alias)] = $lang;
            }
            $langByAlias[strtolower(str_replace('_', '-', $code))] = $lang;
        }

        foreach ($rows as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                $stats['skipped']++;
                continue;
            }
            $rowType = LangItemModel::normalizeType($row['type'] ?? $type);
            $texts = (array) ($row['texts'] ?? []);

            $item = LangItemModel::where('title', $title)->where('type', $rowType)->first();
            if ($item) {
                $stats['updated']++;
            } else {
                $item = LangItemModel::create(['title' => $title, 'type' => $rowType]);
                $stats['created']++;
            }

            foreach ($texts as $code => $text) {
                $text = trim((string) $text);
                if ($text === '') {
                    continue;
                }
                $codeKey = strtolower(str_replace('_', '-', (string) $code));
                $lang = $langByAlias[$codeKey] ?? $langs[(string) $code] ?? null;
                if (!$lang) {
                    $stats['skipped']++;
                    continue;
                }

                $existing = LangItemDetailModel::where('lang_id', (int) $lang->id)
                    ->where('item_id', (int) $item->id)
                    ->first();
                if ($existing && trim((string) $existing->text) !== '' && !$overwrite) {
                    $stats['skipped']++;
                    continue;
                }

                LangItemDetailModel::updateOrCreate(
                    ['lang_id' => (int) $lang->id, 'item_id' => (int) $item->id],
                    ['text' => $text]
                );
                $stats['details']++;
            }
        }

        if ($type === LangItemModel::TYPE_FRONT || collect($rows)->contains(fn ($r) => LangItemModel::normalizeType($r['type'] ?? $type) === LangItemModel::TYPE_FRONT)) {
            $this->exportService->exportAll();
        }

        return $stats;
    }

    /**
     * 解析上传文件内容为数组（PHP return / JSON）。
     */
    public function parseImportFile(string $pathname, string $originalName = ''): array
    {
        if (!is_file($pathname)) {
            throw new \InvalidArgumentException('文件不存在');
        }

        $raw = (string) file_get_contents($pathname);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $ext = strtolower(pathinfo($originalName !== '' ? $originalName : $pathname, PATHINFO_EXTENSION));

        if ($ext === 'json' || str_starts_with(ltrim($raw), '{') || str_starts_with(ltrim($raw), '[')) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new \InvalidArgumentException('JSON 解析失败');
            }
            return $decoded;
        }

        if ($ext === 'php' || str_contains($raw, '<?php') || str_contains($raw, 'return')) {
            $data = include $pathname;
            if (!is_array($data)) {
                throw new \InvalidArgumentException('PHP 语言包必须 return 数组');
            }
            return $data;
        }

        throw new \InvalidArgumentException('仅支持 .php / .json 文件');
    }

    /**
     * 从文件名猜测语言码：en.php / zh_cn.php / en.json
     */
    public function guessLangFromFilename(string $filename): string
    {
        $base = strtolower(pathinfo($filename, PATHINFO_FILENAME));
        $base = str_replace('_', '-', $base);
        return \app\support\Locale::normalize($base);
    }

    /**
     * @return list<array{title:string,type?:string,texts:array<string,string>}>
     */
    private function normalizeImportPayload(array $payload, string $langCode): array
    {
        // JSON 数组行格式
        if ($payload !== [] && array_is_list($payload)) {
            $first = $payload[0] ?? null;
            if (is_array($first) && (isset($first['title']) || isset($first['texts']))) {
                $rows = [];
                foreach ($payload as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $rows[] = [
                        'title' => (string) ($item['title'] ?? ''),
                        'type' => $item['type'] ?? null,
                        'texts' => (array) ($item['texts'] ?? []),
                    ];
                }
                return $rows;
            }
        }

        // 键值对：title => text（单语言）
        if ($langCode === '') {
            throw new \InvalidArgumentException('键值对格式导入时请选择目标语言');
        }

        $rows = [];
        foreach ($payload as $title => $text) {
            if (!is_string($title) && !is_int($title)) {
                continue;
            }
            if (is_array($text)) {
                // 偶发 {"title":{"en":"..."}} 不支持，跳过
                continue;
            }
            $rows[] = [
                'title' => (string) $title,
                'texts' => [$langCode => (string) $text],
            ];
        }
        return $rows;
    }

    /**
     * 一键翻译预览（不落库）：以文案键为源，填充可切换语言译文。
     *
     * @param  array{title?:string,overwrite?:bool|int,existing?:array<string,string>}  $input
     * @return array{texts:array<string,string>,translated:list<string>,skipped:list<string>}
     */
    public function translatePreview(array $input): array
    {
        $sourceText = trim((string) ($input['title'] ?? ''));
        if ($sourceText === '') {
            throw new \InvalidArgumentException('请先填写文案键');
        }

        $overwrite = !empty($input['overwrite']);
        $existingMap = is_array($input['existing'] ?? null) ? $input['existing'] : [];

        return $this->translateTexts($sourceText, $existingMap, $overwrite);
    }

    /**
     * 一键翻译：以文案键(中文)为源，填充各语言译文并落库。
     *
     * @param  bool  $overwrite  true 时覆盖已有译文；false 仅填空
     * @return array{id:int,title:string,type:string,texts:array,translated:list<string>,skipped:list<string>}
     */
    public function translateItem(int $id, bool $overwrite = false): array
    {
        $item = LangItemModel::find($id);
        if (!$item) {
            throw new \InvalidArgumentException('文案不存在');
        }

        $sourceText = trim((string) $item->title);
        if ($sourceText === '') {
            throw new \InvalidArgumentException('文案键为空，无法翻译');
        }

        $langs = $this->workingLangs();
        $details = LangItemDetailModel::where('item_id', $id)->get()->keyBy('lang_id');
        $existingMap = [];
        foreach ($langs as $lang) {
            $existingMap[(string) $lang->lang] = trim((string) ($details[(int) $lang->id]->text ?? ''));
        }

        $result = $this->translateTexts($sourceText, $existingMap, $overwrite);

        foreach ($langs as $lang) {
            $code = (string) $lang->lang;
            if (!in_array($code, $result['translated'], true)) {
                continue;
            }
            LangItemDetailModel::updateOrCreate(
                ['lang_id' => (int) $lang->id, 'item_id' => $id],
                ['text' => (string) ($result['texts'][$code] ?? '')]
            );
        }

        if ($result['translated'] !== [] && LangItemModel::normalizeType($item->type ?? '') === LangItemModel::TYPE_FRONT) {
            $this->exportService->exportAll();
        }

        return [
            'id' => (int) $item->id,
            'title' => $sourceText,
            'type' => LangItemModel::normalizeType($item->type ?? LangItemModel::TYPE_FRONT),
            'texts' => $result['texts'],
            'translated' => $result['translated'],
            'skipped' => $result['skipped'],
        ];
    }

    /**
     * @param  array<string,string>  $existingMap
     * @return array{texts:array<string,string>,translated:list<string>,skipped:list<string>}
     */
    private function translateTexts(string $sourceText, array $existingMap, bool $overwrite): array
    {
        $langs = $this->workingLangs();
        $translated = [];
        $skipped = [];
        $texts = [];

        foreach ($langs as $lang) {
            $code = (string) $lang->lang;
            $existing = trim((string) ($existingMap[$code] ?? ''));
            $texts[$code] = $existing;

            if ($existing !== '' && !$overwrite) {
                $skipped[] = $code;
                continue;
            }

            if (GoogleTranslate::toGoogleCode($code) === 'zh-CN') {
                $text = $sourceText;
            } else {
                try {
                    $text = GoogleTranslate::translate('zh-CN', $code, $sourceText);
                } catch (\Throwable $e) {
                    throw new \RuntimeException("翻译到 {$code} 失败: " . $e->getMessage(), 0, $e);
                }
                usleep(200000);
            }

            if ($text === '') {
                $skipped[] = $code;
                continue;
            }

            $texts[$code] = $text;
            $translated[] = $code;
        }

        return [
            'texts' => $texts,
            'translated' => $translated,
            'skipped' => $skipped,
        ];
    }

    /**
     * 批量翻译任务（供 CLI 使用）。
     *
     * @return array{total:int,success:int,failed:int,errors:list<string>}
     */
    public function translateBatch(array $options = []): array
    {
        $overwrite = !empty($options['overwrite']);
        $onlyEmpty = !empty($options['only_empty']);
        $type = isset($options['type']) && $options['type'] !== '' && $options['type'] !== null
            ? LangItemModel::normalizeType($options['type'])
            : null;
        $id = (int) ($options['id'] ?? 0);
        $limit = max(0, (int) ($options['limit'] ?? 0));

        $query = LangItemModel::query()->orderBy('id');
        if ($id > 0) {
            $query->where('id', $id);
        }
        if ($type !== null) {
            $query->where('type', $type);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $items = $query->get(['id']);
        $result = ['total' => $items->count(), 'success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($items as $row) {
            $itemId = (int) $row->id;
            try {
                if ($onlyEmpty && !$overwrite) {
                    // 若所有可切换语言都已有译文则跳过
                    $langs = $this->workingLangs()->pluck('id')->all();
                    if ($langs !== []) {
                        $filled = LangItemDetailModel::where('item_id', $itemId)
                            ->whereIn('lang_id', $langs)
                            ->where('text', '!=', '')
                            ->count();
                        if ($filled >= count($langs)) {
                            continue;
                        }
                    }
                }
                $this->translateItem($itemId, $overwrite);
                $result['success']++;
            } catch (\Throwable $e) {
                $result['failed']++;
                $result['errors'][] = "#{$itemId}: " . $e->getMessage();
            }
        }

        return $result;
    }
}
