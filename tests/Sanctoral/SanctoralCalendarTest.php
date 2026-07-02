<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Sanctoral;

use DateTimeImmutable;
use DateTimeZone;
use Introibo\Core\Attribute\Colour;
use Introibo\Core\Attribute\ElementColour;
use Introibo\Core\Attribute\RankClass;
use Introibo\Core\Calendar\RealizedObservance;
use Introibo\Core\Observance\Observance;
use Introibo\Core\Observance\ObservanceId;
use Introibo\Core\Observance\ObservanceKind;
use Introibo\Core\Sanctoral\SanctoralCalendar;
use Introibo\Core\Sanctoral\SanctoralData;
use Introibo\Core\Sanctoral\SanctoralEntry;
use Introibo\Core\Temporal\TemporalObservance;
use Introibo\Core\Tests\Fixture\SeedSanctoralData;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SanctoralCalendarTest extends TestCase
{
    public function testPlacesFeastsOnTheirCivilDates(): void
    {
        $calendar = SanctoralCalendar::forYear(2025, new SeedSanctoralData());

        self::assertSame(
            'roman:sanctorale:immaculata-conceptio',
            $this->soleOn($calendar, '2025-12-08')->id()->toString()
        );
        self::assertSame(
            'roman:sanctorale:stephanus',
            $this->soleOn($calendar, '2025-12-26')->id()->toString()
        );
        self::assertSame(
            'roman:sanctorale:petrus-paulus',
            $this->soleOn($calendar, '2025-06-29')->id()->toString()
        );
    }

    /** December is the representative month: it carries feasts of three classes. */
    public function testRepresentativeMonthIsFullyPlaced(): void
    {
        $calendar = SanctoralCalendar::forYear(2025, new SeedSanctoralData());

        $december = [
            '2025-12-08' => 'roman:sanctorale:immaculata-conceptio',
            '2025-12-26' => 'roman:sanctorale:stephanus',
            '2025-12-27' => 'roman:sanctorale:ioannes-evangelista',
            '2025-12-28' => 'roman:sanctorale:innocentes',
        ];
        foreach ($december as $date => $id) {
            self::assertSame($id, $this->soleOn($calendar, $date)->id()->toString(), $date);
        }
    }

    public function testEachPlacedOfficeCarriesRankColourIdAndName(): void
    {
        $office = $this->soleOn(SanctoralCalendar::forYear(2025, new SeedSanctoralData()), '2025-12-08');

        self::assertSame('roman:sanctorale:immaculata-conceptio', $office->id()->toString());
        self::assertSame('feast', $office->kind()->value());
        self::assertSame('I', $office->rank()->label());
        self::assertSame('white', $office->colour()->base()->value());
        self::assertSame('In Conceptione Immaculata B.M.V.', $office->latinName());
    }

    /**
     * A saint suppressed under the 1960 reform survives only as a commemoration:
     * the 1962 calendar has no IV-class saints' feast (IV class = ferias), so the
     * Four Crowned Martyrs are seeded as a commemoration, not a feast.
     */
    public function testASuppressedSaintIsSeededAsACommemoration(): void
    {
        $office = $this->soleOn(SanctoralCalendar::forYear(2025, new SeedSanctoralData()), '2025-11-08');

        self::assertSame('roman:sanctorale:quatuor-coronati', $office->id()->toString());
        self::assertSame('commemoration-only', $office->kind()->value());
        self::assertSame('IV', $office->rank()->label());
        self::assertSame('red', $office->colour()->base()->value());
    }

    public function testOnReturnsEmptyWhenNoFeastFalls(): void
    {
        self::assertSame([], SanctoralCalendar::forYear(2025, new SeedSanctoralData())->on(self::utc('2025-12-09')));
    }

    /** The seed spans every rank class, so the loader must realize all four. */
    public function testSeedExercisesAllFourRankClasses(): void
    {
        $labels = [];
        foreach (SanctoralCalendar::forYear(2025, new SeedSanctoralData())->all() as $offices) {
            foreach ($offices as $office) {
                $labels[$office->rank()->label()] = true;
            }
        }

        foreach (['I', 'II', 'III', 'IV'] as $class) {
            self::assertArrayHasKey($class, $labels, "rank $class present");
        }
    }

    public function testBothCyclesProduceRealizedObservances(): void
    {
        // The sanctoral side, at runtime…
        self::assertInstanceOf(
            RealizedObservance::class,
            $this->soleOn(SanctoralCalendar::forYear(2025, new SeedSanctoralData()), '2025-08-15')
        );
        // …and the temporal side, structurally: the seam both cycles share.
        self::assertContains(RealizedObservance::class, class_implements(TemporalObservance::class));
    }

    public function testFeastFixedToFebruary29IsPlacedOnlyInLeapYears(): void
    {
        $data = self::february29Source();

        self::assertSame([], SanctoralCalendar::forYear(2025, $data)->all(), 'common year: no 29 Feb feast');
        self::assertSame(
            ['2024-02-29'],
            array_keys(SanctoralCalendar::forYear(2024, $data)->all()),
            'leap year: the 29 Feb feast is placed'
        );
    }

    public function testDaysAreInChronologicalOrder(): void
    {
        $keys = array_keys(SanctoralCalendar::forYear(2025, new SeedSanctoralData())->all());
        $sorted = $keys;
        sort($sorted);

        self::assertSame($sorted, $keys);
    }

    public function testPreGregorianYearIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SanctoralCalendar::forYear(1582);
    }

    private function soleOn(SanctoralCalendar $calendar, string $ymd): \Introibo\Core\Sanctoral\SanctoralObservance
    {
        $offices = $calendar->on(self::utc($ymd));
        self::assertCount(1, $offices, $ymd);

        return $offices[0];
    }

    private static function february29Source(): SanctoralData
    {
        return new class implements SanctoralData {
            public function version(): string
            {
                return 'test-corpus';
            }

            /** @return list<SanctoralEntry> */
            public function entries(): array
            {
                return [
                    new SanctoralEntry(
                        2,
                        29,
                        new Observance(
                            ObservanceId::parse('roman:sanctorale:test-leap'),
                            ObservanceKind::fromString(ObservanceKind::FEAST),
                            ['test-leap'],
                            ['la' => 'Sancti Test in Bissexto']
                        ),
                        RankClass::classIII(),
                        ElementColour::of(Colour::white())
                    ),
                ];
            }
        };
    }

    private static function utc(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd . ' 00:00:00', new DateTimeZone('UTC'));
    }
}
