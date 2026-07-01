<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Temporal;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Introibo\Core\Temporal\ChristmasCycle;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ChristmasCycleTest extends TestCase
{
    /**
     * The First Sunday of Advent for known years, cross-checked against published
     * calendars (2023-12-03, 2024-12-01, 2025-11-30 are matters of record).
     *
     * @dataProvider firstSundays
     */
    public function testFirstSundayOfAdvent(int $year, string $expected): void
    {
        self::assertSame($expected, ChristmasCycle::firstSundayOfAdvent($year)->format('Y-m-d'));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public function firstSundays(): array
    {
        return [
            '1961 (short Advent)' => [1961, '1961-12-03'],
            '2023 (short Advent)' => [2023, '2023-12-03'],
            '2024' => [2024, '2024-12-01'],
            '2025 (falls on St Andrew)' => [2025, '2025-11-30'],
        ];
    }

    /**
     * For every year the First Sunday of Advent is a Sunday in the window
     * 27 November – 3 December (the Sunday nearest St Andrew).
     */
    public function testFirstSundayOfAdventAlwaysLandsInItsWindow(): void
    {
        for ($year = 1583; $year <= 2200; $year++) {
            $first = ChristmasCycle::firstSundayOfAdvent($year);
            self::assertSame('7', $first->format('N'), "not a Sunday in $year");

            $monthDay = $first->format('m-d');
            self::assertTrue(
                $monthDay >= '11-27' && $monthDay <= '12-03',
                "First Sunday of Advent $year fell on $monthDay, outside 11-27..12-03"
            );
        }
    }

    /**
     * @dataProvider shortAdventYears
     */
    public function testShortAdventDetection(int $year, bool $expected): void
    {
        self::assertSame($expected, ChristmasCycle::forYear($year)->isShortAdvent());
    }

    /**
     * A short Advent is exactly a year whose Christmas is a Monday (Christmas Eve
     * is then the fourth Sunday of Advent).
     *
     * @return array<string, array{int, bool}>
     */
    public function shortAdventYears(): array
    {
        return [
            '1961 Christmas Monday' => [1961, true],
            '2023 Christmas Monday' => [2023, true],
            '2024 Christmas Wednesday' => [2024, false],
            '2025 Christmas Thursday' => [2025, false],
        ];
    }

    public function testAnchorDates(): void
    {
        $cycle = ChristmasCycle::forYear(2024);

        self::assertSame('2024-12-01', $cycle->firstSunday()->format('Y-m-d'));
        self::assertSame('2024-12-25', $cycle->christmas()->format('Y-m-d'));
        self::assertSame('2025-01-01', $cycle->circumcision()->format('Y-m-d'));
        self::assertSame('2025-01-06', $cycle->epiphany()->format('Y-m-d'));
        // The exclusive end boundary is Septuagesima of the following year (handed to #18).
        self::assertSame('2025-02-16', $cycle->endsBefore()->format('Y-m-d'));
    }

    /**
     * The count of Sundays after the Epiphany before Septuagesima, verified by
     * hand against the Septuagesima dates the PaschalSkeleton already fixes.
     *
     * @dataProvider sundayCounts
     */
    public function testSundaysAfterEpiphanyAreCountedCorrectly(int $year, int $expected): void
    {
        self::assertSame($expected, ChristmasCycle::forYear($year)->sundaysAfterEpiphany());
    }

    /**
     * @return array<string, array{int, int}>
     */
    public function sundayCounts(): array
    {
        return [
            '1961 → 6 (late Easter 1962)' => [1961, 6],
            '2023 → 3 (early Easter 2024)' => [2023, 3],
            '2024 → 5' => [2024, 5],
            '2025 → 3' => [2025, 3],
        ];
    }

    /**
     * The number of Sundays after Epiphany is always 1–6: the calendar can never
     * open a seventh slot before Septuagesima, so none are ever silently dropped.
     */
    public function testSundaysAfterEpiphanyNeverExceedSix(): void
    {
        for ($year = 1583; $year <= 2200; $year++) {
            $count = ChristmasCycle::forYear($year)->sundaysAfterEpiphany();
            self::assertGreaterThanOrEqual(1, $count, "year $year");
            self::assertLessThanOrEqual(6, $count, "year $year");
        }
    }

    /**
     * The block is a contiguous run of days from the First Sunday of Advent up to
     * (but not including) Septuagesima — one office per day, none missing.
     */
    public function testBlockIsContiguousAndComplete(): void
    {
        $cycle = ChristmasCycle::forYear(2024);
        $days = $cycle->days();

        $expected = [];
        $cursor = $cycle->firstSunday();
        while ($cursor < $cycle->endsBefore()) {
            $expected[] = $cursor->format('Y-m-d');
            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        self::assertSame($expected, array_keys($days));
        self::assertCount(count($expected), $days);
    }

    public function testOnReturnsTheOfficeInsideTheBlockAndNullOutside(): void
    {
        $cycle = ChristmasCycle::forYear(2024);

        self::assertNotNull($cycle->on(self::utc('2024-12-25')));
        self::assertNull($cycle->on(self::utc('2024-11-30')), 'day before the First Sunday');
        self::assertNull($cycle->on(self::utc('2025-02-16')), 'Septuagesima is the exclusive boundary');
    }

    /**
     * Seasons advance Advent → Christmastide → Epiphany and never regress: the
     * distinct seasons, in the order the days visit them, are exactly those three.
     */
    public function testSeasonsTransitionInLiturgicalOrder(): void
    {
        $seen = [];
        foreach (ChristmasCycle::forYear(2024)->days() as $obs) {
            $season = $obs->season()->value();
            if ($seen === [] || end($seen) !== $season) {
                $seen[] = $season;
            }
        }

        self::assertSame(['advent', 'christmastide', 'epiphany'], $seen);
    }

    /**
     * Spot-checks of identity, kind, season, class and colour across the whole
     * cycle — the ranks and colours the acceptance criteria call out.
     *
     * @dataProvider sampleDays
     */
    public function testDayRealization(
        string $date,
        string $id,
        string $kind,
        string $season,
        string $rank,
        string $colour,
        bool $rose
    ): void {
        $obs = ChristmasCycle::forYear(2024)->on(self::utc($date));
        self::assertNotNull($obs, $date);

        self::assertSame('roman:temporale:' . $id, $obs->id()->toString(), "$date id");
        self::assertSame($kind, $obs->kind()->value(), "$date kind");
        self::assertSame($season, $obs->season()->value(), "$date season");
        self::assertSame($rank, $obs->rank()->label(), "$date rank");
        self::assertSame($colour, $obs->colour()->base()->value(), "$date colour");
        self::assertSame($rose, $obs->colour()->roseAllowed(), "$date rose");
    }

    /**
     * The id column omits the constant `roman:temporale:` prefix (prepended in the
     * assertion) so each row stays on one line.
     *
     * @return array<string, array{string, string, string, string, string, string, bool}>
     */
    public function sampleDays(): array
    {
        return [
            'Advent I — first class, violet' =>
                ['2024-12-01', 'advent:sunday-1', 'sunday', 'advent', 'I', 'violet', false],
            'Advent feria — fourth class, violet' =>
                ['2024-12-03', 'advent:week-1:feria-3', 'feria', 'advent', 'IV', 'violet', false],
            'Gaudete — second class, violet with rose' =>
                ['2024-12-15', 'advent:sunday-3', 'sunday', 'advent', 'II', 'violet', true],
            'Advent Ember Wednesday — second class' =>
                ['2024-12-18', 'advent:quattuor-temporum:feria-4', 'ember-day', 'advent', 'II', 'violet', false],
            'Greater feria (19 Dec) — second class' =>
                ['2024-12-19', 'advent:week-3:feria-5', 'feria', 'advent', 'II', 'violet', false],
            'Advent IV — second class' =>
                ['2024-12-22', 'advent:sunday-4', 'sunday', 'advent', 'II', 'violet', false],
            'Vigil of the Nativity — first class, still violet' =>
                ['2024-12-24', 'christmas:vigil', 'vigil', 'advent', 'I', 'violet', false],
            'The Nativity — first class, white' =>
                ['2024-12-25', 'christmas:nativity', 'feast', 'christmastide', 'I', 'white', false],
            'Day within the Octave — white' =>
                ['2024-12-27', 'christmas:within-octave:day-3', 'within-octave', 'christmastide', 'II', 'white', false],
            'Sunday within the Octave' =>
                ['2024-12-29', 'christmas:sunday-within-octave', 'sunday', 'christmastide', 'II', 'white', false],
            'Circumcision / Octave Day — first class' =>
                ['2025-01-01', 'christmas:octave-day', 'octave-day', 'christmastide', 'I', 'white', false],
            'Sunday after the Octave (2–5 Jan)' =>
                ['2025-01-05', 'christmas:sunday-after-octave', 'sunday', 'christmastide', 'II', 'white', false],
            'The Epiphany — first class, white' =>
                ['2025-01-06', 'epiphany:domini', 'feast', 'epiphany', 'I', 'white', false],
            'Feria after Epiphany — green' =>
                ['2025-01-09', 'epiphany:post-epiphaniam:feria-5', 'feria', 'epiphany', 'IV', 'green', false],
            '1st Sunday after Epiphany — green, second class' =>
                ['2025-01-12', 'epiphany:sunday-1', 'sunday', 'epiphany', 'II', 'green', false],
            '5th Sunday after Epiphany' =>
                ['2025-02-09', 'epiphany:sunday-5', 'sunday', 'epiphany', 'II', 'green', false],
        ];
    }

    /**
     * Latin names are the invariant display fallback; a handful pin the naming.
     *
     * @dataProvider latinNames
     */
    public function testLatinNames(string $date, string $expected): void
    {
        $obs = ChristmasCycle::forYear(2024)->on(self::utc($date));
        self::assertNotNull($obs, $date);
        self::assertSame($expected, $obs->latinName());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function latinNames(): array
    {
        return [
            'Advent feria' => ['2024-12-03', 'Feria III infra Hebdomadam I Adventus'],
            'Gaudete' => ['2024-12-15', 'Dominica III Adventus'],
            'Advent Ember Wednesday' => ['2024-12-18', 'Feria IV Quatuor Temporum Adventus'],
            'Advent Ember Saturday' => ['2024-12-21', 'Sabbato Quatuor Temporum Adventus'],
            'Nativity' => ['2024-12-25', 'In Nativitate Domini'],
            'Circumcision' => ['2025-01-01', 'In Circumcisione Domini'],
            'Epiphany' => ['2025-01-06', 'In Epiphania Domini'],
            'Sunday after Epiphany' => ['2025-01-12', 'Dominica I post Epiphaniam'],
            'Feria after Epiphany' => ['2025-01-13', 'Feria II infra Hebdomadam I post Epiphaniam'],
        ];
    }

    public function testShortAdventEmitsTheVigilOnTheFourthSunday(): void
    {
        // 2023 is a short Advent: 24 December is both the fourth Sunday of Advent
        // and the Vigil of the Nativity — the Vigil takes the day.
        $obs = ChristmasCycle::forYear(2023)->on(self::utc('2023-12-24'));

        self::assertNotNull($obs);
        self::assertSame('roman:temporale:christmas:vigil', $obs->id()->toString());
        self::assertSame('vigil', $obs->kind()->value());
    }

    public function testPreGregorianYearIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ChristmasCycle::forYear(1582);
    }

    private static function utc(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd . ' 00:00:00', new DateTimeZone('UTC'));
    }
}
