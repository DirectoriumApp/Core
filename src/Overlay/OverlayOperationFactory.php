<?php

declare(strict_types=1);

namespace Directorium\Core\Overlay;

use Directorium\Core\Attribute\RankClass;
use Directorium\Core\Citation\CitationSet;
use Directorium\Core\Corpus\CorpusRecord;
use Directorium\Core\Observance\Observance;
use Directorium\Core\Observance\ObservanceId;
use Directorium\Core\Observance\ObservanceKind;
use Directorium\Core\Sanctoral\SanctoralEntry;
use RuntimeException;

/**
 * Rebuilds one {@see OverlayOperation} value object from a corpus operation row —
 * the shared read seam behind both a particular-calendar overlay
 * ({@see CorpusOverlayData}, `overlays/<slug>/operations.ndjson`) and a dated
 * edition decree ({@see \Directorium\Core\Decree\DecreeSet}, whose `sanctoral`
 * changes are the same add / rerank / suppress vocabulary). Keeping the row→object
 * mapping in one factory means a decree and an overlay parse an operation
 * identically — an overlay is just a decree at a different cadence (a particular
 * calendar always in force; a decree in force from its effective date), so they
 * share the mechanism, not merely the shape (docs/design/edition-governance.md).
 */
final class OverlayOperationFactory
{
    /**
     * Rebuild the operation a `{ op: rerank|suppress|add, … }` row describes.
     *
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): OverlayOperation
    {
        $op = CorpusRecord::requireString($row, 'op');
        switch ($op) {
            case 'rerank':
                return self::rerank($row);
            case 'suppress':
                return self::suppress($row);
            case 'add':
                return self::add($row);
            default:
                throw new RuntimeException(sprintf('Corpus operation has an unknown op "%s".', $op));
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function rerank(array $row): RerankOperation
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
    private static function suppress(array $row): SuppressOperation
    {
        return new SuppressOperation(
            ObservanceId::parse(CorpusRecord::requireString($row, 'target')),
            CitationSet::fromMarkers(CorpusRecord::cites($row))
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function add(array $row): AddOperation
    {
        $entry = $row['entry'] ?? null;
        if (!is_array($entry)) {
            throw new RuntimeException('Corpus add operation has no entry.');
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
