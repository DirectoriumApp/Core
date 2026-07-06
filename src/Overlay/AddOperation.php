<?php

declare(strict_types=1);

namespace Directorium\Core\Overlay;

use Directorium\Core\Observance\ObservanceId;
use Directorium\Core\Sanctoral\SanctoralEntry;

/**
 * Add a proper feast the particular calendar celebrates but the universal calendar
 * does not — a society's or diocese's own saint. The operation carries a complete
 * {@see SanctoralEntry} (identity, date, rank, colour, citations), so the added
 * feast is indistinguishable from a universal one once layered; it simply comes
 * from the overlay's provenance rather than the edition's.
 *
 * Adding a feast the base already has is a conflict — an elevation of an existing
 * feast is a {@see RerankOperation}, not an add.
 */
final class AddOperation implements OverlayOperation
{
    private SanctoralEntry $entry;

    public function __construct(SanctoralEntry $entry)
    {
        $this->entry = $entry;
    }

    public function targetId(): ObservanceId
    {
        return $this->entry->identity()->id();
    }

    /**
     * @param array<string, SanctoralEntry> $byId
     *
     * @return array<string, SanctoralEntry>
     */
    public function applyTo(array $byId): array
    {
        $key = $this->targetId()->toString();
        if (isset($byId[$key])) {
            throw new OverlayConflict(
                sprintf('Cannot add "%s": it is already in the base calendar (re-rank it instead).', $key)
            );
        }

        $byId[$key] = $this->entry;

        return $byId;
    }
}
