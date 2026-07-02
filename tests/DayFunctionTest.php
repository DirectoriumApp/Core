<?php

declare(strict_types=1);

namespace Introibo\Core\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

use function Introibo\Core\day;

final class DayFunctionTest extends TestCase
{
    public function testDayResolvesTheCelebrationForADate(): void
    {
        $day = day(new DateTimeImmutable('2025-12-25', new DateTimeZone('UTC')));

        self::assertFalse($day->isEmpty());
        self::assertCount(1, $day->celebration());
        self::assertSame(
            'roman:temporale:christmas:nativity',
            $day->celebration()[0]->id()->toString()
        );
    }

    public function testDayIsMemoisedAndStableAcrossCalls(): void
    {
        $first = day(new DateTimeImmutable('2025-06-29', new DateTimeZone('UTC')));
        $second = day(new DateTimeImmutable('2025-06-29', new DateTimeZone('UTC')));

        self::assertSame(
            $first->celebration()[0]->id()->toString(),
            $second->celebration()[0]->id()->toString()
        );
        self::assertSame('roman:sanctorale:petrus-paulus', $first->celebration()[0]->id()->toString());
    }
}
