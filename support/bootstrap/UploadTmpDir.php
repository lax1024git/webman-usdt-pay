<?php

declare(strict_types=1);

namespace support\bootstrap;

use Workerman\Protocols\Http;
use Webman\Bootstrap;

/**
 * 修正 Windows 下错误的 upload_tmp_dir（如 php.ini 写成 C:/Temp\n），
 * 并确保 Workerman 上传临时目录可写。
 */
class UploadTmpDir implements Bootstrap
{
    public static function start($worker): void
    {
        $candidates = [
            runtime_path('tmp'),
            'C:/Temp',
            sys_get_temp_dir(),
        ];

        $dir = '';
        foreach ($candidates as $candidate) {
            $candidate = rtrim(str_replace(["\r", "\n"], '', (string) $candidate), "\\/");
            if ($candidate === '') {
                continue;
            }
            if (!is_dir($candidate) && !@mkdir($candidate, 0777, true) && !is_dir($candidate)) {
                continue;
            }
            if (!is_writable($candidate)) {
                continue;
            }
            $dir = $candidate;
            break;
        }

        if ($dir === '') {
            return;
        }

        @ini_set('upload_tmp_dir', $dir);
        Http::uploadTmpDir($dir);
    }
}
