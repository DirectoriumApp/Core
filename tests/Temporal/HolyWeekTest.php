<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Temporal;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Temporal\HolyWeek;
use Directorium\Core\Temporal\PaschalSkeleton;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HolyWeekTest extends TestCase
{
    public function testAnchorsMatchThePaschalSkeleton(): void
    {
        $week = HolyWeek::forYear(2025);
        $skeleton = PaschalSkeleton::forYear(2025);

        self::assertSame('2025-04-20', $week->easter()->format('Y-m-d'));
        self::assertEquals($skeleton->date('palm-sunday'), $week->palmSunday());
        self::assertEquals($skeleton->date('maundy-thursday'), $week->maundyThursday());
        self::assertEquals($skeleton->date('good-friday'), $week->goodFriday());
        self::assertEquals($skeleton->date('holy-saturday'), $week->holySaturday());
    }

    /**
     * Palm Sunday → Holy Saturday is always the seven days Easter−7 … Easter−1,
     * filled contiguously and ending before Easter (handed to Eastertide, #20).
     *
     * @dataProvider sampleYears
     */
    public function testBlockIsSevenContiguousDaysEndingBeforeEaster(int $year): void
    {
        $week = HolyWeek::forYear($year);
        $days = $week->days();

        $expected = [];
        $cursor = $week->palmSunday();
        while ($cursor < $week->easter()) {
            $expected[] = $cursor->format('Y-m-d');
            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        self::assertCount(7, $days);
        self::assertSame($expected, array_keys($days));
    }

    /**
     * @return array<string, array{int}>
     */
    public function sampleYears(): array
    {
        return ['1962' => [1962], '2024 (leap)' => [2024], '2025' => [2025]];
    }

    /**
     * No day of Holy Week is anything but first class, and the whole block is
     * Passiontide — the guarantee that no sanctoral feast may displace it.
     */
    public function testEveryDayIsFirstClassPassiontide(): void
    {
        foreach (HolyWeek::forYear(2025)->days() as $ymd => $obs) {
            self::assertSame('I', $obs->rank()->label(), $ymd);
            self::assertSame('passiontide', $obs->season()->value(), $ymd);
        }
    }

    public function testTriduumIsTheLastThreeDays(): void
    {
        $week = HolyWeek::forYear(2025);

        self::assertSame(
            ['2025-04-17', '2025-04-18', '2025-04-19'],
            array_map(static fn (DateTimeImmutable $d): string => $d->format('Y-m-d'), $week->triduum())
        );

        self::assertTrue($week->isTriduum(self::utc('2025-04-17')), 'Holy Thursday');
        self::assertTrue($week->isTriduum(self::utc('2025-04-18')), 'Good Friday');
        self::assertTrue($week->isTriduum(self::utc('2025-04-19')), 'Holy Saturday');
        self::assertFalse($week->isTriduum(self::utc('2025-04-13')), 'Palm Sunday');
        self::assertFalse($week->isTriduum(self::utc('2025-04-16')), 'Wednesday of Holy Week');
    }

    /**
     * The colour transitions the acceptance criteria call out, in order:
     * violet (Palm Sunday and the first ferias) → white (Maundy Thursday) →
     * black (Good Friday) → violet (Holy Saturday).
     */
    public function testColourTransitions(): void
    {
        $colours = [];
        foreach (HolyWeek::forYear(2025)->days() as $obs) {
            $colours[] = $obs->colour()->base()->value();
        }

        self::assertSame(
            ['violet', 'violet', 'violet', 'violet', 'white', 'black', 'violet'],
            $colours
        );
    }

    /**
     * Under the 1962 rubrics Good Friday is BLACK (turning violet only for
     * Communion); the red of the modern rite is not the 1962 colour.
     */
    public function testGoodFridayIsBlack(): void
    {
        $obs = HolyWeek::forYear(2025)->on(self::utc('2025-04-18'));
        self::assertNotNull($obs);
        self::assertSame('black', $obs->colour()->base()->value());
    }

    /**
     * @dataProvider sampleDays
     */
    public function testDayRealization(string $date, string $id, string $kind, string $rank, string $colour): void
    {
        $obs = HolyWeek::forYear(2025)->on(self::utc($date));
        self::assertNotNull($obs, $date);

        self::assertSame('roman:temporale:' . $id, $obs->id()->toString(), "$date id");
        self::assertSame($kind, $obs->kind()->value(), "$date kind");
        self::assertSame($rank, $obs->rank()->label(), "$date rank");
        self::assertSame($colour, $obs->colour()->base()->value(), "$date colour");
    }

    /**
     * The id column omits the constant `roman:temporale:` prefix.
     *
     * @return array<string, array{string, string, string, string, string}>
     */
    public function sampleDays(): array
    {
        return [
            'Palm Sunday' => ['2025-04-13', 'paschal:palm-sunday', 'sunday', 'I', 'violet'],
            'Monday of Holy Week' => ['2025-04-14', 'paschal:holy-week:feria-2', 'feria', 'I', 'violet'],
            'Tuesday of Holy Week' => ['2025-04-15', 'paschal:holy-week:feria-3', 'feria', 'I', 'violet'],
            'Wednesday of Holy Week' => ['2025-04-16', 'paschal:holy-week:feria-4', 'feria', 'I', 'violet'],
            'Maundy Thursday' => ['2025-04-17', 'paschal:maundy-thursday', 'feria', 'I', 'white'],
            'Good Friday' => ['2025-04-18', 'paschal:good-friday', 'feria', 'I', 'black'],
            'Holy Saturday' => ['2025-04-19', 'paschal:holy-saturday', 'feria', 'I', 'violet'],
        ];
    }

    /**
     * @dataProvider latinNames
     */
    public function testLatinNames(string $date, string $expected): void
    {
        $obs = HolyWeek::forYear(2025)->on(self::utc($date));
        self::assertNotNull($obs, $date);
        self::assertSame($expected, $obs->latinName());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function latinNames(): array
    {
        return [
            'Palm Sunday' => ['2025-04-13', 'Dominica II Passionis seu in Palmis'],
            'Monday' => ['2025-04-14', 'Feria II Majoris Hebdomadae'],
            'Wednesday' => ['2025-04-16', 'Feria IV Majoris Hebdomadae'],
            'Maundy Thursday' => ['2025-04-17', 'Feria V in Cena Domini'],
            'Good Friday' => ['2025-04-18', 'Feria VI in Passione et Morte Domini'],
            'Holy Saturday' => ['2025-04-19', 'Sabbato Sancto'],
        ];
    }

    public function testOnReturnsNullOutsideTheBlock(): void
    {
        $week = HolyWeek::forYear(2025);

        self::assertNotNull($week->on(self::utc('2025-04-13')));
        self::assertNull($week->on(self::utc('2025-04-12')), 'Saturday before Palm Sunday (belongs to #18)');
        self::assertNull($week->on(self::utc('2025-04-20')), 'Easter is the exclusive boundary');
    }

    public function testPreGregorianYearIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HolyWeek::forYear(1582);
    }

    private static function utc(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd . ' 00:00:00', new DateTimeZone('UTC'));
    }
}
