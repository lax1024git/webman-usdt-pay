<?php

declare(strict_types=1);

namespace app\model\pay;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchantModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'pa_merchants';

    protected $fillable = [
        'merchant_no', 'name', 'api_key', 'api_secret', 'notify_url', 'ip_whitelist',
        'status', 'deposit_fee_rate', 'withdraw_fee_rate', 'withdraw_fee_min', 'withdraw_fee_max',
        'auto_withdraw_max', 'remark',
        'login_email', 'login_password', 'login_google_secret', 'last_login_at', 'last_login_ip',
    ];

    protected $casts = [
        'ip_whitelist' => 'array',
        'status' => 'integer',
    ];

    protected $hidden = ['api_secret', 'login_password', 'login_google_secret'];

    public function accounts(): HasMany
    {
        return $this->hasMany(MerchantAccountModel::class, 'merchant_id');
    }

    public static function tableSchema(): array
    {
        return [
            'table' => 'pa_merchants',
            'comment' => '支付商户',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键'],
                'merchant_no' => ['type' => 'string', 'length' => 32, 'comment' => '商户号'],
                'name' => ['type' => 'string', 'length' => 100, 'default' => '', 'comment' => '名称'],
                'api_key' => ['type' => 'string', 'length' => 64, 'comment' => 'API Key'],
                'api_secret' => ['type' => 'string', 'length' => 128, 'comment' => 'API Secret Hash'],
                'notify_url' => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => '默认回调'],
                'ip_whitelist' => ['type' => 'json', 'nullable' => true, 'comment' => 'IP白名单'],
                'status' => ['type' => 'unsignedTinyInteger', 'default' => 1, 'comment' => '0禁用1启用'],
                'deposit_fee_rate' => ['type' => 'decimal', 'precision' => 10, 'scale' => 6, 'default' => '0', 'comment' => '入金费率'],
                'withdraw_fee_rate' => ['type' => 'decimal', 'precision' => 10, 'scale' => 6, 'default' => '0', 'comment' => '出金费率'],
                'withdraw_fee_min' => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => '0', 'comment' => '最低出金手续费'],
                'withdraw_fee_max' => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => '0', 'comment' => '最高出金手续费'],
                'auto_withdraw_max' => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => '0', 'comment' => '自动审核通过上限'],
                'remark' => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => '备注'],
                'login_email' => ['type' => 'string', 'length' => 128, 'nullable' => true, 'comment' => '商户登录邮箱'],
                'login_password' => ['type' => 'string', 'length' => 255, 'nullable' => true, 'comment' => '登录密码bcrypt'],
                'login_google_secret' => ['type' => 'string', 'length' => 64, 'nullable' => true, 'comment' => '商户2FA密钥'],
                'last_login_at' => ['type' => 'dateTime', 'nullable' => true, 'comment' => '最后登录时间'],
                'last_login_ip' => ['type' => 'string', 'length' => 45, 'nullable' => true, 'comment' => '最后登录IP'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['merchant_no'], 'unique' => true, 'name' => 'uk_merchant_no'],
                ['columns' => ['api_key'], 'unique' => true, 'name' => 'uk_merchant_api_key'],
                ['columns' => ['login_email'], 'unique' => true, 'name' => 'uk_merchant_login_email'],
            ],
        ];
    }
}
