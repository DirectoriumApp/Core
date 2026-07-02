<?php

declare(strict_types=1);

namespace Introibo\Core\Precedence;

use DateTimeImmutable;
use Introibo\Core\Calendar\LiturgicalDay;

/**
 * A whole civil year resolved to a {@see LiturgicalDay} per date.
 *
 * The transfer queue makes any one day depend on what was displaced from earlier
 * days, so the resolver ({@see DayResolver}) resolves the year in a single
 * forward sweep and returns this immutable index; `day()` then reads it. Same
 * inputs produce a byte-identical year — the determinism the validation oracle
 * relies on.
 */
final class ResolvedYear
{
    private int $year;

    /** @var array<string, LiturgicalDay> Keyed by 'Y-m-d'. */
    private array $days;

    /**
     * @param array<string, LiturgicalDay> $days
     */
    public function __construct(int $year, array $days)
    {
        $this->year = $year;
        $this->days = $days;
    }

    public function year(): int
    {
        return $this->year;
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
