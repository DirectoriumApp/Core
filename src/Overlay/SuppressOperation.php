<?php

declare(strict_types=1);

namespace Introibo\Core\Overlay;

use Introibo\Core\Citation\CitationSet;
use Introibo\Core\Observance\ObservanceId;
use Introibo\Core\Sanctoral\SanctoralEntry;

/**
 * Suppress a universal feast a particular calendar does not keep — removing it from
 * the sanctoral so the day resolves to whatever it would be without the feast. The
 * suppression carries its own citation (the particular calendar's authority for the
 * omission); suppressing a feast the base does not have is a conflict.
 */
final class SuppressOperation implements OverlayOperation
{
    private ObservanceId $target;

    private CitationSet $citations;

    public function __construct(ObservanceId $target, ?CitationSet $citations = null)
    {
        $this->target = $target;
        $this->citations = $citations ?? CitationSet::empty();
    }

    public function targetId(): ObservanceId
    {
        return $this->target;
    }

    /** The particular calendar's authority for omitting the feast. */
    public function citations(): CitationSet
    {
        return $this->citations;
    }

    /**
     * @param array<string, SanctoralEntry> $byId
     *
     * @return array<string, SanctoralEntry>
     */
    public function applyTo(array $byId): array
    {
        $key = $this->target->toString();
        if (!isset($byId[$key])) {
            throw new OverlayConflict(sprintf('Cannot suppress "%s": it is not in the base calendar.', $key));
        }

        unset($byId[$key]);

        return $byId;
    }
}
