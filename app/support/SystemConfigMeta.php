<?php

declare(strict_types=1);

namespace app\support;

use app\model\sys\LangModel;

final class SystemConfigMeta
{
  /** @return list<array{value: string, label: string}> */
    public static function languages(): array
    {
        return LangModel::where('status', 1)
            ->orderByDesc('is_default')
            ->orderBy('sort')
            ->get()
            ->map(static fn (LangModel $row) => [
                'value' => (string) $row->lang,
                'label' => (string) $row->title,
            ])
            ->all();
    }

  /** @return array<string, list<array{value: string, label: string}>>> */
    public static function optionSets(): array
    {
        return [
            'languages' => self::languages(),
        ];
    }
}
