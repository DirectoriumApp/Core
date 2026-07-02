<?php

declare(strict_types=1);

namespace Introibo\Core\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

use function Introibo\Core\contract;
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

    public function testDaySelectsAParticularCalendar(): void
    {
        $date = new DateTimeImmutable('2026-09-03', new DateTimeZone('UTC'));

        $universal = day($date);
        $sspx = day($date, 'sspx');

        // Same feast, elevated to first class under the SSPX particular calendar.
        self::assertSame('roman:sanctorale:pius-x', $universal->celebration()[0]->id()->toString());
        self::assertSame(3, $universal->celebration()[0]->rank()->ordinal());
        self::assertSame('roman:sanctorale:pius-x', $sspx->celebration()[0]->id()->toString());
        self::assertSame(1, $sspx->celebration()[0]->rank()->ordinal());
    }

    public function testContractStampsTheSelectedCalendarBlock(): void
    {
        $date = new DateTimeImmutable('2026-09-03', new DateTimeZone('UTC'));

        // The universal calendar keeps the reserved block null (the frozen shape).
        self::assertNull(contract($date)['calendar']);

        $sspx = contract($date, false, 'sspx');
        self::assertSame(
            ['particular' => ['id' => 'introibo:overlay:roman:sspx', 'name' => 'Society of Saint Pius X']],
            $sspx['calendar']
        );
        self::assertSame(1, $sspx['celebration'][0]['rankOrdinal']);
        // The overlay also travels on the corpus-version axis.
        self::assertStringContainsString('+introibo:overlay:roman:sspx', $sspx['corpusVersion']);
    }
}
