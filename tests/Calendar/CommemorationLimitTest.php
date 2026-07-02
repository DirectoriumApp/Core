<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Calendar;

use Introibo\Core\Attribute\RankClass;
use Introibo\Core\Calendar\CommemorationLimit;
use PHPUnit\Framework\TestCase;

final class CommemorationLimitTest extends TestCase
{
    /**
     * @dataProvider limits
     */
    public function testCommemorationLimitByDayClass(int $ordinal, int $expected): void
    {
        self::assertSame($expected, CommemorationLimit::forDayClass(RankClass::fromOrdinal($ordinal)));
    }

    /**
     * The 1960 counts: I → 1 (privileged only), II → 1, III → 2, IV → 2.
     *
     * @return array<string, array{int, int}>
     */
    public function limits(): array
    {
        return [
            'class I' => [1, 1],
            'class II' => [2, 1],
            'class III' => [3, 2],
            'class IV' => [4, 2],
        ];
    }
}
