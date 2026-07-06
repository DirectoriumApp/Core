<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Temporal;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Temporal\Eastertide;
use Directorium\Core\Temporal\PaschalSkeleton;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EastertideTest extends TestCase
{
    public function testAnchorsMatchThePaschalSkeleton(): void
    {
        $tide = Eastertide::forYear(2025);
        $skeleton = PaschalSkeleton::forYear(2025);

        self::assertSame('2025-04-20', $tide->easter()->format('Y-m-d'));
        self::assertEquals($skeleton->ascension(), $tide->ascension());
        self::assertEquals($skeleton->pentecost(), $tide->pentecost());
        // Ends before Trinity Sunday (Easter+56), handed to the Time after Pentecost (#21).
        self::assertEquals($skeleton->date('trinity-sunday'), $tide->endsBefore());
    }

    /**
     * Easter → Trinity Sunday is always Easter+0 … Easter+55 — a fixed 56-day
     * span, filled contiguously, and the whole of it is Eastertide.
     *
     * @dataProvider sampleYears
     */
    public function testBlockIsFiftySixContiguousEastertideDays(int $year): void
    {
        $tide = Eastertide::forYear($year);
        $days = $tide->days();

        $expected = [];
        $cursor = $tide->easter();
        while ($cursor < $tide->endsBefore()) {
            $expected[] = $cursor->format('Y-m-d');
            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        self::assertCount(56, $days);
        self::assertSame($expected, array_keys($days));

        foreach ($days as $ymd => $obs) {
            self::assertSame('eastertide', $obs->season()->value(), $ymd);
        }
    }

    /**
     * @return array<string, array{int}>
     */
    public function sampleYears(): array
    {
        return ['1962' => [1962], '2024 (leap)' => [2024], '2025' => [2025]];
    }

    /**
     * The Easter octave — Easter Sunday through Low Sunday inclusive — is entirely
     * first class (the acceptance criterion), and white.
     */
    public function testEasterOctaveIsAllFirstClassWhite(): void
    {
        $tide = Eastertide::forYear(2025);
        $cursor = $tide->easter();
        for ($i = 0; $i <= 7; $i++) {
            $obs = $tide->on($cursor);
            self::assertNotNull($obs, $cursor->format('Y-m-d'));
            self::assertSame('I', $obs->rank()->label(), $cursor->format('Y-m-d'));
            self::assertSame('white', $obs->colour()->base()->value(), $cursor->format('Y-m-d'));
            $cursor = $cursor->add(new DateInterval('P1D'));
        }
    }

    /**
     * The Octave of Pentecost is filled per the 1960 rubrics: every day first
     * class and red (the Whit Ember days keep red — the joyful fast).
     */
    public function testPentecostOctaveIsAllFirstClassRed(): void
    {
        $tide = Eastertide::forYear(2025);
        $cursor = $tide->pentecost();
        for ($i = 0; $i <= 6; $i++) {
            $obs = $tide->on($cursor);
            self::assertNotNull($obs, $cursor->format('Y-m-d'));
            self::assertSame('I', $obs->rank()->label(), $cursor->format('Y-m-d'));
            self::assertSame('red', $obs->colour()->base()->value(), $cursor->format('Y-m-d'));
            $cursor = $cursor->add(new DateInterval('P1D'));
        }
    }

    /**
     * @dataProvider sampleDays
     */
    public function testDayRealization(string $date, string $id, string $kind, string $rank, string $colour): void
    {
        $obs = Eastertide::forYear(2025)->on(self::utc($date));
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
            'Easter Sunday' =>
                ['2025-04-20', 'paschal:easter', 'sunday', 'I', 'white'],
            'Easter Monday (within octave)' =>
                ['2025-04-21', 'paschal:easter-octave:feria-2', 'within-octave', 'I', 'white'],
            'Saturday in Albis' =>
                ['2025-04-26', 'paschal:easter-octave:sabbatum', 'within-octave', 'I', 'white'],
            'Low Sunday' =>
                ['2025-04-27', 'paschal:low-sunday', 'sunday', 'I', 'white'],
            'Paschaltide feria' =>
                ['2025-04-28', 'paschal:paschaltide:week-1:feria-2', 'feria', 'IV', 'white'],
            'Second Sunday after Easter' =>
                ['2025-05-04', 'paschal:paschaltide:sunday-2', 'sunday', 'II', 'white'],
            'Rogation Monday' =>
                ['2025-05-26', 'paschal:rogation:feria-2', 'rogation-day', 'IV', 'violet'],
            'Ascension' =>
                ['2025-05-29', 'paschal:ascension', 'feast', 'I', 'white'],
            'Sunday after Ascension' =>
                ['2025-06-01', 'paschal:sunday-after-ascension', 'sunday', 'II', 'white'],
            'Vigil of Pentecost' =>
                ['2025-06-07', 'paschal:pentecost-vigil', 'vigil', 'I', 'red'],
            'Pentecost' =>
                ['2025-06-08', 'paschal:pentecost', 'sunday', 'I', 'red'],
            'Whit Monday (within octave)' =>
                ['2025-06-09', 'paschal:pentecost-octave:feria-2', 'within-octave', 'I', 'red'],
            'Whit Ember Wednesday' =>
                ['2025-06-11', 'paschal:pentecost-octave:quattuor-temporum:feria-4', 'ember-day', 'I', 'red'],
        ];
    }

    /**
     * @dataProvider latinNames
     */
    public function testLatinNames(string $date, string $expected): void
    {
        $obs = Eastertide::forYear(2025)->on(self::utc($date));
        self::assertNotNull($obs, $date);
        self::assertSame($expected, $obs->latinName());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function latinNames(): array
    {
        return [
            'Easter' => ['2025-04-20', 'Dominica Resurrectionis'],
            'Saturday in Albis' => ['2025-04-26', 'Sabbato in Albis'],
            'Low Sunday' => ['2025-04-27', 'Dominica in Albis'],
            'Paschaltide feria' => ['2025-04-28', 'Feria II infra Hebdomadam I post Octavam Paschae'],
            'Second Sunday after Easter' => ['2025-05-04', 'Dominica II post Pascha'],
            'Rogation Monday' => ['2025-05-26', 'Feria II in Rogationibus'],
            'Ascension' => ['2025-05-29', 'In Ascensione Domini'],
            'Feria after Ascension (before the Sunday)' => ['2025-05-30', 'Feria VI post Ascensionem'],
            'Feria after the Sunday after Ascension' => ['2025-06-02', 'Feria II post Ascensionem'],
            'Vigil of Pentecost' => ['2025-06-07', 'Sabbato in Vigilia Pentecostes'],
            'Pentecost' => ['2025-06-08', 'Dominica Pentecostes'],
            'Whit Ember Wednesday' => ['2025-06-11', 'Feria IV Quatuor Temporum Pentecostes'],
        ];
    }

    public function testIdentitiesAreUniqueWithinACycle(): void
    {
        foreach ([1962, 2024, 2025] as $year) {
            $ids = [];
            foreach (Eastertide::forYear($year)->days() as $obs) {
                $ids[] = $obs->id()->toString();
            }
            self::assertSame(array_values(array_unique($ids)), $ids, "duplicate id in $year");
        }
    }

    public function testOnReturnsNullOutsideTheBlock(): void
    {
        $tide = Eastertide::forYear(2025);

        self::assertNotNull($tide->on(self::utc('2025-04-20')));
        self::assertNull($tide->on(self::utc('2025-04-19')), 'Holy Saturday belongs to #19');
        self::assertNull($tide->on(self::utc('2025-06-15')), 'Trinity Sunday is the exclusive boundary');
    }

    public function testPreGregorianYearIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Eastertide::forYear(1582);
    }

    private static function utc(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd . ' 00:00:00', new DateTimeZone('UTC'));
    }
}
