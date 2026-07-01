<?php

declare(strict_types=1);

namespace Introibo\Core\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function Introibo\Core\day;

final class DayFunctionTest extends TestCase
{
    public function testDayReturnsAnEmptyDayForTheGivenDate(): void
    {
        $date = new DateTimeImmutable('2026-06-30');
        $day = day($date);

        self::assertSame($date, $day->date());
        self::assertTrue($day->isEmpty());
        self::assertSame([], $day->celebration());
        self::assertSame([], $day->commemoration());
        self::assertSame([], $day->displaced());
        self::assertSame([], $day->tempora());
    }
}
