<?php

declare(strict_types=1);

namespace tests\Unit;

use app\support\Decimal;
use PHPUnit\Framework\TestCase;

class DecimalTest extends TestCase
{
    public function testAddAndFormat(): void
    {
        $this->assertSame('10.500000', Decimal::format(Decimal::add('10.1', '0.4')));
    }

    public function testCmp(): void
    {
        $this->assertSame(0, Decimal::cmp('100.000000', '100'));
        $this->assertSame(1, Decimal::cmp('100.000001', '100'));
        $this->assertSame(-1, Decimal::cmp('99.999999', '100'));
    }
}
