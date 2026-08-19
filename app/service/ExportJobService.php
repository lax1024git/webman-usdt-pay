<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\sys\ExportJobModel;
use app\queue\redis\CsvExportConsumer;
use app\service\export\ExportHandlerInterface;
use app\support\CsvHelper;
use app\support\ErrorCode;
use support\Redis;
use Webman\RedisQueue\Client;

class ExportJobService
{
    public const DEFAULT_BATCH_SIZE = 500;

    private const LOCK_TTL = 600;

    /** @var list<string> 业务导出 handler 已移除；后续新增时在此注册 */
    private const ALLOWED_TYPES = [];

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function createJob(string $exportType, array $filters = [], int $operatorId = 0): array
    {
        $exportType = trim($exportType);
        if (!in_array($exportType, self::ALLOWED_TYPES, true)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '不支持的导出类型');
        }

        $handler = $this->handler($exportType);
        $filters = $handler->prepareFilters($filters);
        if ($operatorId > 0) {
            $filters['__operator_id'] = $operatorId;
        }
        $handler->validate($filters);

        $total = min($handler->count($filters), CsvHelper::MAX_ROWS);

        $job = ExportJobModel::create([
            'export_type' => $exportType,
            'filters' => $filters,
            'operator_id' => $operatorId,
            'status' => ExportJobModel::STATUS_PENDING,
            'total' => $total,
            'processed' => 0,
            'batch_size' => self::DEFAULT_BATCH_SIZE,
            'cursor_id' => 0,
            'message' => $total === 0 ? '无数据，待生成空文件' : '已入队，等待处理',
        ]);

        Client::send(CsvExportConsumer::QUEUE_NAME, [
            'job_id' => (int) $job->id,
        ]);

        return $this->formatJob($job->fresh());
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{total:int,items:list<array<string,mixed>>,types:list<array{value:string,label:string}>}
     */
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = ExportJobModel::query();

        if (!empty($filters['export_type'])) {
            $query->where('export_type', (string) $filters['export_type']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }
        if (isset($filters['operator_id']) && $filters['operator_id'] !== '' && $filters['operator_id'] !== null) {
            $query->where('operator_id', (int) $filters['operator_id']);
        }
        if (!empty($filters['start_date'])) {
            $start = (string) $filters['start_date'];
            $query->where('created_at', '>=', str_contains($start, ':') ? $start : $start . ' 00:00:00');
        }
        if (!empty($filters['end_date'])) {
            $end = (string) $filters['end_date'];
            $query->where('created_at', '<=', str_contains($end, ':') ? $end : $end . ' 23:59:59');
        }

        $total = (int) $query->count();
        $rows = $query->orderByDesc('id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $operatorIds = $rows->pluck('operator_id')->map(static fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $operators = [];
        if ($operatorIds !== []) {
            $operators = \app\model\sys\AdminModel::query()
                ->whereIn('id', $operatorIds)
                ->get(['id', 'username', 'nickname'])
                ->keyBy('id');
        }

        $items = $rows->map(function (ExportJobModel $job) use ($operators) {
            $data = $this->formatJob($job);
            $op = $operators[(int) $job->operator_id] ?? null;
            $data['operator_id'] = (int) $job->operator_id;
            $data['operator_name'] = $op
                ? (string) ($op->nickname ?: $op->username)
                : ((int) $job->operator_id > 0 ? (string) $job->operator_id : '-');
            return $data;
        })->all();

        return [
            'total' => $total,
            'items' => $items,
            'types' => $this->typeOptions(),
        ];
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    public function typeOptions(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function showJob(int $jobId): array
    {
        $job = ExportJobModel::find($jobId);
        if (!$job) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '导出任务不存在');
        }

        return $this->formatJob($job);
    }

    public function deleteJob(int $jobId): void
    {
        $job = ExportJobModel::find($jobId);
        if (!$job) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '导出任务不存在');
        }

