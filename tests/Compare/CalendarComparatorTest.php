<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Compare;

use Directorium\Core\Compare\CalendarComparator;
use Directorium\Core\Compare\ComparisonField;
use Directorium\Core\Edition\RubricSystem;
use Directorium\Core\Temporal\TemporalCalendar;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Calendar-mode comparison (#312): the engine resolved under two or more editions,
 * diffed day by day. Fixtures are pinned to real divergences the built engines
 * produce (each edition is itself validated against the Divinum Officium oracle), so
 * these tests exercise the comparison, not the calendar data.
 */
final class CalendarComparatorTest extends TestCase
{
    private const E1962 = RubricSystem::RUBRICAE_1960;
    private const E1954 = RubricSystem::DIVINO_AFFLATU;
    private const E1955 = RubricSystem::RUBRICAE_1955;
    private const ENO = RubricSystem::NOVUS_ORDO_2002;

    /**
     * The Novus-Ordo 2002 snapshot resolves independently and is diffable against a
     * traditional edition through the ordinary comparison machinery (#366 AC). On 22 July
     * 2025 both keep St Mary Magdalene, but the snapshot replays the reform's 2016 decree
     * (Apostolorum Apostola), which raised her to a FEAST — so the day diverges on rank
     * while agreeing on the feast itself. This proves both that the snapshot is a
     * first-class comparison participant and that a dated decree flows into the diff.
     */
    public function testTheNovusOrdoSnapshotIsDiffableAndReflectsItsDecrees(): void
    {
        $day = (new CalendarComparator())->compareDay(
            ['roman:novus-ordo-2002', '1962'],
            TemporalCalendar::utcDate(2025, 7, 22)
        );

        self::assertTrue($day->isDivergent());
        self::assertSame('roman:sanctorale:maria-magdalena', $day->cell(self::ENO)->feastId());
        self::assertSame('roman:sanctorale:maria-magdalena', $day->cell(self::E1962)->feastId());
        self::assertTrue($day->agreesOn(ComparisonField::FEAST));
        self::assertContains(ComparisonField::RANK, $day->divergences());
        self::assertSame(2, $day->cell(self::ENO)->rankOrdinal(), 'a feast under the 2016 decree');
        self::assertSame(3, $day->cell(self::E1962)->rankOrdinal(), 'her memorial-grade double in 1962');
    }

    /** Christmas is I class white in every edition — a day the reforms never touched. */
    public function testIdenticalDayHasNoDivergences(): void
    {
        $day = (new CalendarComparator())->compareDay(
            ['1962', '1954', '1955'],
            TemporalCalendar::utcDate(2024, 12, 25)
        );

        self::assertFalse($day->isDivergent());
        self::assertSame([], $day->divergences());
        self::assertSame('roman:temporale:christmas:nativity', $day->cell(self::E1962)->feastId());
        self::assertSame('roman:temporale:christmas:nativity', $day->cell(self::E1954)->feastId());
        self::assertSame(1, $day->cell(self::E1955)->rankOrdinal());
    }

    /**
     * The Vigil of the Assumption is the same feast, colour, season, and commemoration
     * in all three editions, but II class in 1962 and IV in 1954/1955 — so the day
     * diverges on rank and on nothing else.
     */
    public function testSingleFieldDivergenceIsIsolated(): void
    {
        $day = (new CalendarComparator())->compareDay(
            ['1962', '1954', '1955'],
            TemporalCalendar::utcDate(2024, 8, 14)
        );

        self::assertTrue($day->isDivergent());
        self::assertSame([ComparisonField::RANK], $day->divergences());
        self::assertTrue($day->agreesOn(ComparisonField::FEAST));
        self::assertTrue($day->agreesOn(ComparisonField::COLOUR));
        self::assertTrue($day->agreesOn(ComparisonField::COMMEMORATIONS));
        self::assertTrue($day->agreesOn(ComparisonField::SEASON));

        self::assertSame(2, $day->cell(self::E1962)->rankOrdinal());
        self::assertSame(4, $day->cell(self::E1954)->rankOrdinal());
        self::assertSame(['roman:sanctorale:eusebius-confessor'], $day->cell(self::E1962)->commemorations());
    }

    /**
     * 8 November 1954 keeps the Octave Day of All Saints (III, white, sanctoral); 1962
     * abolished the octave, so the day is a green feria — a multi-field divergence on
     * feast, rank, and colour, but not season.
     */
    public function testMultiFieldDivergence(): void
    {
        $day = (new CalendarComparator())->compareDay(
            ['1962', '1954'],
            TemporalCalendar::utcDate(2024, 11, 8)
        );

        self::assertTrue($day->isDivergent());
        self::assertFalse($day->agreesOn(ComparisonField::FEAST));
        self::assertFalse($day->agreesOn(ComparisonField::RANK));
        self::assertFalse($day->agreesOn(ComparisonField::COLOUR));
        self::assertTrue($day->agreesOn(ComparisonField::SEASON));

        self::assertSame('roman:sanctorale:omnes-sancti:in-octava', $day->cell(self::E1954)->feastId());
        self::assertSame('white', $day->cell(self::E1954)->colour());
        self::assertSame('green', $day->cell(self::E1962)->colour());
    }

    /** A range carries every day and rolls up how many of them diverge. */
    public function testCompareRangeCountsAndLocatesDivergentDays(): void
    {
        $comparison = (new CalendarComparator())->compareRange(
            ['1962', '1954'],
            TemporalCalendar::utcDate(2024, 11, 6),
            TemporalCalendar::utcDate(2024, 11, 10)
        );

        self::assertSame([self::E1962, self::E1954], $comparison->editions());
        self::assertSame(5, $comparison->dayCount());
        self::assertGreaterThanOrEqual(1, $comparison->divergentDayCount());

        $divergentDates = array_map(
            static fn ($day): string => $day->date()->format('Y-m-d'),
            $comparison->divergentDays()
        );
        self::assertContains('2024-11-08', $divergentDates);
    }

    /** Selectors are normalised to edition urns and de-duplicated, preserving order. */
    public function testEditionsAreNormalisedAndDeduped(): void
    {
        $day = (new CalendarComparator())->compareDay(
            ['1954', '1962', '1960'], // '1962' and '1960' are the same edition
            TemporalCalendar::utcDate(2024, 8, 14)
        );

        self::assertSame([self::E1954, self::E1962], array_keys($day->cells()));

        // toArray carries the edition order explicitly, since JSON object key order is not guaranteed.
        self::assertSame([self::E1954, self::E1962], $day->toArray()['editions']);
    }

    public function testRejectsFewerThanTwoDistinctEditions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CalendarComparator())->compareDay(
            ['1962', 'roman:rubricae-1960'],
            TemporalCalendar::utcDate(2024, 8, 14)
        );
    }

    public function testRejectsInvertedRange(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CalendarComparator())->compareRange(
            ['1962', '1954'],
            TemporalCalendar::utcDate(2024, 11, 10),
            TemporalCalendar::utcDate(2024, 11, 6)
        );
    }
}
