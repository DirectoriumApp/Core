<?php

declare(strict_types=1);

namespace Directorium\Core\Temporal;

use Directorium\Core\Corpus\Corpus;

/**
 * The traditional Roman Proper of Time, shared by the 1962, 1954, and 1955 editions:
 * the Advent→Christmas cycle, the Lenten cycle, Holy Week, Eastertide, and the Time
 * after Pentecost. The per-edition differences (the 1954 privileged octaves, the
 * moveable feasts) are carried inside the fillers by the edition's archetype overlay,
 * not by the cycle — so all three traditional editions use this one cycle.
 *
 * The filler order is the resolver's historical order (the ChristmasCycle of the
 * *previous* year first, so a January date is owned by the tail of the Christmas cycle
 * that opened the prior Advent, and the current year's ChristmasCycle last), preserved
 * exactly so the 1962 golden fixture is byte-identical.
 *
 * @internal Not part of the public API (docs/api-stability.md).
 */
final class TraditionalTemporalCycle implements TemporalCycle
{
    public function forYear(int $year, Corpus $corpus, string $editionDir): YearTemporalCycle
    {
        $holyWeek = HolyWeek::forYear($year, $corpus, $editionDir);

        return new YearTemporalCycle(
            [
                ChristmasCycle::forYear($year - 1, $corpus, $editionDir),
                LentenCycle::forYear($year, $corpus, $editionDir),
                $holyWeek,
                Eastertide::forYear($year, $corpus, $editionDir),
                TimeAfterPentecost::forYear($year, $corpus, $editionDir),
                ChristmasCycle::forYear($year, $corpus, $editionDir),
            ],
            $holyWeek
        );
    }
}
