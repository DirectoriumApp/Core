<?php

declare(strict_types=1);

namespace Introibo\Core\Calendar;

use Introibo\Core\Attribute\ElementColour;
use Introibo\Core\Attribute\RankClass;
use Introibo\Core\Observance\ObservanceId;
use Introibo\Core\Observance\ObservanceKind;

/**
 * An observance as realized on one day of one edition: its Layer-1 identity
 * paired with the per-edition (Layer-2) attributes a resolved day needs to
 * sort, rank, and render it.
 *
 * Both cycles produce a `RealizedObservance` — the temporal fillers via
 * {@see \Introibo\Core\Temporal\TemporalObservance} and the sanctoral overlay
 * via {@see \Introibo\Core\Sanctoral\SanctoralObservance} — so precedence (#29)
 * can compare temporal and sanctoral offices uniformly and the output contract
 * (#52) can serialize whichever wins. It is the seam that lets
 * {@see LiturgicalDay} eventually carry realized offices rather than bare
 * identity shells; wiring the aggregate onto it is the resolver's work (#37).
 *
 * Season is deliberately absent: it is a property of the day, not of each
 * office (see {@see \Introibo\Core\Temporal\Season}), so a sanctoral feast is
 * not made to carry a foreign season. The rank and colour here are per-edition
 * attributes, never identity — they are supplied by an edition, never parsed
 * from the id. See docs/design/sanctoral-overlay-model.md.
 */
interface RealizedObservance
{
    public function id(): ObservanceId;

    public function kind(): ObservanceKind;

    public function rank(): RankClass;

    public function colour(): ElementColour;

    /** The invariant Latin ("la") name every observance carries. */
    public function latinName(): string;
}
