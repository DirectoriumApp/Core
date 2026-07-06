<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Overlay;

use Directorium\Core\Attribute\Colour;
use Directorium\Core\Attribute\ElementColour;
use Directorium\Core\Attribute\RankClass;
use Directorium\Core\Citation\CitationSet;
use Directorium\Core\Observance\Observance;
use Directorium\Core\Observance\ObservanceId;
use Directorium\Core\Observance\ObservanceKind;
use Directorium\Core\Overlay\AddOperation;
use Directorium\Core\Overlay\CalendarOverlay;
use Directorium\Core\Overlay\OverlaidSanctoralData;
use Directorium\Core\Overlay\OverlayConflict;
use Directorium\Core\Overlay\RerankOperation;
use Directorium\Core\Overlay\SuppressOperation;
use Directorium\Core\Sanctoral\SanctoralEntry;
use Directorium\Core\Tests\Fixture\SeedSanctoralData;
use PHPUnit\Framework\TestCase;

/**
 * The particular-calendar overlay applied to a base sanctoral (#77): a re-rank, an
 * add and a suppression layered over the known {@see SeedSanctoralData} slice, plus
 * the conflict rules that make a malformed overlay fail loudly.
 */
final class OverlaidSanctoralDataTest extends TestCase
{
    public function testAppliesRerankAddAndSuppressTogether(): void
    {
        $overlaid = new OverlaidSanctoralData(new SeedSanctoralData(), $this->sampleOverlay());
        $byId = self::indexByRank($overlaid->entries());

        // Re-rank: St Lawrence, second class in the seed, is elevated to first class.
        self::assertSame(1, $byId['roman:sanctorale:laurentius']);
        // Add: a proper feast the seed does not have appears at its rank.
        self::assertSame(1, $byId['roman:sanctorale:pius-x']);
        // Suppress: the removed feast is gone.
        self::assertArrayNotHasKey('roman:sanctorale:quatuor-coronati', $byId);

        // Net count: 21 seed entries − 1 suppressed + 1 added.
        self::assertCount(21, $overlaid->entries());
    }

    public function testRerankPreservesIdentityDateAndColourButReCitesTheRank(): void
    {
        $overlaid = new OverlaidSanctoralData(new SeedSanctoralData(), $this->sampleOverlay());
        $laurence = self::find($overlaid->entries(), 'roman:sanctorale:laurentius');

        self::assertSame(8, $laurence->month());
        self::assertSame(10, $laurence->day());
        self::assertSame('red', $laurence->colour()->base()->value());
        self::assertSame(1, $laurence->rank()->ordinal());

        $rankCitation = $laurence->citations()->for('rank');
        self::assertNotNull($rankCitation);
        self::assertSame('sspx-ordo', $rankCitation->sourceKey());
    }

    public function testVersionStampsTheOverlay(): void
    {
        $overlaid = new OverlaidSanctoralData(new SeedSanctoralData(), $this->sampleOverlay());

        self::assertSame('1962-seed-2026-07-02+directorium:overlay:roman:test', $overlaid->version());
    }

    public function testApplicationIsOrderIndependent(): void
    {
        $forward = new OverlaidSanctoralData(new SeedSanctoralData(), new CalendarOverlay(
            'directorium:overlay:roman:test',
            'Test',
            [$this->rerankLaurence(), $this->addPiusX(), $this->suppressCoronati()]
        ));
        $reversed = new OverlaidSanctoralData(new SeedSanctoralData(), new CalendarOverlay(
            'directorium:overlay:roman:test',
            'Test',
            [$this->suppressCoronati(), $this->addPiusX(), $this->rerankLaurence()]
        ));

        self::assertSame(self::indexByRank($forward->entries()), self::indexByRank($reversed->entries()));
    }

    public function testRerankingAFeastAbsentFromTheBaseIsAConflict(): void
    {
        $overlay = new CalendarOverlay('directorium:overlay:roman:test', 'Test', [
            new RerankOperation(ObservanceId::parse('roman:sanctorale:nonexistent'), RankClass::classI()),
        ]);

        $this->expectException(OverlayConflict::class);
        (new OverlaidSanctoralData(new SeedSanctoralData(), $overlay))->entries();
    }

    public function testAddingAFeastAlreadyInTheBaseIsAConflict(): void
    {
        $overlay = new CalendarOverlay('directorium:overlay:roman:test', 'Test', [
            new AddOperation($this->entry('laurentius', 8, 10, 1, 'red', 'S. Laurentii Martyris', ['laurentius'])),
        ]);

        $this->expectException(OverlayConflict::class);
        (new OverlaidSanctoralData(new SeedSanctoralData(), $overlay))->entries();
    }

    public function testSuppressingAFeastAbsentFromTheBaseIsAConflict(): void
    {
        $overlay = new CalendarOverlay('directorium:overlay:roman:test', 'Test', [
            new SuppressOperation(ObservanceId::parse('roman:sanctorale:nonexistent')),
        ]);

        $this->expectException(OverlayConflict::class);
        (new OverlaidSanctoralData(new SeedSanctoralData(), $overlay))->entries();
    }

    private function sampleOverlay(): CalendarOverlay
    {
        return new CalendarOverlay('directorium:overlay:roman:test', 'Test', [
            $this->rerankLaurence(),
            $this->addPiusX(),
            $this->suppressCoronati(),
        ]);
    }

    private function rerankLaurence(): RerankOperation
    {
        return new RerankOperation(
            ObservanceId::parse('roman:sanctorale:laurentius'),
            RankClass::classI(),
            null,
            CitationSet::fromMarkers(['rank' => 'sspx-ordo'])
        );
    }

    private function addPiusX(): AddOperation
    {
        return new AddOperation(
            $this->entry('pius-x', 9, 3, 1, 'white', 'S. Pii X Papae Confessoris', ['pius-x'])
        );
    }

    private function suppressCoronati(): SuppressOperation
    {
        return new SuppressOperation(ObservanceId::parse('roman:sanctorale:quatuor-coronati'));
    }

    /**
     * @param list<string> $titulars
     */
    private function entry(
        string $slug,
        int $month,
        int $day,
        int $rank,
        string $colour,
        string $latinName,
        array $titulars
    ): SanctoralEntry {
        return new SanctoralEntry(
            $month,
            $day,
            new Observance(
                ObservanceId::parse('roman:sanctorale:' . $slug),
                ObservanceKind::fromString(ObservanceKind::FEAST),
                $titulars,
                ['la' => $latinName]
            ),
            RankClass::fromOrdinal($rank),
            ElementColour::of(Colour::fromString($colour)),
            null,
            CitationSet::fromMarkers(['rank' => 'sspx-ordo', 'names.la' => 'mr-1920'])
        );
    }

    /**
     * @param list<SanctoralEntry> $entries
     *
     * @return array<string, int> id => rank ordinal
     */
    private static function indexByRank(array $entries): array
    {
        $byId = [];
        foreach ($entries as $entry) {
            $byId[$entry->identity()->id()->toString()] = $entry->rank()->ordinal();
        }

        return $byId;
    }

    /**
     * @param list<SanctoralEntry> $entries
     */
    private static function find(array $entries, string $id): SanctoralEntry
    {
        foreach ($entries as $entry) {
            if ($entry->identity()->id()->toString() === $id) {
                return $entry;
            }
        }

        self::fail(sprintf('No entry with id "%s".', $id));
    }
}
