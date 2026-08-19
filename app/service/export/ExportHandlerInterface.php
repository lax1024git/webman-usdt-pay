<?php

declare(strict_types=1);

namespace app\service\export;

interface ExportHandlerInterface
{
    /**
     * 规范化筛选条件（如 agent_id → ids）。
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function prepareFilters(array $filters): array;

    /**
     * 大表导出范围校验（ExportGuard 等）。
     *
     * @param array<string, mixed> $filters
     */
    public function validate(array $filters): void;

    /**
     * @param array<string, mixed> $filters
     */
    public function count(array $filters): int;

    /**
     * 按游标倒序取一批（id < cursorId；cursorId=0 表示从最大 id 开始）。
     *
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function fetchBatch(array $filters, int $cursorId, int $limit): array;

    /**
     * @return list<string>
     */
    public function headers(): array;

    /**
     * @param array<string, mixed> $row
     * @return list<scalar|null>
     */
    public function mapCsvRow(array $row): array;

    public function filenamePrefix(): string;
}
