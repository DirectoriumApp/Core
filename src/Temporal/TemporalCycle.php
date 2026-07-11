<?php

declare(strict_types=1);

namespace Directorium\Core\Temporal;

use Directorium\Core\Corpus\Corpus;

/**
 * The temporal-cycle strategy: which ordered set of block-fillers composes a year's
 * Proper of Time for an edition.
 *
 * The traditional editions (1962, 1954, 1955) share one cycle — Advent→Christmas,
 * the Lenten cycle, Holy Week, Eastertide, and the Time after Pentecost. The Novus
 * Ordo is a different temporal world (no Septuagesima, no distinct Passiontide, no
 * Ember/Rogation days, and one 34-week Ordinary Time in place of the green Sundays
 * after Epiphany and Pentecost), so it supplies its own fillers. The resolver was
 * already precedence-agnostic through {@see \Directorium\Core\Precedence\PrecedenceRules};
 * this makes it temporal-cycle-agnostic the same way, selecting the cycle from the
 * edition (docs/design/novus-ordo-calendar-model.md).
 *
 * A cycle is stateless; {@see forYear()} materialises it for one civil year into a
 * {@see YearTemporalCycle} the resolver reads day by day.
 *
 * @internal Not part of the public API (docs/api-stability.md); it is the resolver's
 *           temporal-fill seam. Resolve through {@see \Directorium\Core\day()}.
 */
interface TemporalCycle
{
    /**
     * Materialise the cycle for one civil year against an edition's corpus data.
     */
    public function forYear(int $year, Corpus $corpus, string $editionDir): YearTemporalCycle;
}
