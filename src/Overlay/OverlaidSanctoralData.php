<?php

declare(strict_types=1);

namespace Introibo\Core\Overlay;

use Introibo\Core\Sanctoral\SanctoralData;
use Introibo\Core\Sanctoral\SanctoralEntry;

/**
 * The universal 1962 sanctoral with a particular-calendar overlay applied — a
 * {@see SanctoralData} decorator that layers one {@see CalendarOverlay}'s
 * operations over a base source (Core #77).
 *
 * It is exactly the swap the {@see SanctoralData} seam was built for: the resolver
 * reads its sanctoral through this interface, so a particular calendar changes only
 * the *data* the resolver sees — the precedence, concurrence, and commemoration
 * logic is untouched. Operations apply before precedence runs; they are sorted by
 * target id so application is deterministic, and because at most one operation
 * targets a feast ({@see CalendarOverlay} enforces this), the order does not affect
 * the result. The version stamp carries the overlay's id so a consumer can tell an
 * overlaid build from the universal one.
 */
final class OverlaidSanctoralData implements SanctoralData
{
    private SanctoralData $base;

    private CalendarOverlay $overlay;

    public function __construct(SanctoralData $base, CalendarOverlay $overlay)
    {
        $this->base = $base;
        $this->overlay = $overlay;
    }

    /** @return list<SanctoralEntry> */
    public function entries(): array
    {
        $byId = [];
        foreach ($this->base->entries() as $entry) {
            $byId[$entry->identity()->id()->toString()] = $entry;
        }

        $operations = $this->overlay->operations();
        usort(
            $operations,
            static fn (OverlayOperation $a, OverlayOperation $b): int
                => $a->targetId()->toString() <=> $b->targetId()->toString()
        );

        foreach ($operations as $operation) {
            $byId = $operation->applyTo($byId);
        }

        return array_values($byId);
    }

    public function version(): string
    {
        return $this->base->version() . '+' . $this->overlay->id();
    }
}
