<?php

declare(strict_types=1);

namespace Directorium\Core\Decree;

use DateTimeImmutable;
use Directorium\Core\Attribute\ElementColour;
use Directorium\Core\Attribute\RankClass;
use Directorium\Core\Observance\Observance;
use Directorium\Core\Sanctoral\SanctoralObservance;
use Directorium\Core\Temporal\Computus;
use Directorium\Core\Temporal\TemporalCalendar;

/**
 * A movable observance a decree adds to the calendar — a celebration whose date is
 * fixed by an offset from Easter, not by a civil month and day.
 *
 * The particular-calendar overlay vocabulary (add / rerank / suppress) reaches only
 * fixed-date sanctoral feasts, because a {@see \Directorium\Core\Sanctoral\SanctoralEntry}
 * is a month and a day. A decree, though, can institute a *movable* memorial: the
 * reform's Blessed Virgin Mary, Mother of the Church sits on the Monday after
 * Pentecost (Easter + 50), a date that changes every year. This value object carries
 * such an addition — its full Layer-1 identity, its per-edition rank and colour, and
 * the Easter offset that places it — and realizes it for a civil year exactly as the
 * movable feasts of the Lord are placed ({@see \Directorium\Core\Temporal\NovusOrdo\MovableFeasts}):
 * the office facts are data, only the paschal arithmetic is code.
 *
 * It is authored in a decree file's `movable` list and gated by the decree's effective
 * date, so a movable memorial appears only from the year its decree took force
 * (docs/design/edition-governance.md).
 *
 * @internal Not part of the public API (docs/api-stability.md).
 */
final class MovableDecree
{
    private Observance $identity;

    private RankClass $rank;

    private ElementColour $colour;

    /** Signed day-offset from Easter Sunday that places this observance (Pentecost Monday = 50). */
    private int $easterOffset;

    public function __construct(
        Observance $identity,
        RankClass $rank,
        ElementColour $colour,
        int $easterOffset
    ) {
        $this->identity = $identity;
        $this->rank = $rank;
        $this->colour = $colour;
        $this->easterOffset = $easterOffset;
    }

    public function easterOffset(): int
    {
        return $this->easterOffset;
    }

    /** The civil date this observance falls on in the given year: Easter plus its offset. */
    public function dateFor(int $year): DateTimeImmutable
    {
        return TemporalCalendar::addDays(Computus::gregorianEaster($year), $this->easterOffset);
    }

    /**
     * The realized office, ready to compete in precedence as an ordinary sanctoral
     * candidate — its movability lives only in {@see dateFor()}, not in the office.
     */
    public function realize(): SanctoralObservance
    {
        return new SanctoralObservance($this->identity, $this->rank, $this->colour);
    }
}
