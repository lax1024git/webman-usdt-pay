<?php

declare(strict_types=1);

namespace tests\Api;

use app\model\sys\LangModel;
use PHPUnit\Framework\TestCase;

class LangModelSchemaTest extends TestCase
{
    public function testSwitchEnabledIsDeclaredInLangSchemaAndFillable(): void
    {
        $schema = LangModel::tableSchema();

        $this->assertArrayHasKey('switch_enabled', $schema['columns']);
        $this->assertContains('switch_enabled', (new LangModel())->getFillable());
    }
}
