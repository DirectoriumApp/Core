<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Temporal;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Temporal\TemporalCalendar;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TemporalCalendarTest extends TestCase
{
    /**
     * @dataProvider romanNumerals
     */
    public function testRoman(int $number, string $expected): void
    {
        self::assertSame($expected, TemporalCalendar::roman($number));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public function romanNumerals(): array
    {
        return [
            'one' => [1, 'I'],
            'four' => [4, 'IV'],
            'six' => [6, 'VI'],
            'seven' => [7, 'VII'],
            'eight' => [8, 'VIII'],
            // The Time after Pentecost counts Sundays into the twenties.
            'twenty-three' => [23, 'XXIII'],
            'twenty-four' => [24, 'XXIV'],
            'twenty-eight' => [28, 'XXVIII'],
        ];
    }

    /**
     * @dataProvider outOfRangeNumbers
     */
    public function testRomanRejectsOutOfRange(int $number): void
    {
        $this->expectException(InvalidArgumentException::class);

        TemporalCalendar::roman($number);
    }

    /**
     * @return array<string, array{int}>
     */
    public function outOfRangeNumbers(): array
    {
        return ['zero' => [0], 'negative' => [-1], 'too big' => [4000]];
    }

    /**
     * The liturgical feria numbering: Monday = feria-2 … Friday = feria-6,
     * Saturday = sabbatum.
     *
     * @dataProvider weekdays
     */
    public function testFeriaTokenAndLatin(string $date, string $token, string $latin): void
    {
        $day = self::utc($date);
        self::assertSame($token, TemporalCalendar::feriaToken($day));
        self::assertSame($latin, TemporalCalendar::feriaLatin($day, 'Adventus'));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public function weekdays(): array
    {
        return [
            // 2025-03-10 is a Monday.
            'Monday' => ['2025-03-10', 'feria-2', 'Feria II Adventus'],
            'Tuesday' => ['2025-03-11', 'feria-3', 'Feria III Adventus'],
            'Wednesday' => ['2025-03-12', 'feria-4', 'Feria IV Adventus'],
            'Thursday' => ['2025-03-13', 'feria-5', 'Feria V Adventus'],
            'Friday' => ['2025-03-14', 'feria-6', 'Feria VI Adventus'],
            'Saturday' => ['2025-03-15', 'sabbatum', 'Sabbato Adventus'],
        ];
    }

    public function testIsSunday(): void
    {
        self::assertTrue(TemporalCalendar::isSunday(self::utc('2025-03-16')));
        self::assertFalse(TemporalCalendar::isSunday(self::utc('2025-03-15')));
    }

    public function testSameDayIgnoresConstructionButNotDate(): void
    {
        self::assertTrue(TemporalCalendar::sameDay(
            new DateTimeImmutable('2025-03-16 00:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2025-03-16 23:59:59', new DateTimeZone('UTC'))
        ));
        self::assertFalse(TemporalCalendar::sameDay(self::utc('2025-03-16'), self::utc('2025-03-17')));
    }

    public function testDaysBetweenCountsWholeDaysIncludingAcrossFebruary29(): void
    {
        self::assertSame(7, TemporalCalendar::daysBetween(self::utc('2025-03-09'), self::utc('2025-03-16')));
        // 2024 is a leap year: 28 Feb → 1 Mar spans 29 Feb.
        self::assertSame(2, TemporalCalendar::daysBetween(self::utc('2024-02-28'), self::utc('2024-03-01')));
    }

    public function testAddDaysCrossesMonthAndLeapDay(): void
    {
        self::assertSame('2025-03-01', TemporalCalendar::addDays(self::utc('2025-02-28'), 1)->format('Y-m-d'));
        self::assertSame('2024-02-29', TemporalCalendar::addDays(self::utc('2024-02-28'), 1)->format('Y-m-d'));
    }

    public function testUtcDateBuildsMidnightUtc(): void
    {
        $date = TemporalCalendar::utcDate(2025, 4, 20);
        self::assertSame('2025-04-20 00:00:00', $date->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $date->getTimezone()->getName());
    }

    private static function utc(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd . ' 00:00:00', new DateTimeZone('UTC'));
    }
}
