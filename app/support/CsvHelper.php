<?php

declare(strict_types=1);

namespace app\support;

use support\Response;

final class CsvHelper
{
    /** 单次导出硬上限，防止内存/超时 */
    public const MAX_ROWS = 20000;

    /** 默认导出条数 */
    public const DEFAULT_LIMIT = 10000;

    public static function normalizeLimit(?int $limit, int $default = self::DEFAULT_LIMIT): int
    {
        $n = $limit ?? $default;

        return max(1, min($n, self::MAX_ROWS));
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, scalar|null>> $rows
     */
    public static function build(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $content;
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, scalar|null>> $rows
     */
    public static function download(string $filename, array $headers, array $rows): Response
    {
        @set_time_limit(180);
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '256M');
        }

        if (count($rows) > self::MAX_ROWS) {
            $rows = array_slice($rows, 0, self::MAX_ROWS);
        }

        $content = "\xEF\xBB\xBF" . self::build($headers, $rows);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'X-Export-Row-Count' => (string) count($rows),
            'X-Export-Max-Rows' => (string) self::MAX_ROWS,
        ]);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function parse(string $content): array
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return [];
        }

        fwrite($handle, $content);
        rewind($handle);

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            return [];
        }

        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($headers as $index => $header) {
                $row[(string) $header] = (string) ($data[$index] ?? '');
            }
            if (array_filter($row) !== []) {
                $rows[] = $row;
            }
        }

        fclose($handle);
        return $rows;
    }
}
