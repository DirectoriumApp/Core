<?php

declare(strict_types=1);

namespace Directorium\Core\Temporal;

use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Temporal\NovusOrdo\OrdinaryTime;

/**
 * The Novus-Ordo (Ordinary-Form) Proper of Time.
 *
 * v1.1 (#258) delivers the season the reform genuinely invents — **Ordinary Time**,
 * the two green blocks under one 1–34 week count — as {@see OrdinaryTime}. The other
 * Novus-Ordo seasons (Advent, Christmas Time, Lent, Holy Week, Easter Time) reuse the
 * traditional structures minus the pre-conciliar apparatus and arrive with the
 * general-calendar corpus (#108); their fillers join this list there.
 *
 * Because the Novus-Ordo precedence engine (#257) is not yet built, this cycle is not
 * reached through {@see \Directorium\Core\Precedence\DayResolver::resolveYear()} yet
 * (the edition is refused at the public boundary and has no precedence rules); it is
 * exercised directly by the filler tests until the edition is completed. Its Triduum
 * source is therefore null for now.
 *
 * @internal Not part of the public API (docs/api-stability.md).
 */
final class NovusOrdoTemporalCycle implements TemporalCycle
{
    public function forYear(int $year, Corpus $corpus, string $editionDir): YearTemporalCycle
    {
        return new YearTemporalCycle(
            [
                OrdinaryTime::forYear($year, $corpus, $editionDir),
            ],
            null
        );
    }
}
