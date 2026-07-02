<?php

declare(strict_types=1);

namespace Introibo\Core\Overlay;

/**
 * A particular calendar as a thin, declarative layer over the universal one: an
 * identified, named set of {@see OverlayOperation}s (adds, suppressions, re-ranks)
 * to apply to the base 1962 sanctoral (Core #75/#76). The SSPX, FSSP and ICKSP
 * calendars are each one of these; the engine stays universal and only the data is
 * layered.
 *
 * The overlay is identified by its platform URN (`introibo:overlay:roman:sspx`) —
 * stamped onto the output contract so consumers know which calendar produced a day.
 * At most one operation may target a given feast: two operations on the same id is
 * an authoring error, so the constructor rejects it up front and application is
 * order-independent.
 */
final class CalendarOverlay
{
    private string $id;

    private string $name;

    /** @var list<OverlayOperation> */
    private array $operations;

    /**
     * @param list<OverlayOperation> $operations
     */
    public function __construct(string $id, string $name, array $operations)
    {
        if ($id === '') {
            throw new OverlayConflict('An overlay must have a non-empty id.');
        }

        $seen = [];
        foreach ($operations as $operation) {
            $target = $operation->targetId()->toString();
            if (isset($seen[$target])) {
                throw new OverlayConflict(
                    sprintf('Overlay "%s" has two operations targeting "%s".', $id, $target)
                );
            }
            $seen[$target] = true;
        }

        $this->id = $id;
        $this->name = $name;
        $this->operations = $operations;
    }

    /** The overlay's platform URN, e.g. `introibo:overlay:roman:sspx`. */
    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    /** @return list<OverlayOperation> */
    public function operations(): array
    {
        return $this->operations;
    }
}
