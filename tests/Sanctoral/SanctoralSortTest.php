<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Sanctoral;

use DateTimeImmutable;
use DateTimeZone;
use Introibo\Core\Attribute\Colour;
use Introibo\Core\Attribute\ElementColour;
use Introibo\Core\Attribute\RankClass;
use Introibo\Core\Observance\Observance;
use Introibo\Core\Observance\ObservanceId;
use Introibo\Core\Observance\ObservanceKind;
use Introibo\Core\Sanctoral\SanctoralCalendar;
use Introibo\Core\Sanctoral\SanctoralData;
use Introibo\Core\Sanctoral\SanctoralEntry;
use Introibo\Core\Sanctoral\SanctoralObservance;
use PHPUnit\Framework\TestCase;

/**
 * The deterministic pre-sort of co-occurring sanctoral offices (#25): highest
 * rank first, tie-broken by canonical id. This orders candidates for the
 * resolver; it does not decide which office is celebrated (that is #29).
 */
final class SanctoralSortTest extends TestCase
{
    public function testTwoOfficesOnOneDateAreOrderedHighestRankFirst(): void
    {
        $source = self::fixedSource([
            self::entryOn(9, 16, 'lower', 3),
            self::entryOn(9, 16, 'higher', 1),
        ]);

        self::assertSame(['I', 'III'], self::ranksOn($source, '2025-09-16'));
    }

    public function testThreeOfficesOnOneDateAreOrderedByRank(): void
    {
        // Supplied out of order: III, I, II — must come back I, II, III.
        $source = self::fixedSource([
            self::entryOn(9, 16, 'cyprianus', 3),
            self::entryOn(9, 16, 'euphemia', 1),
            self::entryOn(9, 16, 'cornelius', 2),
        ]);

        self::assertSame(['I', 'II', 'III'], self::ranksOn($source, '2025-09-16'));
    }

    public function testSameRankTieIsBrokenByCanonicalId(): void
    {
        $source = self::fixedSource([
            self::entryOn(9, 16, 'zzz-ultima', 2),
            self::entryOn(9, 16, 'aaa-prima', 2),
            self::entryOn(9, 16, 'mmm-media', 2),
        ]);

        self::assertSame(
            [
                'roman:sanctorale:aaa-prima',
                'roman:sanctorale:mmm-media',
                'roman:sanctorale:zzz-ultima',
            ],
            self::idsOn($source, '2025-09-16')
        );
    }

    /** Rank dominates the tiebreak: a higher class sorts first regardless of id. */
    public function testRankDominatesTheIdTiebreak(): void
    {
        $source = self::fixedSource([
            self::entryOn(9, 16, 'aaa-but-lower', 3),
            self::entryOn(9, 16, 'zzz-but-higher', 1),
        ]);

        self::assertSame(
            ['roman:sanctorale:zzz-but-higher', 'roman:sanctorale:aaa-but-lower'],
            self::idsOn($source, '2025-09-16')
        );
    }

    public function testSortIsReproducibleRunToRun(): void
    {
        $entries = [
            self::entryOn(9, 16, 'cyprianus', 3),
            self::entryOn(9, 16, 'euphemia', 1),
            self::entryOn(9, 16, 'cornelius', 2),
        ];

        $first = self::idsOn(self::fixedSource($entries), '2025-09-16');
        $second = self::idsOn(self::fixedSource($entries), '2025-09-16');

        self::assertSame($first, $second);
    }

    /**
     * @return list<string>
     */
    private static function ranksOn(SanctoralData $source, string $date): array
    {
        return array_map(
            static fn (SanctoralObservance $o): string => $o->rank()->label(),
            SanctoralCalendar::forYear(2025, $source)->on(self::utc($date))
        );
    }

    /**
     * @return list<string>
     */
    private static function idsOn(SanctoralData $source, string $date): array
    {
        return array_map(
            static fn (SanctoralObservance $o): string => $o->id()->toString(),
            SanctoralCalendar::forYear(2025, $source)->on(self::utc($date))
        );
    }

    /**
     * @param list<SanctoralEntry> $entries
     */
    private static function fixedSource(array $entries): SanctoralData
    {
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

    private static function entryOn(int $month, int $day, string $slug, int $rankOrdinal): SanctoralEntry
    {
        return new SanctoralEntry(
            $month,
            $day,
            new Observance(
                ObservanceId::parse('roman:sanctorale:' . $slug),
                ObservanceKind::fromString(ObservanceKind::FEAST),
                [$slug],
                ['la' => 'S. Testis']
            ),
            RankClass::fromOrdinal($rankOrdinal),
            ElementColour::of(Colour::white())
        );
    }

    private static function utc(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd . ' 00:00:00', new DateTimeZone('UTC'));
    }
}
