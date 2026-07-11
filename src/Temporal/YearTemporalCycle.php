<?php

declare(strict_types=1);

namespace Directorium\Core\Temporal;

use DateTimeImmutable;
use Directorium\Core\Temporal\NovusOrdo\OrdinaryTime;

/**
 * One civil year's Proper of Time for one edition: the ordered block-fillers and the
 * Triduum window the resolver needs.
 *
 * The fillers are consulted in order; the first that owns a date supplies its
 * temporal office (the earlier blocks are the more specific, exactly as before the
 * list was abstracted behind {@see TemporalCycle}). The Novus-Ordo cycle supplies
 * {@see \Directorium\Core\Temporal\NovusOrdo\ChristmasCycle}, {@see OrdinaryTime}, and
 * {@see \Directorium\Core\Temporal\NovusOrdo\PaschalCycle} instances into the same list
 * — any block-filler exposing `on()` is accepted, so the enumerated union below is the
 * traditional set and not exhaustive. {@see isTriduum()} feeds the precedence context
 * from a {@see TriduumWindow}: the traditional cycle supplies its {@see HolyWeek}, the
 * Novus-Ordo cycle its {@see \Directorium\Core\Temporal\NovusOrdo\PaschalCycle}, and a
 * cycle with no Triduum source reports false.
 *
 * @internal Not part of the public API (docs/api-stability.md).
 */
final class YearTemporalCycle
{
    /**
     * @var list<ChristmasCycle|LentenCycle|HolyWeek|Eastertide|TimeAfterPentecost|OrdinaryTime>
     */
    private array $fillers;

    private ?TriduumWindow $triduum;

    /**
     * @param list<ChristmasCycle|LentenCycle|HolyWeek|Eastertide|TimeAfterPentecost|OrdinaryTime> $fillers
     */
    public function __construct(array $fillers, ?TriduumWindow $triduum)
    {
        $this->fillers = $fillers;
        $this->triduum = $triduum;
    }

    /** The temporal office of one day — the first filler that owns it, or null. */
    public function office(DateTimeImmutable $date): ?TemporalObservance
    {
        foreach ($this->fillers as $filler) {
            $office = $filler->on($date);
            if ($office !== null) {
                return $office;
            }
        }

        return null;
    }

    /** Whether the date falls in the Sacred Triduum (feeds the precedence context). */
    public function isTriduum(DateTimeImmutable $date): bool
    {
        return $this->triduum !== null && $this->triduum->isTriduum($date);
    }
}
