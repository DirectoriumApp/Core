<?php

declare(strict_types=1);

namespace Directorium\Core\Overlay;

use Directorium\Core\Observance\ObservanceId;
use Directorium\Core\Sanctoral\SanctoralEntry;

/**
 * One operation a particular-calendar overlay applies to the universal sanctoral.
 *
 * The three operations — {@see RerankOperation}, {@see AddOperation},
 * {@see SuppressOperation} — are pure data: a particular calendar is a thin layer
 * of these over the base 1962 sanctoral, never engine code (Core #75/#76). Each
 * names the {@see ObservanceId} it acts on ({@see targetId}) and knows how to
 * apply itself to the base entries, indexed by id, returning the new map. An
 * operation whose precondition fails — re-ranking or suppressing a feast the base
 * does not have, or adding one it already has — raises an {@see OverlayConflict}
 * so a malformed overlay fails loudly rather than resolving wrongly.
 */
interface OverlayOperation
{
    /** The universal-calendar feast this operation acts on. */
    public function targetId(): ObservanceId;

    /**
     * Apply this operation to the id-indexed base entries, returning the new map.
     *
     * @param array<string, SanctoralEntry> $byId the base entries, keyed by id string
     *
     * @return array<string, SanctoralEntry>
     */
    public function applyTo(array $byId): array;
}
