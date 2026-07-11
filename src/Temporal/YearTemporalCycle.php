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
 * list was abstracted behind {@see TemporalCycle}). {@see isTriduum()} feeds the
 * precedence context; the traditional cycle delegates it to {@see HolyWeek}, and a
 * cycle with no Triduum source (the Novus Ordo until its Holy Week filler lands with
 * the general-calendar corpus, #108) reports false.
 *
 * @internal Not part of the public API (docs/api-stability.md).
 */
final class YearTemporalCycle
{
    /**
     * @var list<ChristmasCycle|LentenCycle|HolyWeek|Eastertide|TimeAfterPentecost|OrdinaryTime>
     */
    private array $fillers;

    private ?HolyWeek $holyWeek;

    /**
     * @param list<ChristmasCycle|LentenCycle|HolyWeek|Eastertide|TimeAfterPentecost|OrdinaryTime> $fillers
     */
    public function __construct(array $fillers, ?HolyWeek $holyWeek)
    {
        $this->fillers = $fillers;
        $this->holyWeek = $holyWeek;
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
        return $this->holyWeek !== null && $this->holyWeek->isTriduum($date);
    }
}
