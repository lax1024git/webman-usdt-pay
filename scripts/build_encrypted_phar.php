#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * 发布构建：复制项目 → 加密 app/support/config → 打 PHAR
 *
 *   php scripts/build_encrypted_phar.php --key=YOUR_SECRET
 *   # 或
 *   set ENCRYPT_KEY=YOUR_SECRET
 *   php scripts/build_encrypted_phar.php
 *
 * 产物: build/webman.phar
 * 服务器需安装 financeos_loader 扩展，php.ini:
 *   extension=financeos_loader
 *   financeos_loader.key=YOUR_SECRET
 */

$root = dirname(__DIR__);
chdir($root);

$key = getenv('ENCRYPT_KEY') ?: '';
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--key=')) {
        $key = substr($arg, 6);
    }
}
if ($key === '') {
    fwrite(STDERR, "请设置 ENCRYPT_KEY 或传入 --key=SECRET\n");
    exit(1);
}

$stage = $root . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'stage';
$pharOut = $root . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'webman.phar';

function rimraf(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        unlink($path);
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($path);
}

function copyTree(string $src, string $dst, callable $skip): void
{
    $src = rtrim($src, '/\\');
    $dst = rtrim($dst, '/\\');
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $rel = substr($item->getPathname(), strlen($src) + 1);
        $relNorm = str_replace('\\', '/', $rel);
        if ($skip($relNorm)) {
            continue;
        }
        $target = $dst . DIRECTORY_SEPARATOR . $rel;
        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0777, true);
            }
        } else {
            $dir = dirname($target);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            copy($item->getPathname(), $target);
        }
    }
}

function run(array $cmd, ?string $cwd = null): int
{
    $cmdLine = implode(' ', array_map('escapeshellarg', $cmd));
    echo "\$ {$cmdLine}\n";
    $desc = [0 => STDIN, 1 => STDOUT, 2 => STDERR];
    $proc = proc_open($cmdLine, $desc, $pipes, $cwd);
    if (!is_resource($proc)) {
        return 1;
    }

    return proc_close($proc);
}

echo "==> 清理 stage\n";
rimraf($stage);
mkdir($stage, 0777, true);

$skip = static function (string $rel): bool {
    $rel = ltrim($rel, '/');
    $prefixes = [
        '.git/', '.github/', '.idea/', '.vscode/', '.cursor/',
        'runtime/', 'build/', 'frontend/', 'web/', 'docs/', 'tests/',
        'deploy/', 'ext/', 'scripts/', 'node_modules/',
    ];
    foreach ($prefixes as $p) {
        if ($rel === rtrim($p, '/') || str_starts_with($rel, $p)) {
            return true;
        }
    }
    $files = ['.env', '.env.example', '.env.production.example', 'windows.php', 'phpunit.xml'];

    return in_array($rel, $files, true);
};

echo "==> 复制项目到 build/stage\n";
copyTree($root, $stage, $skip);

// 先读明文配置（加密后无法 require config）
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $root);
}
$consoleApp = require $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'plugin'
    . DIRECTORY_SEPARATOR . 'webman' . DIRECTORY_SEPARATOR . 'console' . DIRECTORY_SEPARATOR . 'app.php';
$excludePattern = (string) ($consoleApp['exclude_pattern'] ?? '');
$excludeFiles = (array) ($consoleApp['exclude_files'] ?? []);
$signatureAlgorithm = (int) ($consoleApp['signature_algorithm'] ?? Phar::SHA256);

echo "==> 加密 app / support / config\n";
$encCode = run([
    PHP_BINARY,
    $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'encrypt_php.php',
    '--key=' . $key,
    '--in-place',
    '--dir=build/stage/app',
    '--dir=build/stage/support',
    '--dir=build/stage/config',
], $root);
if ($encCode !== 0) {
    fwrite(STDERR, "encrypt failed\n");
    exit($encCode);
}

// 加密后不能再跑 webman build:phar（会 bootstrap 密文）。直接按 console 配置打 PHAR。
echo "==> 打 PHAR（不启动 webman CLI）\n";
if (!class_exists(Phar::class, false)) {
    fwrite(STDERR, "phar extension required\n");
    exit(1);
}
if (ini_get('phar.readonly')) {
    fwrite(STDERR, "phar.readonly=On，请用: php -d phar.readonly=0 scripts/build_encrypted_phar.php --key=...\n");
    exit(1);
}

$stageBuildDir = $stage . DIRECTORY_SEPARATOR . 'build';
if (!is_dir($stageBuildDir)) {
    mkdir($stageBuildDir, 0777, true);
}
$built = $stageBuildDir . DIRECTORY_SEPARATOR . 'webman.phar';
if (is_file($built)) {
    unlink($built);
}

$phar = new Phar($built, 0, 'webman');
$phar->startBuffering();
$phar->setSignatureAlgorithm($signatureAlgorithm);
if ($excludePattern !== '') {
    $phar->buildFromDirectory($stage, $excludePattern);
} else {
    $phar->buildFromDirectory($stage);
}

$excludeCommandFiles = [
    'AppPluginCreateCommand.php',
    'BuildBinCommand.php',
    'BuildPharCommand.php',
    'MakeBootstrapCommand.php',
    'MakeCommandCommand.php',
    'MakeControllerCommand.php',
    'MakeMiddlewareCommand.php',
    'MakeModelCommand.php',
    'PluginCreateCommand.php',
    'PluginDisableCommand.php',
    'PluginEnableCommand.php',
    'PluginExportCommand.php',
    'PluginInstallCommand.php',
    'PluginUninstallCommand.php',
];
foreach ($excludeCommandFiles as $cmdFile) {
    $excludeFiles[] = 'vendor/webman/console/src/Commands/' . $cmdFile;
}
foreach (array_unique($excludeFiles) as $file) {
    $file = str_replace('\\', '/', (string) $file);
    if ($phar->offsetExists($file)) {
        $phar->delete($file);
    }
}

$phar->setStub("#!/usr/bin/env php
<?php
define('IN_PHAR', true);
Phar::mapPhar('webman');
require 'phar://webman/webman';
__HALT_COMPILER();
");
$phar->stopBuffering();
unset($phar);

if (!is_file($built)) {
    fwrite(STDERR, "phar not found: {$built}\n");
    exit(1);
}
if (!is_dir(dirname($pharOut))) {
    mkdir(dirname($pharOut), 0777, true);
}
copy($built, $pharOut);

$keyFile = $root . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'financeos_loader.ini.example';
file_put_contents($keyFile, "extension=financeos_loader\nfinanceos_loader.key={$key}\n");

echo "==> OK: {$pharOut}\n";
echo "==> INI 示例: {$keyFile}\n";
echo "服务器: 安装 ext/financeos_loader 后:\n";
echo "  php webman.phar start -d\n";
