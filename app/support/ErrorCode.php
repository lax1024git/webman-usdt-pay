<?php

declare(strict_types=1);

namespace app\support;

final class ErrorCode
{
    public const OK = 200;

    public const UNAUTHORIZED = 40101;
    public const TOKEN_INVALID = 40102;
    public const REFRESH_TOKEN_INVALID = 40103;

    public const FORBIDDEN = 40301;
    public const IP_NOT_ALLOWED = 40302;
    public const NOT_FOUND = 40401;

    public const VALIDATION_FAILED = 42201;
    public const USERNAME_OR_PASSWORD_ERROR = 42202;
    public const LOGIN_LOCKED = 42207;
    public const CAPTCHA_REQUIRED = 42208;
    public const CAPTCHA_INVALID = 42209;
    public const GOOGLE_AUTH_REQUIRED = 42210;
    public const GOOGLE_AUTH_INVALID = 42211;
    public const USERNAME_EXISTS = 42203;
    public const ROLE_SLUG_EXISTS = 42204;
    public const CANNOT_DELETE_SUPER_ADMIN = 42205;
    public const CANNOT_DELETE_LAST_SUPER_ADMIN = 42206;

    public const INTERNAL_ERROR = 50000;

    public const PAY_MERCHANT_DISABLED = 43001;
    public const PAY_SIGNATURE_INVALID = 43002;
    public const PAY_IP_NOT_ALLOWED = 43003;
    public const PAY_PLATFORM_UNAVAILABLE = 43004;
    public const PAY_AMOUNT_TOO_LOW = 43005;
    public const PAY_AMOUNT_TOO_HIGH = 43006;
    public const PAY_INSUFFICIENT_BALANCE = 43007;
    public const PAY_ORDER_NOT_FOUND = 43008;
    public const PAY_ORDER_STATUS_INVALID = 43009;
    public const PAY_ADDRESS_INVALID = 43010;
    public const PAY_ADDRESS_BLACKLISTED = 43011;
    public const PAY_BROADCAST_FAILED = 43013;
    public const PAY_ORDER_EXPIRED = 43015;

  /** 会员端 Token 过期（兼容参考项目前端 refresh 逻辑） */
    public const MEMBER_TOKEN_EXPIRED = 10001;

    /**
     * @return array<int, string>
     */
    public static function messages(): array
    {
        return [
            self::OK => 'success',
            self::UNAUTHORIZED => '未登录 / Token 缺失',
            self::TOKEN_INVALID => 'Token 无效或已过期',
            self::REFRESH_TOKEN_INVALID => 'refresh_token 无效',
            self::FORBIDDEN => '无权限访问',
            self::IP_NOT_ALLOWED => 'IP 不在白名单',
            self::NOT_FOUND => '资源不存在',
            self::VALIDATION_FAILED => '参数校验失败',
            self::USERNAME_OR_PASSWORD_ERROR => '用户名或密码错误',
            self::LOGIN_LOCKED => '登录失败次数过多，账号已临时锁定',
            self::CAPTCHA_REQUIRED => '请输入验证码',
            self::CAPTCHA_INVALID => '验证码错误或已过期',
            self::GOOGLE_AUTH_REQUIRED => '请输入谷歌验证码',
            self::GOOGLE_AUTH_INVALID => '谷歌验证码错误',
            self::USERNAME_EXISTS => '用户名已存在',
            self::ROLE_SLUG_EXISTS => '角色标识已存在',
            self::CANNOT_DELETE_SUPER_ADMIN => '不能删除超级管理员',
            self::CANNOT_DELETE_LAST_SUPER_ADMIN => '不能删除最后一个超级管理员',
            self::INTERNAL_ERROR => '服务器内部错误',
            self::PAY_MERCHANT_DISABLED => '商户不存在或已禁用',
            self::PAY_SIGNATURE_INVALID => 'API 签名无效',
            self::PAY_IP_NOT_ALLOWED => 'IP 不在白名单',
            self::PAY_PLATFORM_UNAVAILABLE => '支付通道不可用',
            self::PAY_AMOUNT_TOO_LOW => '金额低于最小限额',
            self::PAY_AMOUNT_TOO_HIGH => '金额超过最大限额',
            self::PAY_INSUFFICIENT_BALANCE => '商户余额不足',
            self::PAY_ORDER_NOT_FOUND => '订单不存在',
            self::PAY_ORDER_STATUS_INVALID => '订单状态不允许此操作',
            self::PAY_ADDRESS_INVALID => '出金地址格式无效',
            self::PAY_ADDRESS_BLACKLISTED => '出金地址在黑名单',
            self::PAY_BROADCAST_FAILED => '链上广播失败',
            self::PAY_ORDER_EXPIRED => '订单已过期',
            self::MEMBER_TOKEN_EXPIRED => 'Token 无效或已过期',
        ];
    }

    public static function message(int $code, ?string $fallback = null): string
    {
        if ($fallback !== null && $fallback !== '') {
            return $fallback;
        }

        return self::messages()[$code] ?? 'error';
    }
}

