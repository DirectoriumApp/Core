<?php

declare(strict_types=1);

namespace Directorium\Core\Temporal;

use DateTimeImmutable;

/**
 * The Sacred Triduum window of one year: the three days (Maundy Thursday, Good
 * Friday, Holy Saturday) that stand at the apex of every edition's Table of
 * Precedence, which no observance may displace.
 *
 * The Triduum falls on the same three Easter-relative dates in every rite, so this
 * is the small seam a {@see YearTemporalCycle} reads to answer
 * {@see YearTemporalCycle::isTriduum()} — the traditional cycle supplies its
 * {@see HolyWeek} filler, the Novus-Ordo cycle its {@see NovusOrdo\PaschalCycle},
 * and the resolver's precedence context is fed from either without caring which
 * built it. A cycle with no Triduum source reports false.
 *
 * @internal Not part of the public API (docs/api-stability.md).
 */
interface TriduumWindow
{
    /** Whether the date is one of the three days of the Sacred Triduum. */
    public function isTriduum(DateTimeImmutable $date): bool;
}