        $status = (string) $job->status;
        if (!in_array($status, [
            ExportJobModel::STATUS_SUCCESS,
            ExportJobModel::STATUS_FAILED,
        ], true)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '仅可删除已完成或失败的导出任务');
        }

        $this->cleanupJobFiles($job);
        $job->delete();
    }

    private function cleanupJobFiles(ExportJobModel $job): void
    {
        $tempPath = trim((string) ($job->file_path ?? ''));
        if ($tempPath !== '' && is_file($tempPath)) {
            @unlink($tempPath);
        }

        $key = trim((string) ($job->file_key ?? ''));
        if ($key === '') {
            return;
        }

        $local = public_path() . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, ltrim($key, '/'));
        if (is_file($local)) {
            @unlink($local);
        }
    }

    public function processBatch(int $jobId): void
    {
        $lockKey = "csv_export:job:{$jobId}";
        if (!Redis::setNx($lockKey, '1', self::LOCK_TTL)) {
            return;
        }

        try {
            $job = ExportJobModel::find($jobId);
            if (!$job) {
                return;
            }
            if (in_array((string) $job->status, [
                ExportJobModel::STATUS_SUCCESS,
                ExportJobModel::STATUS_FAILED,
            ], true)) {
                return;
            }

            $handler = $this->handler((string) $job->export_type);
            $filters = is_array($job->filters) ? $job->filters : [];
            $batchSize = max(1, (int) $job->batch_size ?: self::DEFAULT_BATCH_SIZE);
            $remainingQuota = max(0, min(CsvHelper::MAX_ROWS, (int) $job->total) - (int) $job->processed);
            if ($remainingQuota <= 0 && (int) $job->total > 0) {
                $this->finalizeJob($job, $handler);
                return;
            }

            if ((string) $job->status === ExportJobModel::STATUS_PENDING) {
                $job->status = ExportJobModel::STATUS_RUNNING;
                $job->started_at = date('Y-m-d H:i:s');
                $job->message = '处理中';
                $job->save();
            }

            $path = $this->ensureTempFile($job, $handler->headers());
            $limit = (int) $job->total > 0
                ? min($batchSize, max(1, $remainingQuota))
                : $batchSize;

            $rows = $handler->fetchBatch($filters, (int) $job->cursor_id, $limit);
            if ($rows === []) {
                $this->finalizeJob($job, $handler);
                return;
            }

            $fp = fopen($path, 'ab');
            if ($fp === false) {
                throw new BusinessException(ErrorCode::INTERNAL_ERROR, '临时文件打开失败');
            }
            try {
                foreach ($rows as $row) {
                    fputcsv($fp, $handler->mapCsvRow($row));
                }
            } finally {
                fclose($fp);
            }

            $last = $rows[array_key_last($rows)];
            $job->cursor_id = (int) ($last['__export_cursor'] ?? $last['id'] ?? $job->cursor_id);
            $job->processed = (int) $job->processed + count($rows);
            $job->file_path = $path;
            $job->message = "已处理 {$job->processed}/{$job->total}";
            $job->save();

            if ((int) $job->processed >= (int) $job->total || count($rows) < $limit) {
                $this->finalizeJob($job, $handler);
                return;
            }

            Client::send(CsvExportConsumer::QUEUE_NAME, [
                'job_id' => (int) $job->id,
            ]);
        } catch (\Throwable $e) {
            $job = ExportJobModel::find($jobId);
            if ($job) {
                $this->failJob($job, $e->getMessage());
            }
            throw $e;
        } finally {
            Redis::del($lockKey);
        }
    }

    private function handler(string $exportType): ExportHandlerInterface
    {
        throw new BusinessException(ErrorCode::VALIDATION_FAILED, '不支持的导出类型');
    }

    /**
     * @param list<string> $headers
     */
    private function ensureTempFile(ExportJobModel $job, array $headers): string
    {
        $existing = trim((string) ($job->file_path ?? ''));
        if ($existing !== '' && is_file($existing)) {
            return $existing;
        }

        $dir = runtime_path('exports');
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new BusinessException(ErrorCode::INTERNAL_ERROR, '导出临时目录创建失败');
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'job_' . $job->id . '.csv';
        $fp = fopen($path, 'wb');
        if ($fp === false) {
            throw new BusinessException(ErrorCode::INTERNAL_ERROR, '临时文件创建失败');
        }
        try {
            fwrite($fp, "\xEF\xBB\xBF");
            fputcsv($fp, $headers);
        } finally {
            fclose($fp);
        }

        $job->file_path = $path;
        $job->save();

        return $path;
    }

    private function finalizeJob(ExportJobModel $job, ExportHandlerInterface $handler): void
    {
        $path = $this->ensureTempFile($job, $handler->headers());
        $content = file_get_contents($path);
        if ($content === false) {
            throw new BusinessException(ErrorCode::INTERNAL_ERROR, '读取导出文件失败');
        }

        $key = sprintf(
            'exports/%s/%s/%s_%s.csv',
            $handler->filenamePrefix(),
            date('Y/m'),
            $handler->filenamePrefix(),
            date('YmdHis') . '_' . $job->id
        );

        $uploaded = (new S3Service())->putContents($key, $content, 'text/csv; charset=UTF-8');

        if (is_file($path)) {
            @unlink($path);
        }

        $job->status = ExportJobModel::STATUS_SUCCESS;
        $job->file_key = (string) ($uploaded['key'] ?? $key);
        $job->file_url = (string) ($uploaded['url'] ?? '');
        $job->file_path = '';
        $job->finished_at = date('Y-m-d H:i:s');
        $job->message = '导出完成';
        $job->save();
    }

    private function failJob(ExportJobModel $job, string $message): void
    {
        $path = trim((string) ($job->file_path ?? ''));
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }

        $job->status = ExportJobModel::STATUS_FAILED;
        $job->finished_at = date('Y-m-d H:i:s');
        $job->file_path = '';
        $job->message = mb_substr($message, 0, 500);
        $job->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatJob(?ExportJobModel $job): array
    {
        if (!$job) {
            return [];
        }

        $total = max(0, (int) $job->total);
        $processed = max(0, (int) $job->processed);
        $percent = $total > 0
            ? min(100, (int) floor($processed * 100 / $total))
            : (in_array((string) $job->status, [ExportJobModel::STATUS_SUCCESS], true) ? 100 : 0);

        $fileUrl = trim((string) ($job->file_url ?? ''));
        if ($fileUrl === '') {
            $fileUrl = trim((string) ($job->file_key ?? ''));
        }
        if ($fileUrl !== '') {
            try {
                $fileUrl = (new S3Service())->resolvePublicUrl($fileUrl);
            } catch (\Throwable) {
                // 保持原值，由前端再补全域名
            }
        }

        return [
            'id' => (int) $job->id,
            'export_type' => (string) $job->export_type,
            'export_type_label' => $this->typeLabel((string) $job->export_type),
            'status' => (string) $job->status,
            'total' => $total,
            'processed' => $processed,
            'percent' => $percent,
            'batch_size' => (int) $job->batch_size,
            'file_url' => $fileUrl,
            'message' => (string) ($job->message ?? ''),
            'operator_id' => (int) $job->operator_id,
            'started_at' => $job->started_at,
            'finished_at' => $job->finished_at,
            'created_at' => $job->created_at,
        ];
    }

    private function typeLabel(string $type): string
    {
        foreach ($this->typeOptions() as $item) {
            if ($item['value'] === $type) {
                return $item['label'];
            }
        }

        return $type;
    }
}
