<?php

declare(strict_types=1);

namespace Introibo\Core\Calendar;

use DateTimeImmutable;
use Introibo\Core\Observance\Observance;

/**
 * The resolved liturgical day: the immutable aggregate returned by
 * {@see \Introibo\Core\day()}.
 *
 * A day groups the observances in play into four roles:
 *  - **celebration** — the office(s) actually celebrated (normally one principal);
 *  - **commemoration** — offices commemorated within another office;
 *  - **displaced** — offices impeded on this day (transferred away or omitted);
 *  - **tempora** — the temporal office(s): the Sunday or feria of the season.
 *
 * This is the contract seam. It exists before the resolver phases so callers can
 * build against a stable shape; until those phases run, a day is an empty
 * {@see placeholder()}. The per-day realization of rank, colour and season
 * attaches to the observances as later phases populate the day, and the
 * output-contract epic formalises serialisation. The aggregate is immutable once
 * built: it holds only value objects and hands back copied arrays.
 */
final class LiturgicalDay
{
    private DateTimeImmutable $date;

    /** @var list<Observance> */
    private array $celebration;

    /** @var list<Observance> */
    private array $commemoration;

    /** @var list<Observance> */
    private array $displaced;

    /** @var list<Observance> */
    private array $tempora;

    /**
     * @param list<Observance> $celebration
     * @param list<Observance> $commemoration
     * @param list<Observance> $displaced
     * @param list<Observance> $tempora
     */
    public function __construct(
        DateTimeImmutable $date,
        array $celebration,
        array $commemoration,
        array $displaced,
        array $tempora
    ) {
        $this->date = $date;
        $this->celebration = array_values($celebration);
        $this->commemoration = array_values($commemoration);
        $this->displaced = array_values($displaced);
        $this->tempora = array_values($tempora);
    }

    /** An empty placeholder day for the given date: no observances in any role. */
    public static function placeholder(DateTimeImmutable $date): self
    {
        return new self($date, [], [], [], []);
    }

    public function date(): DateTimeImmutable
    {
        return $this->date;
    }

    /** @return list<Observance> */
    public function celebration(): array
    {
        return $this->celebration;
    }

    /** @return list<Observance> */
    public function commemoration(): array
    {
        return $this->commemoration;
    }

    /** @return list<Observance> */
    public function displaced(): array
    {
        return $this->displaced;
    }

    /** @return list<Observance> */
    public function tempora(): array
    {
        return $this->tempora;
    }

    /** True when no observance occupies any of the four roles. */
    public function isEmpty(): bool
    {
        return $this->celebration === []
            && $this->commemoration === []
            && $this->displaced === []
            && $this->tempora === [];
    }
}
