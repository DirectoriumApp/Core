<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Attribute;

use Directorium\Core\Attribute\RankClass;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RankClassTest extends TestCase
{
    public function testOrdinalAndLabelForEachClass(): void
    {
        self::assertSame([1, 'I'], [RankClass::classI()->ordinal(), RankClass::classI()->label()]);
        self::assertSame([2, 'II'], [RankClass::classII()->ordinal(), RankClass::classII()->label()]);
        self::assertSame([3, 'III'], [RankClass::classIII()->ordinal(), RankClass::classIII()->label()]);
        self::assertSame([4, 'IV'], [RankClass::classIV()->ordinal(), RankClass::classIV()->label()]);
        self::assertSame('III', (string) RankClass::fromOrdinal(3));
    }

    /**
     * @dataProvider invalidOrdinals
     */
    public function testFromOrdinalRejectsOutOfRange(int $ordinal): void
    {
        $this->expectException(InvalidArgumentException::class);

        RankClass::fromOrdinal($ordinal);
    }

    /**
     * @return array<string, array{int}>
     */
    public function invalidOrdinals(): array
    {
        return ['zero' => [0], 'five' => [5], 'negative' => [-1]];
    }

    public function testTotalOrdering(): void
    {
        $classes = [RankClass::classI(), RankClass::classII(), RankClass::classIII(), RankClass::classIV()];

        for ($i = 0; $i < count($classes) - 1; $i++) {
            self::assertTrue($classes[$i]->isHigherThan($classes[$i + 1]));
            self::assertTrue($classes[$i + 1]->isLowerThan($classes[$i]));
            self::assertFalse($classes[$i]->equals($classes[$i + 1]));
        }
    }

    public function testEqualityAndReflexiveComparison(): void
    {
        self::assertTrue(RankClass::classII()->equals(RankClass::fromOrdinal(2)));
        self::assertFalse(RankClass::classI()->isHigherThan(RankClass::classI()));
        self::assertFalse(RankClass::classI()->isLowerThan(RankClass::classI()));
    }
}
