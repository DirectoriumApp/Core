<?php

declare(strict_types=1);

namespace Directorium\Core\Decree;

use DateTimeImmutable;
use Directorium\Core\Overlay\OverlayOperation;

/**
 * A dated decree: one published change to an edition's calendar, applied on or after
 * its effective date (docs/design/edition-governance.md).
 *
 * The living Novus-Ordo calendar is a base *editio typica* snapshot plus a stream of
 * these — a canonisation, a rank elevation, a suppression, a new memorial. Modelling
 * each as a dated, additive layer keeps the snapshot itself frozen: resolving a civil
 * year replays exactly the decrees in force by then, so the same `(date, edition)`
 * always resolves the same way (an early year sees no later decree; a current year
 * sees them all). A decree carries two kinds of change, either of which may be empty:
 *
 *  - **sanctoral operations** — the same add / rerank / suppress vocabulary a
 *    particular-calendar overlay uses ({@see OverlayOperation}), acting on a fixed-date
 *    feast (e.g. raising St Mary Magdalene from a memorial to a feast, 2016); and
 *  - **movable additions** — a memorial placed by an Easter offset that no fixed-date
 *    operation can express ({@see MovableDecree}; e.g. the Blessed Virgin Mary, Mother
 *    of the Church on the Monday after Pentecost, 2018).
 *
 * The decree is inert engine-side: {@see DecreeSet} gates it by date and hands its
 * changes to the sanctoral decorator and the movable candidate source. Immutable.
 *
 * @internal Not part of the public API (docs/api-stability.md).
 */
final class Decree
{
    private string $id;

    private DateTimeImmutable $effective;

    private string $title;

    /** @var list<OverlayOperation> */
    private array $sanctoralOperations;

    /** @var list<MovableDecree> */
    private array $movableAdditions;

    /**
     * @param list<OverlayOperation> $sanctoralOperations
     * @param list<MovableDecree>    $movableAdditions
     */
    public function __construct(
        string $id,
        DateTimeImmutable $effective,
        string $title,
        array $sanctoralOperations,
        array $movableAdditions
    ) {
        $this->id = $id;
        $this->effective = $effective;
        $this->title = $title;
        $this->sanctoralOperations = $sanctoralOperations;
        $this->movableAdditions = $movableAdditions;
    }

    /** The decree's stable slug, `<effective-date>-<title-slug>` (its file basename). */
    public function id(): string
    {
        return $this->id;
    }

    /** The date from which the decree is in force; it applies to a day on or after it. */
    public function effective(): DateTimeImmutable
    {
        return $this->effective;
    }

    /** The decree's incipit / short title (e.g. "Apostolorum Apostola"). */
    public function title(): string
    {
        return $this->title;
    }

    /** @return list<OverlayOperation> */
    public function sanctoralOperations(): array
    {
        return $this->sanctoralOperations;
    }

    /** @return list<MovableDecree> */
    public function movableAdditions(): array
    {
        return $this->movableAdditions;
    }
}
