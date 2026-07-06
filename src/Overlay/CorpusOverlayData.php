<?php

declare(strict_types=1);

namespace Directorium\Core\Overlay;

use Directorium\Core\Attribute\RankClass;
use Directorium\Core\Citation\CitationSet;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Corpus\CorpusRecord;
use Directorium\Core\Observance\Observance;
use Directorium\Core\Observance\ObservanceId;
use Directorium\Core\Observance\ObservanceKind;
use Directorium\Core\Sanctoral\SanctoralEntry;
use RuntimeException;

/**
 * Reads a particular-calendar {@see CalendarOverlay} from the cited CC0 corpus (#76).
 *
 * The overlays are hand-authored, cited YAML compiled by the build-time generator into
 * byte-stable NDJSON (`overlays/<slug>/operations.ndjson`) plus a metadata singleton
 * (`overlays/<slug>/overlay.json`); this loader is the read seam that rebuilds the PHP
 * {@see OverlayOperation} value objects from those rows and hands back a ready overlay.
 * Layer it over the base sanctoral with {@see OverlaidSanctoralData} and the resolver
 * produces the particular calendar — the engine stays universal, only the data changes.
 *
 * It is to overlays what {@see \Directorium\Core\Sanctoral\CorpusSanctoralData} is to the
 * base sanctoral, and shares the same typed row readers ({@see CorpusRecord}).
 */
final class CorpusOverlayData
{
    private Corpus $corpus;

    public function __construct(?Corpus $corpus = null)
    {
        $this->corpus = $corpus ?? Corpus::default();
    }

    /**
     * The overlay slugs the corpus ships (e.g. `sspx`).
     *
     * @return list<string>
     */
    public function slugs(): array
    {
        return $this->corpus->overlaySlugs();
    }

    /** Whether the corpus ships an overlay with the given slug. */
    public function has(string $slug): bool
    {
        return in_array($slug, $this->slugs(), true);
    }

    /**
     * The overlay for the given slug, rebuilt from the corpus. Throws if the corpus
     * carries no such overlay.
     */
    public function overlay(string $slug): CalendarOverlay
    {
        if (!$this->has($slug)) {
            throw new RuntimeException(sprintf(
                'Corpus has no overlay "%s"; known overlays: %s.',
                $slug,
                $this->slugs() === [] ? '(none)' : implode(', ', $this->slugs())
            ));
        }

        $meta = $this->corpus->overlayMeta($slug);

        $operations = [];
        foreach ($this->corpus->overlayOperations($slug) as $row) {
            $operations[] = $this->operation($row);
        }

        return new CalendarOverlay(
            CorpusRecord::requireString($meta, 'id'),
            CorpusRecord::requireString($meta, 'name'),
            $operations
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function operation(array $row): OverlayOperation
    {
        $op = CorpusRecord::requireString($row, 'op');
        switch ($op) {
            case 'rerank':
                return $this->rerank($row);
            case 'suppress':
                return $this->suppress($row);
            case 'add':
                return $this->add($row);
            default:
                throw new RuntimeException(sprintf('Corpus overlay has an unknown operation "%s".', $op));
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rerank(array $row): RerankOperation
    {
        $colour = isset($row['colour']) ? CorpusRecord::elementColour($row) : null;

        return new RerankOperation(
            ObservanceId::parse(CorpusRecord::requireString($row, 'target')),
            RankClass::fromOrdinal(CorpusRecord::requireInt($row, 'rank')),
            $colour,
            CitationSet::fromMarkers(CorpusRecord::cites($row))
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function suppress(array $row): SuppressOperation
    {
        return new SuppressOperation(
            ObservanceId::parse(CorpusRecord::requireString($row, 'target')),
            CitationSet::fromMarkers(CorpusRecord::cites($row))
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function add(array $row): AddOperation
    {
        $entry = $row['entry'] ?? null;
        if (!is_array($entry)) {
            throw new RuntimeException('Corpus overlay add operation has no entry.');
        }
        /** @var array<string, mixed> $entry */

        $id = CorpusRecord::requireString($entry, 'id');
        $vigilOf = CorpusRecord::optionalString($entry, 'vigilOf');
        // Forwarded for symmetry with vigilOf so an overlay-added observance can carry its
        // octave link too; the overlay-operation schema does not yet expose octaveOf, so this
        // is null in practice today (a particular calendar adds feasts, not octaves).
        $octaveOf = CorpusRecord::optionalString($entry, 'octaveOf');

        return new AddOperation(new SanctoralEntry(
            CorpusRecord::requireInt($entry, 'month'),
            CorpusRecord::requireInt($entry, 'day'),
            new Observance(
                ObservanceId::parse($id),
                ObservanceKind::fromString(CorpusRecord::requireString($entry, 'kind')),
                CorpusRecord::titulars($entry),
                CorpusRecord::names($entry)
            ),
            RankClass::fromOrdinal(CorpusRecord::requireInt($entry, 'rank')),
            CorpusRecord::elementColour($entry),
            $vigilOf !== null ? ObservanceId::parse($vigilOf) : null,
            CitationSet::fromMarkers(CorpusRecord::cites($entry)),
            null,
            $octaveOf !== null ? ObservanceId::parse($octaveOf) : null
        ));
    }
}
