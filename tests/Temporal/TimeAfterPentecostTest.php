<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Temporal;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Temporal\ChristmasCycle;
use Directorium\Core\Temporal\PaschalSkeleton;
use Directorium\Core\Temporal\TimeAfterPentecost;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TimeAfterPentecostTest extends TestCase
{
    public function testAnchors(): void
    {
        // 2035 is a long green season (early Easter, 25 March → 28 Sundays).
        $season = TimeAfterPentecost::forYear(2035);

        self::assertSame('2035-03-25', $season->easter()->format('Y-m-d'));
        self::assertEquals(PaschalSkeleton::forYear(2035)->date('trinity-sunday'), $season->trinitySunday());
        self::assertEquals(ChristmasCycle::firstSundayOfAdvent(2035), $season->endsBefore());
    }

    /**
     * The block is a contiguous run from Trinity Sunday to the eve of Advent, and
     * every day of it is the green Time after Pentecost.
     *
     * @dataProvider sampleYears
     */
    public function testBlockIsContiguousAndGreen(int $year): void
    {
        $season = TimeAfterPentecost::forYear($year);
        $days = $season->days();

        $expected = [];
        $cursor = $season->trinitySunday();
        while ($cursor < $season->endsBefore()) {
            $expected[] = $cursor->format('Y-m-d');
            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        self::assertSame($expected, array_keys($days));
        foreach ($days as $ymd => $obs) {
            self::assertSame('pentecost', $obs->season()->value(), $ymd);
            self::assertSame('green', $obs->colour()->base()->value(), $ymd);
        }
    }

    /**
     * @return array<string, array{int}>
     */
    public function sampleYears(): array
    {
        return ['2035 (long)' => [2035], '2038 (short)' => [2038], '1962' => [1962]];
    }

    /**
     * The count of Sundays after Pentecost tracks the date of Easter — 24 to 28.
     *
     * @dataProvider sundayCounts
     */
    public function testSundaysAfterPentecostAreCountedCorrectly(
        int $year,
        int $expectedCount,
        int $expectedResumed
    ): void {
        $season = TimeAfterPentecost::forYear($year);

        self::assertSame($expectedCount, $season->sundaysAfterPentecost());
        self::assertSame($expectedResumed, $season->resumedSundays());
    }

    /**
     * @return array<string, array{int, int, int}>
     */
    public function sundayCounts(): array
    {
        return [
            '2035 long: 28 Sundays, 4 resumed' => [2035, 28, 4],
            '2038 short: 23 Sundays, none resumed' => [2038, 23, 0],
        ];
    }

    /**
     * The sweep confirms the count never leaves 23–28.
     */
    public function testSundayCountAlwaysBetween23And28(): void
    {
        for ($year = 1583; $year <= 2200; $year++) {
            $count = TimeAfterPentecost::forYear($year)->sundaysAfterPentecost();
            self::assertGreaterThanOrEqual(23, $count, "year $year");
            self::assertLessThanOrEqual(28, $count, "year $year");
        }
    }

    /**
     * The last Sunday before Advent always takes the "24th and last" Mass,
     * whatever the year's length.
     */
    public function testLastSundayIsAlwaysTheUltima(): void
    {
        $long = TimeAfterPentecost::forYear(2035);
        self::assertSame(
            'roman:temporale:paschal:pentecost-time:sunday-ultima',
            $long->on(self::utc('2035-11-25'))->id()->toString()
        );

        $short = TimeAfterPentecost::forYear(2038);
        self::assertSame(
            'roman:temporale:paschal:pentecost-time:sunday-ultima',
            $short->on(self::utc('2038-11-21'))->id()->toString()
        );
    }

    /**
     * In a long year the crowded-out Sundays after Epiphany are resumed before the
     * last Sunday, ending with the VIth immediately before the ultima — the
     * acceptance criterion.
     */
    public function testLeftoverEpiphanySundaysAreResumedBeforeTheLast(): void
    {
        $season = TimeAfterPentecost::forYear(2035); // 4 resumed: Epiphany III, IV, V, VI

        $resumed = [
            '2035-10-28' => ['paschal:pentecost-time:resumed-epiphany-3',
                'Dominica III quae superfuit post Epiphaniam'],
            '2035-11-04' => ['paschal:pentecost-time:resumed-epiphany-4',
                'Dominica IV quae superfuit post Epiphaniam'],
            '2035-11-11' => ['paschal:pentecost-time:resumed-epiphany-5',
                'Dominica V quae superfuit post Epiphaniam'],
            '2035-11-18' => ['paschal:pentecost-time:resumed-epiphany-6',
                'Dominica VI quae superfuit post Epiphaniam'],
        ];
        foreach ($resumed as $date => [$id, $latin]) {
            $obs = $season->on(self::utc($date));
            self::assertNotNull($obs, $date);
            self::assertSame('roman:temporale:' . $id, $obs->id()->toString(), "$date id");
            self::assertSame($latin, $obs->latinName(), "$date latin");
            self::assertSame('II', $obs->rank()->label(), "$date rank");
        }
    }

    /**
     * @dataProvider sampleDays
     */
    public function testDayRealization(string $date, string $id, string $kind, string $rank, string $latin): void
    {
        $obs = TimeAfterPentecost::forYear(2035)->on(self::utc($date));
        self::assertNotNull($obs, $date);

        self::assertSame('roman:temporale:' . $id, $obs->id()->toString(), "$date id");
        self::assertSame($kind, $obs->kind()->value(), "$date kind");
        self::assertSame($rank, $obs->rank()->label(), "$date rank");
        self::assertSame('green', $obs->colour()->base()->value(), "$date colour");
        self::assertSame($latin, $obs->latinName(), "$date latin");
    }

    /**
     * The id column omits the constant `roman:temporale:` prefix.
     *
     * @return array<string, array{string, string, string, string, string}>
     */
    public function sampleDays(): array
    {
        return [
            'First Sunday after Pentecost (Trinity slot)' =>
                ['2035-05-20', 'paschal:pentecost-time:sunday-1', 'sunday', 'II', 'Dominica I post Pentecosten'],
            'Feria of the first week' => [
                '2035-05-21', 'paschal:pentecost-time:week-1:feria-2', 'feria', 'IV',
                'Feria II infra Hebdomadam I post Octavam Pentecostes',
            ],
            'Twenty-third Sunday after Pentecost' =>
                ['2035-10-21', 'paschal:pentecost-time:sunday-23', 'sunday', 'II', 'Dominica XXIII post Pentecosten'],
            'Feria of a high-numbered week' => [
                '2035-11-19', 'paschal:pentecost-time:week-27:feria-2', 'feria', 'IV',
                'Feria II infra Hebdomadam XXVII post Octavam Pentecostes',
            ],
        ];
    }

    /**
     * Every numbered Sunday (2nd … 23rd, the first is the Trinity slot) renders its
     * own "Dominica N post Pentecosten".
     */
    public function testNumberedSundaysRenderContinuously(): void
    {
        $season = TimeAfterPentecost::forYear(2035);
        $cursor = $season->trinitySunday();
        for ($n = 1; $n <= 23; $n++) {
            $obs = $season->on($cursor);
            self::assertNotNull($obs, $cursor->format('Y-m-d'));
            self::assertSame('sunday', $obs->kind()->value());
            self::assertSame("Dominica " . self::roman($n) . " post Pentecosten", $obs->latinName());
            $cursor = $cursor->add(new DateInterval('P7D'));
        }
    }

    public function testOnReturnsNullOutsideTheBlock(): void
    {
        $season = TimeAfterPentecost::forYear(2035);

        self::assertNotNull($season->on(self::utc('2035-05-20')));
        self::assertNull($season->on(self::utc('2035-05-19')), 'Pentecost octave belongs to #20');
        self::assertNull($season->on(self::utc('2035-12-02')), 'First Sunday of Advent is the exclusive boundary');
    }

    public function testPreGregorianYearIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TimeAfterPentecost::forYear(1582);
    }

    private static function roman(int $n): string
    {
        $map = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
            9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII', 13 => 'XIII', 14 => 'XIV', 15 => 'XV', 16 => 'XVI',
            17 => 'XVII', 18 => 'XVIII', 19 => 'XIX', 20 => 'XX', 21 => 'XXI', 22 => 'XXII', 23 => 'XXIII'];

        return $map[$n];
    }

    private static function utc(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd . ' 00:00:00', new DateTimeZone('UTC'));
    }
}
