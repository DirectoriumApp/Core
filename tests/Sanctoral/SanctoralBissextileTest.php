<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Sanctoral;

use DateTimeImmutable;
use Introibo\Core\Attribute\Colour;
use Introibo\Core\Attribute\ElementColour;
use Introibo\Core\Attribute\RankClass;
use Introibo\Core\Observance\Observance;
use Introibo\Core\Observance\ObservanceId;
use Introibo\Core\Observance\ObservanceKind;
use Introibo\Core\Sanctoral\SanctoralCalendar;
use Introibo\Core\Sanctoral\SanctoralData;
use Introibo\Core\Sanctoral\SanctoralEntry;
use PHPUnit\Framework\TestCase;

/**
 * Leap-year bissextile placement (#333). In a leap year the sixth day before the
 * Kalends of March is doubled, so every feast on 24–28 February is kept one day
 * later: St Matthias 24 Feb → 25 Feb, St Gabriel 27 Feb → 28 Feb, a 28 Feb feast
 * → 29 Feb.
 */
final class SanctoralBissextileTest extends TestCase
{
    public function testStMatthiasMovesToFeb25InLeapYears(): void
    {
        self::assertSame('2024-02-25', self::dateOf(SanctoralCalendar::forYear(2024), 'matthias'));
        self::assertSame('2025-02-24', self::dateOf(SanctoralCalendar::forYear(2025), 'matthias'));
    }

    public function testStGabrielMovesToFeb28InLeapYears(): void
    {
        $id = 'gabriel-a-virgine-perdolente';

        self::assertSame('2024-02-28', self::dateOf(SanctoralCalendar::forYear(2024), $id));
        self::assertSame('2025-02-27', self::dateOf(SanctoralCalendar::forYear(2025), $id));
    }

    public function testTheWholeFebruary24To28TailShiftsInLeapYears(): void
    {
        $source = self::sourceOnDays([24, 25, 26, 27, 28]);

        self::assertSame(
            ['2024-02-25', '2024-02-26', '2024-02-27', '2024-02-28', '2024-02-29'],
            array_keys(SanctoralCalendar::forYear(2024, $source)->all()),
            'leap year: 24–28 Feb kept one day later, filling 29 Feb'
        );
        self::assertSame(
            ['2025-02-24', '2025-02-25', '2025-02-26', '2025-02-27', '2025-02-28'],
            array_keys(SanctoralCalendar::forYear(2025, $source)->all()),
            'common year: unshifted'
        );
    }

    public function testFebruary23IsNotShifted(): void
    {
        $source = self::sourceOnDays([23]);

        self::assertSame(['2024-02-23'], array_keys(SanctoralCalendar::forYear(2024, $source)->all()));
    }

    /**
     * Across every Gregorian year the shift never collides — each realized date
     * still holds exactly one office — and St Matthias lands on its
     * leap-dependent day.
     */
    public function testBissextileInvariantsHold(): void
    {
        for ($year = 1583; $year <= 2200; $year++) {
            $calendar = SanctoralCalendar::forYear($year);

            foreach ($calendar->all() as $ymd => $offices) {
                self::assertCount(1, $offices, "one office per realized date ($ymd)");
            }

            $leap = (new DateTimeImmutable($year . '-01-01'))->format('L') === '1';
            self::assertSame(
                $leap ? "$year-02-25" : "$year-02-24",
                self::dateOf($calendar, 'matthias'),
                "St Matthias $year"
            );
        }
    }

    private static function dateOf(SanctoralCalendar $calendar, string $subjectSlug): ?string
    {
        $id = 'roman:sanctorale:' . $subjectSlug;
        foreach ($calendar->all() as $ymd => $offices) {
            foreach ($offices as $office) {
                if ($office->id()->toString() === $id) {
                    return $ymd;
                }
            }
        }

        return null;
    }

    /**
     * @param list<int> $days
     */
    private static function sourceOnDays(array $days): SanctoralData
    {
        $entries = [];
        foreach ($days as $day) {
            $entries[] = new SanctoralEntry(
                2,
                $day,
                new Observance(
                    ObservanceId::parse('roman:sanctorale:test-feb-' . $day),
                    ObservanceKind::fromString(ObservanceKind::FEAST),
                    ['test-feb-' . $day],
                    ['la' => 'S. Testis in Februario']
                ),
                RankClass::classIII(),
                ElementColour::of(Colour::white())
            );
        }

        return new class ($entries) implements SanctoralData {
            /** @var list<SanctoralEntry> */
            private array $entries;

            /** @param list<SanctoralEntry> $entries */
            public function __construct(array $entries)
            {
                $this->entries = $entries;
            }

            /** @return list<SanctoralEntry> */
            public function entries(): array
            {
                return $this->entries;
            }
        };
    }
}
