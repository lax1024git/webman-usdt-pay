<?php

declare(strict_types=1);

namespace app\service\pay;

use app\exception\BusinessException;
use app\model\pay\MerchantModel;
use app\support\ErrorCode;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class MerchantPortalService
{
    private const ISS = 'merchant_portal';
    private const REFRESH_PREFIX = 'merchant_refresh:';

    public function login(string $email, string $password, string $ip = ''): array
    {
        if ($email === '' || $password === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '邮箱和密码不能为空');
        }

        $merchant = MerchantModel::where('login_email', $email)->first();
        if (!$merchant || !is_string($merchant->login_password) || $merchant->login_password === '') {
            throw new BusinessException(ErrorCode::USERNAME_OR_PASSWORD_ERROR, '邮箱或密码错误');
        }

        if ((int) $merchant->status !== 1) {
            throw new BusinessException(ErrorCode::PAY_MERCHANT_DISABLED);
        }

        if (!password_verify($password, $merchant->login_password)) {
            throw new BusinessException(ErrorCode::USERNAME_OR_PASSWORD_ERROR, '邮箱或密码错误');
        }

        $merchant->update([
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $ip,
        ]);

        return $this->issueTokens($merchant);
    }

    public function refresh(string $refreshToken): array
    {
        $merchantId = $this->getRefreshTokenOwner($refreshToken);
        if (!$merchantId) {
            throw new BusinessException(ErrorCode::REFRESH_TOKEN_INVALID, '登录已过期');
        }

        $merchant = MerchantModel::find($merchantId);
        if (!$merchant || (int) $merchant->status !== 1) {
            throw new BusinessException(ErrorCode::REFRESH_TOKEN_INVALID);
        }

        $this->deleteRefreshToken($refreshToken);

        return $this->issueTokens($merchant);
    }

    public function logout(string $refreshToken): void
    {
        $this->deleteRefreshToken($refreshToken);
    }

    public function changePassword(MerchantModel $merchant, string $oldPassword, string $newPassword): void
    {
        if ($newPassword === '' || strlen($newPassword) < 6) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '新密码长度不能少于6位');
        }

        if (!is_string($merchant->login_password) || !password_verify($oldPassword, $merchant->login_password)) {
            throw new BusinessException(ErrorCode::USERNAME_OR_PASSWORD_ERROR, '原密码错误');
        }

        $merchant->update(['login_password' => password_hash($newPassword, PASSWORD_BCRYPT)]);
    }

    public function updateSettings(MerchantModel $merchant, array $data): MerchantModel
    {
        $allowed = ['notify_url', 'ip_whitelist'];
        $update = array_intersect_key($data, array_flip($allowed));
        if (isset($update['ip_whitelist']) && is_string($update['ip_whitelist'])) {
            $update['ip_whitelist'] = array_values(array_filter(
                array_map('trim', explode(',', $update['ip_whitelist']))
            ));
        }
        $merchant->update($update);

        return $merchant->fresh();
    }

    /**
     * @return array{api_key: string, api_secret: string}
     */
    public function resetSecret(MerchantModel $merchant, string $loginPassword): array
    {
        if ($loginPassword === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请输入登录密码');
        }
        if (!is_string($merchant->login_password) || !password_verify($loginPassword, $merchant->login_password)) {
            throw new BusinessException(ErrorCode::USERNAME_OR_PASSWORD_ERROR, '登录密码错误');
        }

        return (new MerchantService())->resetSecret((int) $merchant->id);
    }

    public function decodeAccessToken(string $token): ?object
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret(), 'HS256'));
            if (($decoded->iss ?? '') !== self::ISS) {
                return null;
            }
            return $decoded;
        } catch (\Throwable) {
            return null;
        }
    }

    private function issueTokens(MerchantModel $merchant): array
    {
        $expiresIn = (int) env('JWT_ACCESS_TTL', 7200);
        $exp = time() + $expiresIn;

        $accessToken = JWT::encode([
            'iss' => self::ISS,
            'sub' => $merchant->id,
            'merchant_id' => $merchant->id,
            'iat' => time(),
            'exp' => $exp,
        ], $this->jwtSecret(), 'HS256');

        $refreshToken = bin2hex(random_bytes(32));
        $refreshTtl = (int) env('JWT_REFRESH_TTL', 604800);
        $redis = \support\Redis::connection();
        $redis->setex(self::REFRESH_PREFIX . $refreshToken, $refreshTtl, (string) $merchant->id);

        return [
            'token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $expiresIn,
            'merchant' => [
                'id' => $merchant->id,
                'merchant_no' => $merchant->merchant_no,
                'name' => $merchant->name,
            ],
        ];
    }

    private function getRefreshTokenOwner(string $refreshToken): ?int
    {
        if ($refreshToken === '') {
            return null;
        }
        $val = \support\Redis::connection()->get(self::REFRESH_PREFIX . $refreshToken);
        return $val ? (int) $val : null;
    }

    private function deleteRefreshToken(string $refreshToken): void
    {
        if ($refreshToken !== '') {
            \support\Redis::connection()->del(self::REFRESH_PREFIX . $refreshToken);
        }
    }

    private function jwtSecret(): string
    {
        return (string) env('JWT_SECRET', 'your-secret-key');
    }
}
