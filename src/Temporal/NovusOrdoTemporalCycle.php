<?php

declare(strict_types=1);

namespace Directorium\Core\Temporal;

use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Temporal\NovusOrdo\ChristmasCycle;
use Directorium\Core\Temporal\NovusOrdo\OrdinaryTime;
use Directorium\Core\Temporal\NovusOrdo\PaschalCycle;

/**
 * The Novus-Ordo (Ordinary-Form) Proper of Time — every day of the reformed temporal
 * cycle now filled.
 *
 * The reform's four temporal blocks map onto four fillers: **Ordinary Time**, the two
 * green blocks under one 1–34 week count ({@see OrdinaryTime}, #258); the **Advent →
 * Christmas Time** block ({@see ChristmasCycle}, #108); and the **paschal half** — Lent,
 * Holy Week, and Easter Time — gathered into {@see PaschalCycle} (#108), which also
 * supplies the {@see TriduumWindow} the precedence context reads.
 *
 * The Christmas cycle spans a civil-year boundary (Advent of one year runs to the
 * Baptism of the next), so — exactly as the {@see TraditionalTemporalCycle} does — a
 * civil year is tiled by the PREVIOUS year's Christmas cycle (its January tail: the
 * octave day, Epiphany, the Baptism), Ordinary Time and the paschal half in the middle,
 * and the current year's Christmas cycle (its December head: Advent to the Nativity). The
 * fillers are consulted in order and the first that owns a date wins; the blocks do not
 * overlap, so the order only has to keep each date with the cycle that owns it.
 *
 * Because the Novus-Ordo edition is refused at the public boundary until it is validated
 * (#260), this cycle is not yet reached through
 * {@see \Directorium\Core\Precedence\DayResolver::resolveYear()} for a public edition; it
 * is exercised directly by the filler tests until the edition is completed.
 *
 * @internal Not part of the public API (docs/api-stability.md).
 */
final class NovusOrdoTemporalCycle implements TemporalCycle
{
    public function forYear(int $year, Corpus $corpus, string $editionDir): YearTemporalCycle
    {
        $paschal = PaschalCycle::forYear($year, $corpus, $editionDir);

        return new YearTemporalCycle(
            [
                ChristmasCycle::forYear($year - 1, $corpus, $editionDir),
                OrdinaryTime::forYear($year, $corpus, $editionDir),
                $paschal,
                ChristmasCycle::forYear($year, $corpus, $editionDir),
            ],
            $paschal
        );
    }
}
