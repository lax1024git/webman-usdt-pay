<?php

declare(strict_types=1);

namespace app\support;

use app\exception\BusinessException;

/**
 * 导出范围校验：避免大表无筛选全量导出拖垮进程。
 */
final class ExportGuard
{
    /**
     * 要求至少提供用户维度或时间范围之一。
     *
     * @param array<string, mixed> $filters
     * @param list<string> $userKeys
     * @param list<string> $dateKeys
     */
    public static function requireUserOrDate(
        array $filters,
        array $userKeys = ['user_id', 'username', 'keyword', 'ids', 'order_no'],
        array $dateKeys = ['start_date', 'end_date', 'start_time', 'end_time', 'startTime', 'endTime'],
        string $message = '导出数据量过大，请先筛选用户或时间范围'
    ): void {
        foreach ($userKeys as $key) {
            if (!array_key_exists($key, $filters)) {
                continue;
            }
            $value = $filters[$key];
            if ($value === null || $value === '') {
                continue;
            }
            if (is_array($value) && $value === []) {
                continue;
            }
            return;
        }

        foreach ($dateKeys as $key) {
            if (!empty($filters[$key])) {
                return;
            }
        }

        throw new BusinessException(ErrorCode::VALIDATION_FAILED, $message);
    }
}
