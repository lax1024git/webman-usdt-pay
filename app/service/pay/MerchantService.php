<?php

declare(strict_types=1);

namespace app\service\pay;

use app\exception\BusinessException;
use app\model\pay\MerchantModel;
use app\support\ErrorCode;
use app\support\pay\OrderNoGenerator;
use app\support\pay\PaySecretCipher;

class MerchantService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? new LedgerService();
    }

    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = MerchantModel::query();
        if (!empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                    ->orWhere('merchant_no', 'like', "%{$kw}%")
                    ->orWhere('api_key', 'like', "%{$kw}%")
                    ->orWhere('login_email', 'like', "%{$kw}%");
            });
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        $total = $query->count();
        $items = $query->orderByDesc('id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return ['total' => $total, 'items' => $items];
    }

    public function show(int $id): MerchantModel
    {
        $merchant = MerchantModel::find($id);
        if (!$merchant) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '商户不存在');
        }

        return $merchant;
    }

    /**
     * @return array{merchant: MerchantModel, api_secret: string}
     */
    public function create(array $data): array
    {
        $plainSecret = bin2hex(random_bytes(16));
        $apiKey = 'pk_' . bin2hex(random_bytes(12));
        $loginEmail = trim((string) ($data['login_email'] ?? ''));
        $loginPassword = (string) ($data['login_password'] ?? '');

        if ($loginEmail !== '') {
            $this->assertLoginEmailUnique($loginEmail);
        }

        $payload = [
            'merchant_no' => OrderNoGenerator::merchantNo(),
            'name' => (string) ($data['name'] ?? ''),
            'api_key' => $apiKey,
            'api_secret' => PaySecretCipher::encrypt($plainSecret),
            'notify_url' => (string) ($data['notify_url'] ?? ''),
            'ip_whitelist' => $data['ip_whitelist'] ?? [],
            'status' => (int) ($data['status'] ?? 1),
            'deposit_fee_rate' => (string) ($data['deposit_fee_rate'] ?? '0'),
            'withdraw_fee_rate' => (string) ($data['withdraw_fee_rate'] ?? '0'),
            'withdraw_fee_min' => (string) ($data['withdraw_fee_min'] ?? '0'),
            'withdraw_fee_max' => (string) ($data['withdraw_fee_max'] ?? '0'),
            'auto_withdraw_max' => (string) ($data['auto_withdraw_max'] ?? '0'),
            'remark' => (string) ($data['remark'] ?? ''),
            'login_email' => $loginEmail !== '' ? $loginEmail : null,
        ];

        if ($loginPassword !== '') {
            if (strlen($loginPassword) < 6) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '登录密码长度不能少于6位');
            }
            $payload['login_password'] = password_hash($loginPassword, PASSWORD_BCRYPT);
        }

        $merchant = MerchantModel::create($payload);

        $this->ledgerService->getOrCreateAccount($merchant->id, 'USDT', 'TRC20');

        return ['merchant' => $merchant, 'api_secret' => $plainSecret];
    }

    public function update(int $id, array $data): MerchantModel
    {
        $merchant = $this->show($id);
        $allowed = [
            'name', 'notify_url', 'ip_whitelist', 'status',
            'deposit_fee_rate', 'withdraw_fee_rate', 'withdraw_fee_min', 'withdraw_fee_max',
            'auto_withdraw_max', 'remark', 'login_email',
        ];
        $update = array_intersect_key($data, array_flip($allowed));

        if (array_key_exists('login_email', $update)) {
            $email = trim((string) $update['login_email']);
            $update['login_email'] = $email !== '' ? $email : null;
            if ($update['login_email'] !== null) {
                $this->assertLoginEmailUnique($update['login_email'], $id);
            }
        }

        if (!empty($data['login_password'])) {
            $pwd = (string) $data['login_password'];
            if (strlen($pwd) < 6) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '登录密码长度不能少于6位');
            }
            $update['login_password'] = password_hash($pwd, PASSWORD_BCRYPT);
        }

        $merchant->update($update);

        return $merchant->fresh();
    }

    public function setLoginPassword(int $id, string $password): void
    {
        if (strlen($password) < 6) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '登录密码长度不能少于6位');
        }
        $merchant = $this->show($id);
        $merchant->update(['login_password' => password_hash($password, PASSWORD_BCRYPT)]);
    }

    private function assertLoginEmailUnique(string $email, ?int $excludeId = null): void
    {
        $query = MerchantModel::where('login_email', $email);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        if ($query->exists()) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '登录邮箱已被使用');
        }
    }

    /**
     * @return array{api_key: string, api_secret: string}
     */
    public function resetSecret(int $id): array
    {
        $merchant = $this->show($id);
        $plainSecret = bin2hex(random_bytes(16));
        $merchant->update(['api_secret' => PaySecretCipher::encrypt($plainSecret)]);

        return ['api_key' => $merchant->api_key, 'api_secret' => $plainSecret];
    }

    public function findByApiKey(string $apiKey): MerchantModel
    {
        $merchant = MerchantModel::where('api_key', $apiKey)->first();
        if (!$merchant || (int) $merchant->status !== 1) {
            throw new BusinessException(ErrorCode::PAY_MERCHANT_DISABLED);
        }

        return $merchant;
    }

    public function verifySecret(MerchantModel $merchant, string $plainSecret): bool
    {
        $stored = MerchantModel::where('id', $merchant->id)->value('api_secret');
        if (!is_string($stored) || $stored === '') {
            return false;
        }
        $decrypted = PaySecretCipher::decrypt($stored);

        return $decrypted !== '' && hash_equals($decrypted, $plainSecret);
    }

    public function getPlainSecret(int $merchantId): string
    {
        $stored = MerchantModel::where('id', $merchantId)->value('api_secret');

        return is_string($stored) ? PaySecretCipher::decrypt($stored) : '';
    }
}
