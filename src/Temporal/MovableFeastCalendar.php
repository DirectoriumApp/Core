<?php

declare(strict_types=1);

namespace Directorium\Core\Temporal;

use DateTimeImmutable;

/**
 * The movable feasts of the Lord placed by a Sunday / Easter rule for one year — the
 * overlays the resolver gathers alongside the temporal cycle and the sanctoral.
 *
 * The set is edition-selected exactly as the {@see TemporalCycle} is: the traditional
 * editions supply {@see MovableFeasts} (Holy Name, Holy Family, the Most Holy Trinity,
 * Corpus Christi, the Sacred Heart, Christ the King), the Novus Ordo its own
 * {@see NovusOrdo\MovableFeasts} (only the four solemnities the reform keeps in Ordinary
 * Time, re-placed and re-titled). The resolver reads either through this one seam.
 *
 * @internal Not part of the public API (docs/api-stability.md).
 */
interface MovableFeastCalendar
{
    /** The movable feast on a date, or null if none falls there. */
    public function on(DateTimeImmutable $date): ?TemporalObservance;
}
