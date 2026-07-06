<?php

declare(strict_types=1);

namespace Directorium\Core\Calendar;

use DateTimeImmutable;
use Directorium\Core\Precedence\ConcurrenceOutcome;
use Directorium\Core\Trace\ResolutionTrace;

/**
 * The resolved liturgical day: the immutable aggregate returned by
 * {@see \Directorium\Core\day()}.
 *
 * A day groups the offices in play into four roles:
 *  - **celebration** — the office actually celebrated (normally one principal);
 *  - **commemoration** — offices commemorated within the celebration;
 *  - **displaced** — offices impeded on this day (transferred away or omitted);
 *  - **tempora** — the temporal office of the season (the Sunday or feria),
 *    always reported even when it is also the celebration.
 *
 * Each role holds {@see RoledObservance}s: an office paired with its role and,
 * where relevant, its occurrence outcome and transfer links. Convenience readers
 * ({@see celebration()} and the other three) hand back the bare
 * {@see RealizedObservance}s for callers that only need identity and attributes;
 * {@see offices()} hands back the full roled offices the output contract (#52)
 * serialises. The evening boundary with the next day is reported by
 * {@see secondVespers()} (null on a placeholder). The aggregate is immutable: it
 * holds only value objects and hands back copied arrays.
 */
final class LiturgicalDay
{
    private DateTimeImmutable $date;

    /** @var list<RoledObservance> */
    private array $celebration;

    /** @var list<RoledObservance> */
    private array $commemoration;

    /** @var list<RoledObservance> */
    private array $displaced;

    /** @var list<RoledObservance> */
    private array $tempora;

    private ?ConcurrenceOutcome $secondVespers;

    private ?ResolutionTrace $trace;

    private ?int $commemorationLimit;

    /**
     * @param list<RoledObservance> $celebration
     * @param list<RoledObservance> $commemoration
     * @param list<RoledObservance> $displaced
     * @param list<RoledObservance> $tempora
     * @param int|null              $commemorationLimit the commemorations the day admits by
     *        its class under the resolving edition ({@see commemorationLimit()}), or null on a
     *        day the resolver did not stamp (a placeholder or a synthetically built day)
     */
    public function __construct(
        DateTimeImmutable $date,
        array $celebration,
        array $commemoration,
        array $displaced,
        array $tempora,
        ?ConcurrenceOutcome $secondVespers = null,
        ?ResolutionTrace $trace = null,
        ?int $commemorationLimit = null
    ) {
        $this->date = $date;
        $this->celebration = array_values($celebration);
        $this->commemoration = array_values($commemoration);
        $this->displaced = array_values($displaced);
        $this->tempora = array_values($tempora);
        $this->secondVespers = $secondVespers;
        $this->trace = $trace;
        $this->commemorationLimit = $commemorationLimit;
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
        return self::unwrap($this->celebration);
    }

    /** @return list<RealizedObservance> */
    public function commemoration(): array
    {
        return self::unwrap($this->commemoration);
    }

    /** @return list<RealizedObservance> */
    public function displaced(): array
    {
        return self::unwrap($this->displaced);
    }

    /** @return list<RealizedObservance> */
    public function tempora(): array
    {
        return self::unwrap($this->tempora);
    }

    /**
     * Every office of the day in role order (celebration, commemoration,
     * displaced, tempora), each carrying its role, outcome, and transfer links.
     *
     * @return list<RoledObservance>
     */
    public function offices(): array
    {
        return array_merge($this->celebration, $this->commemoration, $this->displaced, $this->tempora);
    }

    /** How the evening concurs with the following day, or null when not resolved. */
    public function secondVespers(): ?ConcurrenceOutcome
    {
        return $this->secondVespers;
    }

    /** The show-your-work resolution trace (#233), or null unless the day was explained. */
    public function trace(): ?ResolutionTrace
    {
        return $this->trace;
    }

    /**
     * How many commemorations the day admits by the class of its celebrated office, under the
     * edition that resolved it — the per-edition class cap the output contract reports (#332).
     * Null on a day the resolver did not stamp (a placeholder or a synthetically built day).
     */
    public function commemorationLimit(): ?int
    {
        return $this->commemorationLimit;
    }

    /** A copy of the day with its evening concurrence resolved. */
    public function withSecondVespers(ConcurrenceOutcome $secondVespers): self
    {
        return new self(
            $this->date,
            $this->celebration,
            $this->commemoration,
            $this->displaced,
            $this->tempora,
            $secondVespers,
            $this->trace,
            $this->commemorationLimit
        );
    }

    /** A copy of the day carrying its resolution trace. */
    public function withTrace(ResolutionTrace $trace): self
    {
        return new self(
            $this->date,
            $this->celebration,
            $this->commemoration,
            $this->displaced,
            $this->tempora,
            $this->secondVespers,
            $trace,
            $this->commemorationLimit
        );
    }

    /**
     * A copy of the day rebuilt from a flat list of offices, re-bucketed by
     * their roles. Used by the resolver's reconciliation pass to stamp transfer
     * links without disturbing the date or the resolved evening concurrence.
     *
     * @param list<RoledObservance> $offices
     */
    public function withOffices(array $offices): self
    {
        $byRole = [
            CelebrationRole::CELEBRATION => [],
            CelebrationRole::COMMEMORATION => [],
            CelebrationRole::DISPLACED => [],
            CelebrationRole::TEMPORA => [],
        ];
        foreach ($offices as $office) {
            $byRole[$office->role()->value()][] = $office;
        }

        return new self(
            $this->date,
            $byRole[CelebrationRole::CELEBRATION],
            $byRole[CelebrationRole::COMMEMORATION],
            $byRole[CelebrationRole::DISPLACED],
            $byRole[CelebrationRole::TEMPORA],
            $this->secondVespers,
            $this->trace,
            $this->commemorationLimit
        );
    }

    /** True when no observance occupies any of the four roles. */
    public function isEmpty(): bool
    {
        return $this->celebration === []
            && $this->commemoration === []
            && $this->displaced === []
            && $this->tempora === [];
    }

    /**
     * @param list<RoledObservance> $offices
     *
     * @return list<RealizedObservance>
     */
    private static function unwrap(array $offices): array
    {
        $bare = [];
        foreach ($offices as $office) {
            $bare[] = $office->observance();
        }

        return $bare;
    }
}
