<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Temporal;

use Directorium\Core\Temporal\Computus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ComputusTest extends TestCase
{
    /**
     * @dataProvider knownEasters
     */
    public function testReturnsKnownEasterDate(int $year, string $expected): void
    {
        self::assertSame($expected, Computus::gregorianEaster($year)->format('Y-m-d'));
    }

    /**
     * A spread of independently known Gregorian Easter dates across the
     * centuries, including the two extremes: the earliest possible Easter
     * (22 March, 1818) and the latest possible (25 April, 1943 and 2038).
     *
     * @return array<string, array{int, string}>
     */
    public function knownEasters(): array
    {
        return [
            '1583 first Gregorian Easter' => [1583, '1583-04-10'],
            '1818 earliest possible (22 Mar)' => [1818, '1818-03-22'],
            '1911 Divino Afflatu era' => [1911, '1911-04-16'],
            '1943 latest possible (25 Apr)' => [1943, '1943-04-25'],
            '1954' => [1954, '1954-04-18'],
            '1955' => [1955, '1955-04-10'],
            '1962 engine baseline' => [1962, '1962-04-22'],
            '2000' => [2000, '2000-04-23'],
            '2008 very early' => [2008, '2008-03-23'],
            '2011 very late' => [2011, '2011-04-24'],
            '2024' => [2024, '2024-03-31'],
            '2025' => [2025, '2025-04-20'],
            '2026' => [2026, '2026-04-05'],
            '2027' => [2027, '2027-03-28'],
            '2038 latest possible (25 Apr)' => [2038, '2038-04-25'],
        ];
    }

    public function testEasterIsReturnedAtUtcMidnight(): void
    {
        $easter = Computus::gregorianEaster(1962);

        self::assertSame('00:00:00', $easter->format('H:i:s'));
        self::assertSame('UTC', $easter->getTimezone()->getName());
    }

    public function testRejectsYearsBeforeTheGregorianReform(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Computus::gregorianEaster(Computus::GREGORIAN_REFORM_YEAR - 1);
    }

    /**
     * Across a wide span, Easter is always a Sunday and always falls within the
     * 22 March – 25 April window. This invariant catches any arithmetic slip
     * the fixed dates above might miss.
     */
    public function testEasterAlwaysFallsOnASundayWithinItsWindow(): void
    {
        for ($year = Computus::GREGORIAN_REFORM_YEAR; $year <= 2299; $year++) {
            $easter = Computus::gregorianEaster($year);

            self::assertSame('7', $easter->format('N'), "Easter $year is not a Sunday");

            $monthDay = $easter->format('m-d');
            self::assertGreaterThanOrEqual('03-22', $monthDay, "Easter $year is before 22 March");
            self::assertLessThanOrEqual('04-25', $monthDay, "Easter $year is after 25 April");
        }
    }

    /**
     * @dataProvider knownPaschalFullMoons
     */
    public function testReturnsKnownPaschalFullMoon(int $year, string $expected): void
    {
        self::assertSame($expected, Computus::paschalFullMoon($year)->format('Y-m-d'));
    }

    /**
     * The paschal moon sits thirteen days before its Easter, off a Sunday: e.g.
     * 2024 Easter is 31 Mar, moon 25 Mar; 2025 Easter 20 Apr, moon 13 Apr (itself
     * a Sunday, so Easter is the following Sunday).
     *
     * @return array<string, array{int, string}>
     */
    public function knownPaschalFullMoons(): array
    {
        return [
            '2024' => [2024, '2024-03-25'],
            '2025' => [2025, '2025-04-13'],
            '1962 (capped at 18 Apr by the Clavian correction)' => [1962, '1962-04-18'],
        ];
    }

    /**
     * The defining relation, proven against the independently-computed Easter:
     * the paschal full moon is always on or after 21 March, never later than
     * 18 April, and Easter is the first Sunday strictly after it.
     */
    public function testEasterIsAlwaysTheFirstSundayAfterThePaschalFullMoon(): void
    {
        for ($year = Computus::GREGORIAN_REFORM_YEAR; $year <= 4099; $year++) {
            $moon = Computus::paschalFullMoon($year);
            $easter = Computus::gregorianEaster($year);

            $monthDay = $moon->format('m-d');
            self::assertGreaterThanOrEqual('03-21', $monthDay, "Paschal moon $year is before 21 March");
            self::assertLessThanOrEqual('04-18', $monthDay, "Paschal moon $year is after 18 April");

            self::assertGreaterThan($moon, $easter, "Easter $year is not after its paschal moon");
            $diff = (int) $easter->diff($moon)->format('%a');
            self::assertGreaterThanOrEqual(1, $diff, "Easter $year is not after its paschal moon");
            self::assertLessThanOrEqual(7, $diff, "Easter $year is more than a week after its paschal moon");
        }
    }
}
