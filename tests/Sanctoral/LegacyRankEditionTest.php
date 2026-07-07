<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Sanctoral;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Sanctoral\CorpusSanctoralData;
use Directorium\Core\Sanctoral\SanctoralCalendar;
use Directorium\Core\Sanctoral\SanctoralEntry;
use Directorium\Core\Sanctoral\SanctoralObservance;
use PHPUnit\Framework\TestCase;

/**
 * The 1954 (Divino Afflatu) edition foundation (#64): the generator materialises the
 * edition as a diff from the 1962 base into its own corpus directory, and the sanctoral
 * loader carries each entry's native pre-1960 grade token ({@see LegacyRank}) alongside
 * the normalized {@see RankClass}, all the way through to the realized observance. The
 * 1960 edition carries no legacy grade, so its behaviour — and the golden fixture — are
 * untouched.
 */
final class LegacyRankEditionTest extends TestCase
{
    private const DIVINO_AFFLATU = 'roman-divino-afflatu';

    public function testTheDivinoAfflatuEditionMaterialisesAsItsOwnCorpusDirectory(): void
    {
        $entries = (new CorpusSanctoralData(null, self::DIVINO_AFFLATU))->entries();

        // The full 1954 sanctoral: 290 feasts of the 1962 base re-graded into the pre-1955
        // double scheme (#64); 25 general-calendar feasts the 1955/1960 reforms SUPPRESSED,
        // authored as notInBaseEdition; the materialised sanctoral octaves (#65/#453 — 38 octave
        // observances, the comites-Christi/All-Saints/Immaculate-Conception octave DAYS added
        // with #453); the pre-1955 vigils (#66 — 13); and the Commemoration of All Souls
        // (#453 — the Office of the Dead, +1). Every one carries a legacy grade.
        self::assertCount(290 + 25 + 38 + 13 + 1, $entries);
        foreach ($entries as $entry) {
            self::assertNotNull(
                $entry->legacyRank(),
                sprintf('Every 1954 entry carries a legacy grade; %s did not.', $entry->identity()->id()->toString())
            );
        }
    }

    public function testEachEntryCarriesItsNativeGradeAndItsNormalizedClass(): void
    {
        $entries = (new CorpusSanctoralData(null, self::DIVINO_AFFLATU))->entries();

        $assumption = $this->entryFor($entries, 'roman:sanctorale:assumptio');
        self::assertSame('duplex-i-classis', $assumption->legacyRank()->value());
        self::assertSame(1, $assumption->rank()->ordinal());

        $nativityBvm = $this->entryFor($entries, 'roman:sanctorale:nativitas-mariae');
        self::assertSame('duplex-ii-classis', $nativityBvm->legacyRank()->value());
        self::assertSame(2, $nativityBvm->rank()->ordinal());
    }

    public function testTheNineteenSixtyEditionCarriesNoLegacyGrade(): void
    {
        $entries = (new CorpusSanctoralData())->entries();

        $assumption = $this->entryFor($entries, 'roman:sanctorale:assumptio');
        self::assertNull($assumption->legacyRank());
        self::assertSame(1, $assumption->rank()->ordinal());
    }

    public function testTheLegacyGradeThreadsThroughToTheRealizedObservance(): void
    {
        $calendar = SanctoralCalendar::forYear(1954, new CorpusSanctoralData(null, self::DIVINO_AFFLATU));

        $offices = $calendar->on(new DateTimeImmutable('1954-08-15', new DateTimeZone('UTC')));
        $assumption = $this->observanceFor($offices, 'roman:sanctorale:assumptio');

        self::assertInstanceOf(SanctoralObservance::class, $assumption);
        self::assertSame('duplex-i-classis', $assumption->legacyRank()->value());
        self::assertSame(1, $assumption->rank()->ordinal());
    }

    /**
     * @param list<SanctoralEntry> $entries
     */
    private function entryFor(array $entries, string $id): SanctoralEntry
    {
        foreach ($entries as $entry) {
            if ($entry->identity()->id()->toString() === $id) {
                return $entry;
            }
        }

        self::fail(sprintf('No 1954 entry for "%s".', $id));
    }

    /**
     * @param list<SanctoralObservance> $offices
     */
    private function observanceFor(array $offices, string $id): SanctoralObservance
    {
        foreach ($offices as $office) {
            if ($office->id()->toString() === $id) {
                return $office;
            }
        }

        self::fail(sprintf('No 1954 office for "%s".', $id));
    }
}
