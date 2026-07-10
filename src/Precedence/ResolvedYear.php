<?php

declare(strict_types=1);

namespace Directorium\Core\Precedence;

use DateTimeImmutable;
use Directorium\Core\Calendar\LiturgicalDay;
use Directorium\Core\Contract\Provenance;

/**
 * A whole civil year resolved to a {@see LiturgicalDay} per date.
 *
 * The transfer queue makes any one day depend on what was displaced from earlier
 * days, so the resolver ({@see DayResolver}) resolves the year in a single
 * forward sweep and returns this immutable index; `day()` then reads it. Same
 * inputs produce a byte-identical year — the determinism the validation oracle
 * relies on.
 *
 * It also carries the {@see Provenance} that produced it — the edition, corpus,
 * and engine versions the output contract (#52) stamps onto each serialised day.
 *
 * @internal Not part of the public API (docs/api-stability.md); it is returned by the
 *           internal resolver, not by the stable day()/contract() boundary.
 */
final class ResolvedYear
{
    private int $year;

    /** @var array<string, LiturgicalDay> Keyed by 'Y-m-d'. */
    private array $days;

    private Provenance $provenance;

    /**
     * @param array<string, LiturgicalDay> $days
     */
    public function __construct(int $year, array $days, Provenance $provenance)
    {
        $this->year = $year;
        $this->days = $days;
        $this->provenance = $provenance;
    }

    public function year(): int
    {
        return $this->year;
    }

    /** The edition, corpus, and engine versions that resolved this year. */
    public function provenance(): Provenance
    {
        return $this->provenance;
    }

    /**
     * The resolved day for a date. A date outside the year returns an empty
     * placeholder rather than throwing.
     */
    public function day(DateTimeImmutable $date): LiturgicalDay
    {
        return $this->days[$date->format('Y-m-d')] ?? LiturgicalDay::placeholder($date);
    }
}
