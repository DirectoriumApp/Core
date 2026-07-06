<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Calendrical;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Calendrical\LunarAge;
use Directorium\Core\Temporal\Computus;
use PHPUnit\Framework\TestCase;

/**
 * The ecclesiastical moon's age (#244), the "Luna" of the martyrology.
 *
 * The load-bearing check is that Luna 14 falls on the ecclesiastical full moon
 * ({@see Computus::paschalFullMoon()}) every year — the anchor that ties this
 * schematic moon to the validated paschal computus — with range, daily advance,
 * and phase asserted around it.
 */
final class LunarAgeTest extends TestCase
{
    private static function utc(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone('UTC'));
    }

    public function testKnownFullMoonIsLunaFourteen(): void
    {
        // 25 March 2024 is the paschal full moon of 2024 (Easter 31 March).
        self::assertSame(14, LunarAge::onDate(self::utc('2024-03-25'))->age());
        self::assertTrue(LunarAge::onDate(self::utc('2024-03-25'))->isFullMoon());
    }

    public function testPaschalNewMoonIsLunaOne(): void
    {
        // Thirteen days before the paschal full moon: the new moon, Luna 1.
        self::assertSame(1, LunarAge::onDate(self::utc('2024-03-12'))->age());
        self::assertTrue(LunarAge::onDate(self::utc('2024-03-12'))->isNewMoon());
    }

    /**
     * The defining anchor across the full range: the paschal full moon is Luna 14
     * every year — exactly, by construction, since it is the moon's own anchor.
     */
    public function testPaschalFullMoonIsAlwaysLunaFourteen(): void
    {
        for ($year = Computus::GREGORIAN_REFORM_YEAR; $year <= 4099; $year++) {
            $moon = Computus::paschalFullMoon($year);
            self::assertSame(14, LunarAge::onDate($moon)->age(), "Paschal full moon $year is not Luna 14");
        }
    }

    /**
     * Within a resolved year the moon's age advances by one each day and resets to
     * 1 after a hollow (29) or full (30) lunation, never straying outside 1–30.
     * Walked day by day through several years, including leap years, from 1 January
     * to 31 December of each (the civil-year boundary reconciliation is a
     * documented limitation, so it is not asserted across New Year here).
     */
    public function testTheAgeAdvancesByOneDayWithinAYear(): void
    {
        foreach ([2023, 2024, 2025, 2026] as $year) {
            $date = self::utc("$year-01-01");
            $end = self::utc("$year-12-31");
            $previous = LunarAge::onDate($date)->age();

            $date = $date->add(new DateInterval('P1D'));
            while ($date <= $end) {
                $age = LunarAge::onDate($date)->age();

                self::assertGreaterThanOrEqual(1, $age, "Luna below 1 on {$date->format('Y-m-d')}");
                self::assertLessThanOrEqual(30, $age, "Luna above 30 on {$date->format('Y-m-d')}");

                $advanced = $age === $previous + 1;
                $resetAfterLunation = ($previous === 29 || $previous === 30) && $age === 1;
                self::assertTrue(
                    $advanced || $resetAfterLunation,
                    "Luna went {$previous} → {$age} on {$date->format('Y-m-d')}"
                );

                $previous = $age;
                $date = $date->add(new DateInterval('P1D'));
            }
        }
    }

    /**
     * @dataProvider phases
     */
    public function testPhaseLabels(int $age, string $expected): void
    {
        // Reach a known age by offsetting from the 2024 paschal new moon (Luna 1
        // on 12 March 2024), so age N is N-1 days later.
        $date = self::utc('2024-03-12')->add(new DateInterval('P' . ($age - 1) . 'D'));

        self::assertSame($age, LunarAge::onDate($date)->age(), 'setup: expected age');
        self::assertSame($expected, LunarAge::onDate($date)->phase());
    }

    /**
     * @return array<string, array{int, string}>
     */
    public function phases(): array
    {
        return [
            'new (1)' => [1, 'new'],
            'waxing crescent (4)' => [4, 'waxing-crescent'],
            'first quarter (8)' => [8, 'first-quarter'],
            'waxing gibbous (11)' => [11, 'waxing-gibbous'],
            'full (14)' => [14, 'full'],
            'waning gibbous (18)' => [18, 'waning-gibbous'],
            'last quarter (22)' => [22, 'last-quarter'],
            'waning crescent (27)' => [27, 'waning-crescent'],
        ];
    }

    public function testEquality(): void
    {
        self::assertTrue(
            LunarAge::onDate(self::utc('2024-03-25'))->equals(LunarAge::onDate(self::utc('2024-03-25')))
        );
        self::assertFalse(
            LunarAge::onDate(self::utc('2024-03-25'))->equals(LunarAge::onDate(self::utc('2024-03-12')))
        );
    }
}
