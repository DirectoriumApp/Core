<?php

declare(strict_types=1);

namespace Introibo\Core\Calendar;

use DateTimeImmutable;
use Introibo\Core\Precedence\ConcurrenceOutcome;

/**
 * The resolved liturgical day: the immutable aggregate returned by
 * {@see \Introibo\Core\day()}.
 *
 * A day groups the offices in play into four roles:
 *  - **celebration** — the office actually celebrated (normally one principal);
 *  - **commemoration** — offices commemorated within the celebration;
 *  - **displaced** — offices impeded on this day (transferred away or omitted);
 *  - **tempora** — the temporal office of the season (the Sunday or feria),
 *    always reported even when it is also the celebration.
 *
 * Each role holds {@see RealizedObservance}s, so every office carries the rank
 * and colour it wears this day; the role is the array it sits in. The evening
 * boundary with the next day is reported by {@see secondVespers()} (null on a
 * placeholder). The aggregate is immutable: it holds only value objects and
 * hands back copied arrays.
 */
final class LiturgicalDay
{
    private DateTimeImmutable $date;

    /** @var list<RealizedObservance> */
    private array $celebration;

    /** @var list<RealizedObservance> */
    private array $commemoration;

    /** @var list<RealizedObservance> */
    private array $displaced;

    /** @var list<RealizedObservance> */
    private array $tempora;

    private ?ConcurrenceOutcome $secondVespers;

    /**
     * @param list<RealizedObservance> $celebration
     * @param list<RealizedObservance> $commemoration
     * @param list<RealizedObservance> $displaced
     * @param list<RealizedObservance> $tempora
     */
    public function __construct(
        DateTimeImmutable $date,
        array $celebration,
        array $commemoration,
        array $displaced,
        array $tempora,
        ?ConcurrenceOutcome $secondVespers = null
    ) {
        $this->date = $date;
        $this->celebration = array_values($celebration);
        $this->commemoration = array_values($commemoration);
        $this->displaced = array_values($displaced);
        $this->tempora = array_values($tempora);
        $this->secondVespers = $secondVespers;
    }

    /** An empty placeholder day for the given date: no observances in any role. */
    public static function placeholder(DateTimeImmutable $date): self
    {
        return new self($date, [], [], [], [], null);
    }

    public function date(): DateTimeImmutable
    {
        return $this->date;
    }

    /** @return list<RealizedObservance> */
    public function celebration(): array
    {
        return $this->celebration;
    }

    /** @return list<RealizedObservance> */
    public function commemoration(): array
    {
        return $this->commemoration;
    }

    /** @return list<RealizedObservance> */
    public function displaced(): array
    {
        return $this->displaced;
    }

    /** @return list<RealizedObservance> */
    public function tempora(): array
    {
        return $this->tempora;
    }

    /** How the evening concurs with the following day, or null when not resolved. */
    public function secondVespers(): ?ConcurrenceOutcome
    {
        return $this->secondVespers;
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
