<?php

declare(strict_types=1);

namespace database\seeders;

use app\model\pay\PlatformModel;
use app\model\pay\MerchantModel;
use app\service\pay\LedgerService;
use app\support\pay\PaySecretCipher;

class PaySeeder
{
    public static function run(): void
    {
        if (!PlatformModel::where('code', 'TRC20_USDT')->exists()) {
            echo "  -> Seeding pay platform TRC20_USDT...\n";
            PlatformModel::create([
                'code' => 'TRC20_USDT',
                'name' => 'TRC20-USDT',
                'chain' => 'TRC20',
                'currency' => 'USDT',
                'contract_address' => env('TRON_USDT_CONTRACT', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'),
                'decimals' => 6,
                'min_deposit_amount' => '10.000000',
                'max_deposit_amount' => '100000.000000',
                'min_withdraw_amount' => '10.000000',
                'max_withdraw_amount' => '50000.000000',
                'deposit_confirmations' => (int) env('TRON_DEPOSIT_CONFIRMATIONS', 19),
                'withdraw_confirmations' => (int) env('TRON_WITHDRAW_CONFIRMATIONS', 19),
                'deposit_expire_seconds' => 1800,
                'amount_match_mode' => PlatformModel::AMOUNT_MATCH_EXACT,
                'status' => 1,
                'config' => [
                    'api_url' => env('TRON_API_URL', 'https://api.trongrid.io'),
                ],
                'sort' => 1,
            ]);
        }

        if (MerchantModel::where('login_email', 'demo@merchant.local')->exists()) {
            return;
        }

        echo "  -> Seeding demo merchant (portal: demo@merchant.local / merchant123)...\n";
        $plainSecret = bin2hex(random_bytes(16));
        $merchant = MerchantModel::create([
            'merchant_no' => 'M' . str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'name' => 'Demo Merchant',
            'api_key' => 'pk_demo_' . bin2hex(random_bytes(8)),
            'api_secret' => PaySecretCipher::encrypt($plainSecret),
            'notify_url' => '',
            'ip_whitelist' => [],
            'status' => 1,
            'deposit_fee_rate' => '0.01',
            'withdraw_fee_rate' => '0.005',
            'auto_withdraw_max' => '1000',
            'login_email' => 'demo@merchant.local',
            'login_password' => password_hash('merchant123', PASSWORD_BCRYPT),
        ]);
        (new LedgerService())->getOrCreateAccount((int) $merchant->id, 'USDT', 'TRC20');
        echo "     Demo API Secret (save once): {$plainSecret}\n";
    }
}
