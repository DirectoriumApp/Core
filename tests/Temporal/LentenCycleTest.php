<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Temporal;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Introibo\Core\Temporal\LentenCycle;
use Introibo\Core\Temporal\PaschalSkeleton;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LentenCycleTest extends TestCase
{
    public function testAnchorsMatchThePaschalSkeleton(): void
    {
        $cycle = LentenCycle::forYear(2025);
        $skeleton = PaschalSkeleton::forYear(2025);

        self::assertSame('2025-04-20', $cycle->easter()->format('Y-m-d'));
        self::assertEquals($skeleton->septuagesima(), $cycle->septuagesima());
        self::assertEquals($skeleton->ashWednesday(), $cycle->ashWednesday());
        self::assertEquals($skeleton->date('passion-sunday'), $cycle->passionSunday());
        // The exclusive end boundary is Palm Sunday, handed to Holy Week (#19).
        self::assertEquals($skeleton->date('palm-sunday'), $cycle->endsBefore());
    }

    /**
     * Septuagesima → Palm Sunday is always Easter−63 to Easter−7 — a fixed 56-day
     * span, filled contiguously with one office per day.
     *
     * @dataProvider sampleYears
     */
    public function testBlockIsContiguousAndFiftySixDays(int $year): void
    {
        $cycle = LentenCycle::forYear($year);
        $days = $cycle->days();

        $expected = [];
        $cursor = $cycle->septuagesima();
        while ($cursor < $cycle->endsBefore()) {
            $expected[] = $cursor->format('Y-m-d');
            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        self::assertCount(56, $days);
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
     * Seasons advance Septuagesima → Lent → Passiontide and never regress.
     */
    public function testSeasonsTransitionInLiturgicalOrder(): void
    {
        $seen = [];
        foreach (LentenCycle::forYear(2025)->days() as $obs) {
            $season = $obs->season()->value();
            if ($seen === [] || end($seen) !== $season) {
                $seen[] = $season;
            }
        }

        self::assertSame(['septuagesima', 'lent', 'passiontide'], $seen);
    }

    /**
     * The whole block is violet.
     */
    public function testEveryDayIsViolet(): void
    {
        foreach (LentenCycle::forYear(2025)->days() as $ymd => $obs) {
            self::assertSame('violet', $obs->colour()->base()->value(), $ymd);
        }
    }

    /**
     * Identity, kind, season, class and colour across the block, under the 1960
     * rubrics (the raised Lenten ferial ranks the issue calls out).
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
        $obs = LentenCycle::forYear(2025)->on(self::utc($date));
        self::assertNotNull($obs, $date);

        self::assertSame($id, $obs->id()->toString(), "$date id");
        self::assertSame($kind, $obs->kind()->value(), "$date kind");
        self::assertSame($season, $obs->season()->value(), "$date season");
        self::assertSame($rank, $obs->rank()->label(), "$date rank");
        self::assertSame($colour, $obs->colour()->base()->value(), "$date colour");
        self::assertSame($rose, $obs->colour()->roseAllowed(), "$date rose");
    }

    /**
     * @return array<string, array{string, string, string, string, string, string, bool}>
     */
    public function sampleDays(): array
    {
        return [
            'Septuagesima Sunday — second class' => [
                '2025-02-16', 'roman:temporale:paschal:septuagesima',
                'sunday', 'septuagesima', 'II', 'violet', false,
            ],
            'Pre-Lent feria — fourth class' => [
                '2025-02-17', 'roman:temporale:paschal:septuagesima:feria-2',
                'feria', 'septuagesima', 'IV', 'violet', false,
            ],
            'Sexagesima Sunday' => [
                '2025-02-23', 'roman:temporale:paschal:sexagesima',
                'sunday', 'septuagesima', 'II', 'violet', false,
            ],
            'Quinquagesima Sunday' => [
                '2025-03-02', 'roman:temporale:paschal:quinquagesima',
                'sunday', 'septuagesima', 'II', 'violet', false,
            ],
            'Ash Wednesday — first-class feria' => [
                '2025-03-05', 'roman:temporale:paschal:ash-wednesday',
                'feria', 'lent', 'I', 'violet', false,
            ],
            'Feria after Ash Wednesday — third class' => [
                '2025-03-06', 'roman:temporale:paschal:post-cineres:feria-5',
                'feria', 'lent', 'III', 'violet', false,
            ],
            'First Sunday of Lent — first class' => [
                '2025-03-09', 'roman:temporale:paschal:lent-1',
                'sunday', 'lent', 'I', 'violet', false,
            ],
            'Lenten feria — third class' => [
                '2025-03-10', 'roman:temporale:paschal:lent-week-1:feria-2',
                'feria', 'lent', 'III', 'violet', false,
            ],
            'Ember Wednesday of Lent — second class' => [
                '2025-03-12', 'roman:temporale:paschal:quattuor-temporum-quadragesimae:feria-4',
                'ember-day', 'lent', 'II', 'violet', false,
            ],
            'Laetare — first class, rose permitted' => [
                '2025-03-30', 'roman:temporale:paschal:lent-4',
                'sunday', 'lent', 'I', 'violet', true,
            ],
            'Passion Sunday — first class, Passiontide' => [
                '2025-04-06', 'roman:temporale:paschal:passion-sunday',
                'sunday', 'passiontide', 'I', 'violet', false,
            ],
            'Passion-week feria — third class' => [
                '2025-04-07', 'roman:temporale:paschal:passion-week:feria-2',
                'feria', 'passiontide', 'III', 'violet', false,
            ],
        ];
    }

    /**
     * @dataProvider latinNames
     */
    public function testLatinNames(string $date, string $expected): void
    {
        $obs = LentenCycle::forYear(2025)->on(self::utc($date));
        self::assertNotNull($obs, $date);
        self::assertSame($expected, $obs->latinName());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function latinNames(): array
    {
        return [
            'Septuagesima' => ['2025-02-16', 'Dominica in Septuagesima'],
            'Pre-Lent feria' => ['2025-02-17', 'Feria II infra Hebdomadam Septuagesimae'],
            'Ash Wednesday' => ['2025-03-05', 'Feria IV Cinerum'],
            'After Ash Wednesday' => ['2025-03-06', 'Feria V post Cineres'],
            'First Sunday of Lent' => ['2025-03-09', 'Dominica I in Quadragesima'],
            'Lenten feria' => ['2025-03-10', 'Feria II infra Hebdomadam I in Quadragesima'],
            'Ember Wednesday' => ['2025-03-12', 'Feria IV Quatuor Temporum Quadragesimae'],
            'Laetare' => ['2025-03-30', 'Dominica IV in Quadragesima'],
            'Passion Sunday' => ['2025-04-06', 'Dominica I Passionis'],
            'Passion-week feria' => ['2025-04-07', 'Feria II infra Hebdomadam Passionis'],
            'Passion-week Saturday' => ['2025-04-12', 'Sabbato infra Hebdomadam Passionis'],
        ];
    }

    /**
     * Every observance in a cycle has a distinct identity — no two days collide.
     */
    public function testIdentitiesAreUniqueWithinACycle(): void
    {
        foreach ([1962, 2024, 2025] as $year) {
            $ids = [];
            foreach (LentenCycle::forYear($year)->days() as $obs) {
                $ids[] = $obs->id()->toString();
            }
            self::assertSame(array_values(array_unique($ids)), $ids, "duplicate id in $year");
        }
    }

    public function testOnReturnsNullOutsideTheBlock(): void
    {
        $cycle = LentenCycle::forYear(2025);

        self::assertNotNull($cycle->on(self::utc('2025-03-05')));
        self::assertNull($cycle->on(self::utc('2025-02-15')), 'day before Septuagesima');
        self::assertNull($cycle->on(self::utc('2025-04-13')), 'Palm Sunday is the exclusive boundary');
    }

    public function testPreGregorianYearIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LentenCycle::forYear(1582);
    }

    private static function utc(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd . ' 00:00:00', new DateTimeZone('UTC'));
    }
}
