<?php

declare(strict_types=1);

namespace tests\Api;

use tests\ApiTestCase;

class SettingApiTest extends ApiTestCase
{
    public function testSystemConfigBundle(): void
    {
        $response = $this->get('/admin/system-config');
        $this->assertEquals(200, $response['code']);
        $this->assertIsArray($response['data']);
    }

    public function testSaveSystemConfigBundle(): void
    {
        $bundle = $this->get('/admin/system-config');
        $this->assertEquals(200, $bundle['code']);

        $update = $this->put('/admin/system-config', $bundle['data']);
        $this->assertEquals(200, $update['code']);
    }
}
