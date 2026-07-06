<?php

declare(strict_types=1);

namespace Directorium\Core\Calendar;

use DateTimeImmutable;
use Directorium\Core\Precedence\OccurrenceOutcome;

/**
 * A realized office paired with the role it plays on a resolved day, plus the
 * occurrence outcome and transfer links that make the office self-describing.
 *
 * This is how an office is "marked" as the celebration, a commemoration, or
 * displaced. Because it keeps the whole {@see RealizedObservance}, a displaced
 * office retains all the data — id, rank, colour, name — the transfer queue
 * (#34) needs to re-place it on a later day, and a commemoration keeps
 * everything needed to render it.
 *
 * Beyond the role it also carries how the office fared in occurrence (the
 * {@see OccurrenceOutcome}: commemorated, transferred, or omitted; null for a
 * celebration or the tempora, which never "lose") and, for a transferred feast,
 * the dates that link the two ends of the move: {@see transferredTo()} on the
 * day it was impeded and {@see transferredFrom()} on the day it lands. Those two
 * dates are stamped by the resolver's reconciliation pass once the whole year is
 * known, so the output contract (#52) can emit self-describing offices without
 * the consumer having to correlate days.
 *
 * Assigning all of this is the resolver's decision (#29); this type only carries
 * it. Immutable: it holds value objects and returns copies via {@see withTransfer()}.
 */
final class RoledObservance
{
    private RealizedObservance $observance;

    private CelebrationRole $role;

    private ?OccurrenceOutcome $outcome;

    private ?DateTimeImmutable $transferredTo;

    private ?DateTimeImmutable $transferredFrom;

    public function __construct(
        RealizedObservance $observance,
        CelebrationRole $role,
        ?OccurrenceOutcome $outcome = null,
        ?DateTimeImmutable $transferredTo = null,
        ?DateTimeImmutable $transferredFrom = null
    ) {
        $this->observance = $observance;
        $this->role = $role;
        $this->outcome = $outcome;
        $this->transferredTo = $transferredTo;
        $this->transferredFrom = $transferredFrom;
    }

    public function observance(): RealizedObservance
    {
        return $this->observance;
    }

    public function role(): CelebrationRole
    {
        return $this->role;
    }

    /** How this office fared in occurrence, or null for a celebration or the tempora. */
    public function outcome(): ?OccurrenceOutcome
    {
        return $this->outcome;
    }

    /** The date a transferred (impeded) office was moved to, or null. */
    public function transferredTo(): ?DateTimeImmutable
    {
        return $this->transferredTo;
    }

    /** The date a landed office was transferred from, or null when celebrated on its own day. */
    public function transferredFrom(): ?DateTimeImmutable
    {
        return $this->transferredFrom;
    }

    /**
     * A copy that records the transfer link the reconciliation pass discovered:
     * where an impeded office went, or where a landing office came from. Role,
     * observance, and outcome are preserved.
     */
    public function withTransfer(?DateTimeImmutable $to, ?DateTimeImmutable $from): self
    {
        return new self($this->observance, $this->role, $this->outcome, $to, $from);
    }

    public function equals(self $other): bool
    {
        return $this->role->equals($other->role)
            && $this->outcomeMatches($other)
            && $this->dateMatches($this->transferredTo, $other->transferredTo)
            && $this->dateMatches($this->transferredFrom, $other->transferredFrom)
            && $this->observance->id()->equals($other->observance->id())
            && $this->observance->rank()->equals($other->observance->rank())
            && $this->observance->colour()->equals($other->observance->colour())
            && $this->observance->latinName() === $other->observance->latinName();
    }

    private function outcomeMatches(self $other): bool
    {
        if ($this->outcome === null || $other->outcome === null) {
            return $this->outcome === $other->outcome;
        }

        return $this->outcome->equals($other->outcome);
    }

    private function dateMatches(?DateTimeImmutable $a, ?DateTimeImmutable $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return $a->format('Y-m-d') === $b->format('Y-m-d');
    }
}
