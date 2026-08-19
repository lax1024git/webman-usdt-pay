<?php

declare(strict_types=1);

/**
 * 加密 PHP 源码为 financeos_loader 可识别格式。
 *
 * 用法:
 *   php scripts/encrypt_php.php --key=YOUR_SECRET --dir=app
 *   php scripts/encrypt_php.php --key=YOUR_SECRET --dir=app --dir=support --dir=config --out=build/stage
 *
 * 格式: FOSENC01 + iv(16) + aes-256-cbc(ciphertext)
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/encrypt_php.php --key=SECRET --dir=app [--dir=support] [--out=build/stage] [--in-place]\n");
    exit(1);
}

$key = '';
$dirs = [];
$outRoot = '';
$inPlace = false;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--key=')) {
        $key = substr($arg, 6);
    } elseif (str_starts_with($arg, '--dir=')) {
        $dirs[] = substr($arg, 6);
    } elseif (str_starts_with($arg, '--out=')) {
        $outRoot = substr($arg, 6);
    } elseif ($arg === '--in-place') {
        $inPlace = true;
    }
}

if ($key === '' || $dirs === []) {
    fwrite(STDERR, "Missing --key or --dir\n");
    exit(1);
}

$root = dirname(__DIR__);
$magic = 'FOSENC01';
$aesKey = hash('sha256', $key, true);
$count = 0;

foreach ($dirs as $dir) {
    $srcBase = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dir);
    if (!is_dir($srcBase)) {
        fwrite(STDERR, "Skip missing dir: {$dir}\n");
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcBase, FilesystemIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $srcPath = $file->getPathname();
        $relative = substr($srcPath, strlen($root) + 1);
        $destPath = $inPlace
            ? $srcPath
            : ($root . DIRECTORY_SEPARATOR . ($outRoot !== '' ? $outRoot . DIRECTORY_SEPARATOR : '') . $relative);

        $raw = file_get_contents($srcPath);
        if ($raw === false) {
            fwrite(STDERR, "Read fail: {$relative}\n");
            continue;
        }
        if (str_starts_with($raw, $magic)) {
            echo "Already encrypted: {$relative}\n";
            if (!$inPlace && $destPath !== $srcPath) {
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0777, true);
                }
                copy($srcPath, $destPath);
            }
            continue;
        }

        $iv = random_bytes(16);
        $cipher = openssl_encrypt($raw, 'AES-256-CBC', $aesKey, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            fwrite(STDERR, "Encrypt fail: {$relative}\n");
            continue;
        }
        $blob = $magic . $iv . $cipher;

        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0777, true);
        }
        if (file_put_contents($destPath, $blob) === false) {
            fwrite(STDERR, "Write fail: {$relative}\n");
            continue;
        }
        $count++;
        echo "Encrypted: {$relative}\n";
    }
}

echo "Done. encrypted={$count}\n";
