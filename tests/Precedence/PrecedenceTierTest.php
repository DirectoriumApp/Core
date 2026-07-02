<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Precedence;

use Introibo\Core\Precedence\PrecedenceTier;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PrecedenceTierTest extends TestCase
{
    public function testLowerOrdinalOutranksHigher(): void
    {
        $apex = PrecedenceTier::of(1);
        $lower = PrecedenceTier::of(5);

        self::assertTrue($apex->isHigherThan($lower));
        self::assertFalse($lower->isHigherThan($apex));
        self::assertTrue($lower->isLowerThan($apex));
    }

    public function testSubOrderBreaksTiesWithinAnOrdinal(): void
    {
        $lord = PrecedenceTier::of(5, 0);
        $saint = PrecedenceTier::of(5, 1);

        self::assertTrue($lord->isHigherThan($saint));
        self::assertFalse($saint->isHigherThan($lord));
    }

    public function testCompareToOrdersLexicographically(): void
    {
        self::assertSame(-1, PrecedenceTier::of(2)->compareTo(PrecedenceTier::of(3)));
        self::assertSame(1, PrecedenceTier::of(3)->compareTo(PrecedenceTier::of(2)));
        self::assertSame(0, PrecedenceTier::of(4, 2)->compareTo(PrecedenceTier::of(4, 2)));
    }

    public function testEquals(): void
    {
        self::assertTrue(PrecedenceTier::of(6, 1)->equals(PrecedenceTier::of(6, 1)));
        self::assertFalse(PrecedenceTier::of(6, 1)->equals(PrecedenceTier::of(6, 2)));
        self::assertFalse(PrecedenceTier::of(6, 0)->equals(PrecedenceTier::of(7, 0)));
    }

    public function testAccessors(): void
    {
        $tier = PrecedenceTier::of(8, 3);

        self::assertSame(8, $tier->ordinal());
        self::assertSame(3, $tier->subOrder());
    }

    public function testRejectsOrdinalBelowOne(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PrecedenceTier::of(0);
    }
}
