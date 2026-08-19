<?php

declare(strict_types=1);

namespace app\model\sys;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LangItemModel extends Model
{
    use DefinesTableSchema;

    /** 用户端 / H5 / API app_lang */
    public const TYPE_FRONT = 'front';

    /** 管理后台 */
    public const TYPE_ADMIN = 'admin';

    protected $table = 'sy_lang_items';

    protected $fillable = ['title', 'type'];

    public static function typeOptions(): array
    {
        return [
            self::TYPE_FRONT => '前端',
            self::TYPE_ADMIN => '后台',
        ];
    }

    public static function normalizeType(mixed $type): string
    {
        $type = strtolower(trim((string) $type));
        if ($type === self::TYPE_ADMIN || $type === '2' || $type === 'backend') {
            return self::TYPE_ADMIN;
        }
        return self::TYPE_FRONT;
    }

    public static function tableSchema(): array
    {
        return [
            'table' => 'sy_lang_items',
            'comment' => '多语言文案键表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'title' => ['type' => 'string', 'length' => 1000, 'default' => '', 'comment' => '文案键(通常为中文)'],
                'type' => [
                    'type' => 'string',
                    'length' => 20,
                    'default' => self::TYPE_FRONT,
                    'comment' => 'front前端 admin后台',
                ],
            ],
            'indexes' => [
                ['columns' => ['type'], 'name' => 'idx_sy_lang_items_type'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
        ];
    }

    public function details(): HasMany
    {
        return $this->hasMany(LangItemDetailModel::class, 'item_id');
    }
}
