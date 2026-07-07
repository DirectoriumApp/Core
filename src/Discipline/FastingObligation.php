<?php

declare(strict_types=1);

namespace Directorium\Core\Discipline;

/**
 * A day's fasting and abstinence obligation under a penitential discipline: whether
 * it is a day of fast, the abstinence it carries, the rule that determined it, and
 * the discipline (with its cited canon) that governs.
 *
 * A day with no obligation carries no `FastingObligation` at all (the resolver returns
 * null and the contract's `fasting` slot stays null); this object is minted only when
 * some fast or abstinence applies. It is a per-day realization ({@see FastingResolver}),
 * not an edition attribute — a suppressed vigil simply never produces one.
 */
final class FastingObligation
{
    private bool $fast;

    private Abstinence $abstinence;

    private string $reason;

    private string $disciplineUrn;

    private string $citation;

    public function __construct(
        bool $fast,
        Abstinence $abstinence,
        string $reason,
        string $disciplineUrn,
        string $citation
    ) {
        $this->fast = $fast;
        $this->abstinence = $abstinence;
        $this->reason = $reason;
        $this->disciplineUrn = $disciplineUrn;
        $this->citation = $citation;
    }

    /** Whether the day is a day of fast (one full meal). */
    public function fast(): bool
    {
        return $this->fast;
    }

    public function abstinence(): Abstinence
    {
        return $this->abstinence;
    }

    /** The rule that determined the obligation (e.g. `friday`, `lent-major`, `vigil`). */
    public function reason(): string
    {
        return $this->reason;
    }

    /** The governing discipline's URN, e.g. `roman:cic-1917`. */
    public function disciplineUrn(): string
    {
        return $this->disciplineUrn;
    }

    /** The cited authority for the rule, e.g. `cic-1917:c1252`. */
    public function citation(): string
    {
        return $this->citation;
    }

    public function equals(self $other): bool
    {
        return $this->fast === $other->fast
            && $this->abstinence->equals($other->abstinence)
            && $this->reason === $other->reason
            && $this->disciplineUrn === $other->disciplineUrn
            && $this->citation === $other->citation;
    }
}
