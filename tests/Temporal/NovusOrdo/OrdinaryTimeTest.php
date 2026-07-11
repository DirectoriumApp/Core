<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Temporal\NovusOrdo;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Temporal\NovusOrdo\OrdinaryTime;
use Directorium\Core\Temporal\Season;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The Novus-Ordo Ordinary Time filler (#258): its two blocks, the single 1–34 week
 * series counted backward from Christ the King, the omitted week of a 33-week year,
 * and the office it mints from the edition's `ot-sunday` / `ot-feria` archetypes.
 *
 * The archetypes are read from a minimal test fixture (a two-row temporal corpus);
 * the real cited Novus-Ordo temporal data ships with the general-calendar corpus
 * (#108). The week arithmetic is checked against dates computed by hand from the
 * Baptism/Ash-Wednesday/Pentecost/Advent anchors and cross-read against the published
 * 2024/2025 calendars (romcal/USCCB); the full-year oracle sweep is #260.
 */
final class OrdinaryTimeTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../fixtures/novus-ordo-temporal';

    private const EDITION = 'roman-novus-ordo-2002';

    private function ordinaryTime(int $year): OrdinaryTime
    {
        return OrdinaryTime::forYear($year, Corpus::at(self::FIXTURE), self::EDITION);
    }

    private static function utc(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone('UTC'));
    }

    /**
     * The Baptism of the Lord is the Sunday after 6 January — 7 Jan 2024 (6 Jan a
     * Saturday), 12 Jan 2025 (6 Jan a Monday).
     */
    public function testBaptismOfTheLordIsTheSundayAfterEpiphany(): void
    {
        self::assertSame('2024-01-07', OrdinaryTime::baptismOfTheLord(2024)->format('Y-m-d'));
        self::assertSame('2025-01-12', OrdinaryTime::baptismOfTheLord(2025)->format('Y-m-d'));
    }

    /** Block I opens the Monday after the Baptism as week 1, so the Baptism day itself is not Ordinary Time. */
    public function testBlockOneOpensTheMondayAfterTheBaptism(): void
    {
        $ot = $this->ordinaryTime(2024);

        self::assertNull($ot->on(self::utc('2024-01-07')), 'The Baptism (7 Jan 2024) closes Christmas Time.');

        $monday = $ot->on(self::utc('2024-01-08'));
        self::assertNotNull($monday);
        self::assertSame('roman:temporale:ordinary-time:week-1:feria-2', $monday->id()->toString());
        self::assertSame('Feria II hebdomadae I per annum', $monday->latinName());
    }

    /** There is no "First Sunday of Ordinary Time": the Baptism occupies it, so the first Sunday minted is the Second. */
    public function testFirstMintedSundayIsTheSecond(): void
    {
        self::assertSame(
            'roman:temporale:ordinary-time:sunday-2',
            $this->ordinaryTime(2024)->on(self::utc('2024-01-14'))->id()->toString()
        );
        self::assertSame(
            'Dominica II per annum',
            $this->ordinaryTime(2024)->on(self::utc('2024-01-14'))->latinName()
        );
        self::assertSame(
            'roman:temporale:ordinary-time:sunday-2',
            $this->ordinaryTime(2025)->on(self::utc('2025-01-19'))->id()->toString()
        );
    }

    /** Block I runs up to the Tuesday before Ash Wednesday; Ash Wednesday itself opens Lent (not Ordinary Time). */
    public function testBlockOneEndsBeforeAshWednesday(): void
    {
        $ot = $this->ordinaryTime(2024); // Ash Wednesday 14 Feb 2024

        self::assertNotNull($ot->on(self::utc('2024-02-13')), 'Shrove Tuesday is the last OT day before Lent.');
        self::assertSame('roman:temporale:ordinary-time:sunday-6', $ot->on(self::utc('2024-02-11'))->id()->toString());
        self::assertNull($ot->on(self::utc('2024-02-14')), 'Ash Wednesday belongs to Lent.');
    }

    /**
     * Block II resumes the Monday after Pentecost, counting backward from Christ the
     * King. 2024 is a continuous 34-week year: the last week before Lent is the 6th and
     * Block II resumes at the 7th (Mon 20 May 2024).
     */
    public function testBlockTwoResumesAfterPentecost(): void
    {
        $ot = $this->ordinaryTime(2024); // Pentecost 19 May 2024

        self::assertNull($ot->on(self::utc('2024-05-19')), 'Pentecost is the last day of Easter Time.');

        $monday = $ot->on(self::utc('2024-05-20'));
        self::assertNotNull($monday);
        self::assertSame('roman:temporale:ordinary-time:week-7:feria-2', $monday->id()->toString());
        self::assertSame('Feria II hebdomadae VII per annum', $monday->latinName());

        // The first Sunday after Pentecost is the 8th of Ordinary Time (Trinity is laid over it elsewhere).
        self::assertSame(
            'roman:temporale:ordinary-time:sunday-8',
            $ot->on(self::utc('2024-05-26'))->id()->toString()
        );
    }

    /**
     * A 33-week year omits exactly one week — the week that would have followed Block I.
     * 2025's last pre-Lent week is the 8th and Block II resumes at the 10th (Mon 9 Jun
     * 2025), so week 9 never appears.
     */
    public function testThirtyThreeWeekYearOmitsOneWeek(): void
    {
        $ot = $this->ordinaryTime(2025);

        self::assertSame(
            'roman:temporale:ordinary-time:sunday-8',
            $ot->on(self::utc('2025-03-02'))->id()->toString(),
            'The last Sunday before Lent 2025 is the 8th.'
        );
        self::assertSame(
            'roman:temporale:ordinary-time:week-10:feria-2',
            $ot->on(self::utc('2025-06-09'))->id()->toString(),
            'Ordinary Time resumes at the 10th week after Pentecost 2025 — the 9th is omitted.'
        );

        $weeks = $this->weeksSeen(2025);
        self::assertNotContains(9, $weeks, 'Week 9 is the omitted week in the 33-week year 2025.');
        self::assertContains(8, $weeks);
        self::assertContains(10, $weeks);
        self::assertContains(34, $weeks);
    }

    /** A continuous 34-week year has every week from 1 to 34 (2024). */
    public function testThirtyFourWeekYearIsContinuous(): void
    {
        $weeks = $this->weeksSeen(2024);

        self::assertContains(7, $weeks, '2024 is continuous — week 7 is present.');
        for ($n = 1; $n <= 34; $n++) {
            self::assertContains($n, $weeks, "Week $n must appear in the continuous year 2024.");
        }
    }

    /** Christ the King is always the 34th Sunday — the Sunday before Advent I, the last Ordinary-Time slot. */
    public function testChristTheKingIsTheThirtyFourthSunday(): void
    {
        $ot = $this->ordinaryTime(2024);

        self::assertSame('2024-11-24', $ot->christTheKing()->format('Y-m-d'));
        self::assertSame(
            'roman:temporale:ordinary-time:sunday-34',
            $ot->on(self::utc('2024-11-24'))->id()->toString()
        );
        self::assertSame('Dominica XXXIV per annum', $ot->on(self::utc('2024-11-24'))->latinName());

        // The First Sunday of Advent (1 Dec 2024) is the exclusive upper boundary.
        self::assertNull($ot->on(self::utc('2024-12-01')));
    }

    /** Ordinary Time owns neither the great feasts that bracket it nor the penitential/festal seasons between. */
    public function testSeasonalBoundariesAreNotOrdinaryTime(): void
    {
        $ot = $this->ordinaryTime(2024);

        foreach (['2024-01-01', '2024-01-06', '2024-03-31', '2024-12-25'] as $date) {
            self::assertNull($ot->on(self::utc($date)), "$date is not Ordinary Time.");
        }
    }

    /** Every Ordinary-Time office is green and in the `ordinary-time` season, with the archetype's kind. */
    public function testOfficeIsGreenOrdinaryTime(): void
    {
        $ot = $this->ordinaryTime(2024);

        $sunday = $ot->on(self::utc('2024-01-14'));
        self::assertSame(Season::ORDINARY_TIME, $sunday->season()->value());
        self::assertSame('green', $sunday->colour()->base()->value());
        self::assertSame('sunday', $sunday->kind()->value());

        $feria = $ot->on(self::utc('2024-01-08'));
        self::assertSame('feria', $feria->kind()->value());
        self::assertSame('green', $feria->colour()->base()->value());
    }

    public function testRejectsPreGregorianYear(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OrdinaryTime::forYear(1580, Corpus::at(self::FIXTURE), self::EDITION);
    }

    /**
     * The distinct week ordinals appearing in a year, from the minted slugs.
     *
     * @return list<int>
     */
    private function weeksSeen(int $year): array
    {
        $weeks = [];
        foreach ($this->ordinaryTime($year)->days() as $office) {
            if (preg_match('/:(?:sunday|week)-(\d+)/', $office->id()->toString(), $m) === 1) {
                $weeks[(int) $m[1]] = true;
            }
        }
        ksort($weeks);

        return array_keys($weeks);
    }
}
