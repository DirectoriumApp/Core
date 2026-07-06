<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Calendrical;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Calendrical\CalendricalYear;
use Directorium\Core\Temporal\Computus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The calendrical numbers of a year (#243): Golden Number, Epact, Solar Cycle,
 * Dominical Letter(s), Roman Indiction.
 *
 * The epact is the one figure whose century phasing is easy to get wrong, so it
 * is pinned to the independently-published Gregorian epact table for 2000–2099
 * (which cycles through all nineteen golden numbers over 2000–2018) plus a
 * cross-century check either side of a solar correction. The other four are
 * anchored to published almanac values and cross-checked by self-validating
 * invariants — a dominical letter must actually mark a Sunday.
 */
final class CalendricalYearTest extends TestCase
{
    /**
     * The published Gregorian epact for the 2000–2099 correction window: the
     * standard table, walked golden-number by golden-number across 2000–2018,
     * then the years either side of the 2100 solar correction (which steps the
     * epact down by one) and a 20th-century year (same window as the 2000s, so
     * the same value for the same golden number — the check that no spurious
     * step is introduced at the leap century 2000).
     *
     * @dataProvider publishedEpacts
     */
    public function testEpactMatchesThePublishedTable(int $year, int $expected): void
    {
        self::assertSame($expected, CalendricalYear::forYear($year)->epact());
    }

    /**
     * @return array<string, array{int, int}>
     */
    public function publishedEpacts(): array
    {
        return [
            '2000 (GN 6)' => [2000, 24],
            '2001 (GN 7)' => [2001, 5],
            '2002 (GN 8)' => [2002, 16],
            '2003 (GN 9)' => [2003, 27],
            '2004 (GN 10)' => [2004, 8],
            '2005 (GN 11)' => [2005, 19],
            '2006 (GN 12)' => [2006, 0],
            '2007 (GN 13)' => [2007, 11],
            '2008 (GN 14)' => [2008, 22],
            '2009 (GN 15)' => [2009, 3],
            '2010 (GN 16)' => [2010, 14],
            '2011 (GN 17)' => [2011, 25],
            '2012 (GN 18)' => [2012, 6],
            '2013 (GN 19)' => [2013, 17],
            '2014 (GN 1)' => [2014, 29],
            '2015 (GN 2)' => [2015, 10],
            '2016 (GN 3)' => [2016, 21],
            '2017 (GN 4)' => [2017, 2],
            '2018 (GN 5)' => [2018, 13],
            // 1954 shares the 1900–2099 window: golden number 17, same as 2011.
            '1954 (GN 17, same window as 2011)' => [1954, 25],
            // The 2100 solar correction steps the epact down one: GN 11 → 18, not 19.
            '2119 (GN 11, past the 2100 correction)' => [2119, 18],
        ];
    }

    /**
     * @dataProvider knownYears
     */
    public function testGoldenNumberSolarCycleAndIndiction(
        int $year,
        int $goldenNumber,
        int $solarCycle,
        int $romanIndiction
    ): void {
        $block = CalendricalYear::forYear($year);

        self::assertSame($goldenNumber, $block->goldenNumber(), 'golden number');
        self::assertSame($solarCycle, $block->solarCycle(), 'solar cycle');
        self::assertSame($romanIndiction, $block->romanIndiction(), 'roman indiction');
    }

    /**
     * Published almanac values for the cyclic numbers.
     *
     * @return array<string, array{int, int, int, int}>
     */
    public function knownYears(): array
    {
        return [
            //                      year   GN  solarCycle  indiction
            'AD 2000' => [2000, 6, 21, 8],
            'AD 2024' => [2024, 11, 17, 2],
            'AD 2025' => [2025, 12, 18, 3],
        ];
    }

    /**
     * @dataProvider dominicalLetters
     */
    public function testDominicalLetter(int $year, string $expected): void
    {
        self::assertSame($expected, CalendricalYear::forYear($year)->dominicalLetter());
    }

    /**
     * @return array<string, array{int, string}>
     */
    public function dominicalLetters(): array
    {
        return [
            '2023 common → A' => [2023, 'A'],
            '2024 leap → GF' => [2024, 'GF'],
            '2025 common → E' => [2025, 'E'],
            '2000 leap → BA' => [2000, 'BA'],
        ];
    }

    public function testGoldenNumberIsTheMetonicRemainderPlusOne(): void
    {
        for ($year = Computus::GREGORIAN_REFORM_YEAR; $year <= 4099; $year++) {
            self::assertSame(($year % 19) + 1, CalendricalYear::forYear($year)->goldenNumber());
        }
    }

    public function testEveryFigureStaysWithinItsTraditionalRange(): void
    {
        for ($year = Computus::GREGORIAN_REFORM_YEAR; $year <= 4099; $year++) {
            $block = CalendricalYear::forYear($year);

            self::assertGreaterThanOrEqual(1, $block->goldenNumber());
            self::assertLessThanOrEqual(19, $block->goldenNumber());

            self::assertGreaterThanOrEqual(0, $block->epact());
            self::assertLessThanOrEqual(29, $block->epact());

            self::assertGreaterThanOrEqual(1, $block->solarCycle());
            self::assertLessThanOrEqual(28, $block->solarCycle());

            self::assertGreaterThanOrEqual(1, $block->romanIndiction());
            self::assertLessThanOrEqual(15, $block->romanIndiction());
        }
    }

    /**
     * The dominical letter must genuinely mark the Sundays: the letter's place in
     * the alphabet is the January day it falls on, and that day (and its weekly
     * repeats) must be a Sunday. A leap year carries a second letter (one earlier)
     * for March onward; a common year carries exactly one.
     */
    public function testDominicalLetterActuallyMarksSundays(): void
    {
        for ($year = Computus::GREGORIAN_REFORM_YEAR; $year <= 4099; $year++) {
            $block = CalendricalYear::forYear($year);
            $letters = $block->dominicalLetter();

            $leap = ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;
            self::assertSame($leap ? 2 : 1, strlen($letters), "letter count for $year");

            // The January (first) letter: its ordinal is the January date it marks.
            $januaryDay = ord($letters[0]) - ord('A') + 1;
            $marked = (new DateTimeImmutable('1970-01-01 00:00:00', new DateTimeZone('UTC')))
                ->setDate($year, 1, $januaryDay);
            self::assertSame('7', $marked->format('N'), "dominical letter marks a non-Sunday in $year");

            if ($leap) {
                // The March-onward letter is exactly one place earlier, wrapping A→G.
                $first = ord($letters[0]) - ord('A');
                $second = ord($letters[1]) - ord('A');
                self::assertSame(($first - 1 + 7) % 7, $second, "second leap letter for $year");
            }
        }
    }

    public function testRejectsYearsBeforeTheGregorianReform(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CalendricalYear::forYear(Computus::GREGORIAN_REFORM_YEAR - 1);
    }

    public function testTheFirstGregorianYearIsAccepted(): void
    {
        self::assertSame(
            Computus::GREGORIAN_REFORM_YEAR,
            CalendricalYear::forYear(Computus::GREGORIAN_REFORM_YEAR)->year()
        );
    }

    public function testEqualityIsByYear(): void
    {
        self::assertTrue(CalendricalYear::forYear(1962)->equals(CalendricalYear::forYear(1962)));
        self::assertFalse(CalendricalYear::forYear(1962)->equals(CalendricalYear::forYear(1954)));
    }
}
