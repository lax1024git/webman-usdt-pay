<?php

declare(strict_types=1);

/**
 * 服务器自检：financeos_loader 是否可用
 *
 *   php scripts/check_loader.php
 *   php scripts/check_loader.php --key=lax1024
 */

$keyArg = '';
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--key=')) {
        $keyArg = substr($arg, 6);
    }
}

echo "PHP: " . PHP_VERSION . ' (' . PHP_SAPI . ")\n";
echo "Loaded ini: " . (php_ini_loaded_file() ?: '(none)') . "\n";

if (!extension_loaded('financeos_loader')) {
    echo "financeos_loader: NOT LOADED\n";
    echo "Fix: compile ext/financeos_loader, add to php.ini:\n";
    echo "  extension=financeos_loader.so\n";
    echo "  financeos_loader.key=YOUR_SECRET\n";
    exit(1);
}

echo "financeos_loader: loaded\n";

$iniKey = ini_get('financeos_loader.key');
echo 'financeos_loader.key (ini): ' . ($iniKey !== false && $iniKey !== '' ? '(set)' : '(empty)') . "\n";

if ($keyArg !== '' && $iniKey !== false && $iniKey !== '' && $iniKey !== $keyArg) {
    echo "WARN: --key= 与 php.ini 中 financeos_loader.key 不一致\n";
}

if ($iniKey === false || $iniKey === '') {
    echo "ERROR: financeos_loader.key 未配置\n";
    exit(1);
}

$testKey = $keyArg !== '' ? $keyArg : (string) $iniKey;
$plain = "<?php return ['ok'=>true];\n";
$magic = 'FOSENC01';
$aesKey = hash('sha256', $testKey, true);
$iv = random_bytes(16);
$cipher = openssl_encrypt($plain, 'AES-256-CBC', $aesKey, OPENSSL_RAW_DATA, $iv);
if ($cipher === false) {
    echo "encrypt self-test failed\n";
    exit(1);
}
$blob = $magic . $iv . $cipher;
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fos_loader_check_' . getmypid() . '.php';
file_put_contents($tmp, $blob);

try {
    /** @var array{ok:bool} $result */
    $result = include $tmp;
    if (!is_array($result) || ($result['ok'] ?? false) !== true) {
        echo "decrypt compile self-test: FAIL\n";
        exit(1);
    }
    echo "decrypt compile self-test: OK\n";
} catch (Throwable $e) {
    echo "decrypt compile self-test: FAIL - " . $e->getMessage() . "\n";
    exit(1);
} finally {
    @unlink($tmp);
}

echo "Ready for encrypted PHAR.\n";
