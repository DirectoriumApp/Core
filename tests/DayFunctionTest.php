<?php

declare(strict_types=1);

namespace Directorium\Core\Tests;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use function Directorium\Core\contract;
use function Directorium\Core\day;

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
            ['particular' => ['id' => 'directorium:overlay:roman:sspx', 'name' => 'Society of Saint Pius X']],
            $sspx['calendar']
        );
        self::assertSame(1, $sspx['celebration'][0]['rankOrdinal']);
        // The overlay also travels on the corpus-version axis.
        self::assertStringContainsString('+directorium:overlay:roman:sspx', $sspx['corpusVersion']);
    }

    public function testDayDefaultsToTheNineteenSixtyEdition(): void
    {
        $date = new DateTimeImmutable('2026-06-29', new DateTimeZone('UTC'));
        $default = day($date)->celebration()[0]->id()->toString();

        // Naming the 1962 edition explicitly (by alias or URN) resolves identically to the default.
        self::assertSame($default, day($date, null, '1962')->celebration()[0]->id()->toString());
        self::assertSame($default, day($date, null, 'roman:rubricae-1960')->celebration()[0]->id()->toString());
    }

    public function testContractStampsTheEditionProvenance(): void
    {
        $date = new DateTimeImmutable('2026-06-29', new DateTimeZone('UTC'));

        self::assertSame('roman:rubricae-1960', contract($date)['edition']);
        self::assertSame('roman:rubricae-1960', contract($date, false, null, '1962')['edition']);
    }

    public function testSelectingAnUnbuiltEditionThrows(): void
    {
        // 1954's precedence engine is wired (#67), but its calendar is incomplete pending the
        // dataset burndown (#64), so it is not yet marked built: the public boundary refuses
        // it. (1955 has no engine at all and is likewise refused.)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not yet built/');
        day(new DateTimeImmutable('2026-06-29', new DateTimeZone('UTC')), null, '1954');
    }
}
