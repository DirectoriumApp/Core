<?php

declare(strict_types=1);

namespace Introibo\Core\Overlay;

use Introibo\Core\Attribute\ElementColour;
use Introibo\Core\Attribute\RankClass;
use Introibo\Core\Citation\CitationSet;
use Introibo\Core\Observance\ObservanceId;
use Introibo\Core\Sanctoral\SanctoralEntry;

/**
 * Re-rank a universal feast the particular calendar keeps but ranks differently —
 * the commonest overlay operation (the SSPX calendar, for instance, keeps St Pius
 * X and the Seven Sorrows but elevates them to the first class).
 *
 * It replaces the base entry's {@see RankClass} (and its {@see ElementColour}, when
 * the higher rank changes the colour) while preserving the feast's identity, date,
 * and vigil link. The overlay's own citation is layered over the base entry's, so
 * the changed field now cites the particular calendar's source, not the universal
 * edition's.
 */
final class RerankOperation implements OverlayOperation
{
    private ObservanceId $target;

    private RankClass $rank;

    private ?ElementColour $colour;

    private CitationSet $citations;

    public function __construct(
        ObservanceId $target,
        RankClass $rank,
        ?ElementColour $colour = null,
        ?CitationSet $citations = null
    ) {
        $this->target = $target;
        $this->rank = $rank;
        $this->colour = $colour;
        $this->citations = $citations ?? CitationSet::empty();
    }

    public function targetId(): ObservanceId
    {
        return $this->target;
    }

    /**
     * @param array<string, SanctoralEntry> $byId
     *
     * @return array<string, SanctoralEntry>
     */
    public function applyTo(array $byId): array
    {
        $key = $this->target->toString();
        $base = $byId[$key] ?? null;
        if ($base === null) {
            throw new OverlayConflict(sprintf('Cannot re-rank "%s": it is not in the base calendar.', $key));
        }

        $byId[$key] = new SanctoralEntry(
            $base->month(),
            $base->day(),
            $base->identity(),
            $this->rank,
            $this->colour ?? $base->colour(),
            $base->vigilOfId(),
            $base->citations()->merge($this->citations)
        );

        return $byId;
    }
}
