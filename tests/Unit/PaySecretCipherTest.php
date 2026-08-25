<?php

declare(strict_types=1);

namespace tests\Unit;

use app\support\pay\PaySecretCipher;
use PHPUnit\Framework\TestCase;

class PaySecretCipherTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        $val = getenv('JWT_SECRET');
        $this->envBackup['JWT_SECRET'] = $val === false ? null : $val;
        putenv('JWT_SECRET=test-cipher-secret');
    }

    protected function tearDown(): void
    {
        if ($this->envBackup['JWT_SECRET'] === null) {
            putenv('JWT_SECRET');
        } else {
            putenv('JWT_SECRET=' . $this->envBackup['JWT_SECRET']);
        }
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $plain = bin2hex(random_bytes(16));
        $encrypted = PaySecretCipher::encrypt($plain);
        $this->assertNotSame($plain, $encrypted);
        $this->assertSame($plain, PaySecretCipher::decrypt($encrypted));
    }

    public function testDecryptInvalidReturnsEmpty(): void
    {
        $this->assertSame('', PaySecretCipher::decrypt('not-valid-base64!!!'));
    }
}
