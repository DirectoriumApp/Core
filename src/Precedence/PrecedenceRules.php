<?php

declare(strict_types=1);

namespace Introibo\Core\Precedence;

use DateTimeImmutable;
use Introibo\Core\Calendar\RealizedObservance;

/**
 * The precedence rules of one rubric edition.
 *
 * This is the per-edition seam (the abstraction #59 will use to add the 1954,
 * 1955, and Novus Ordo editions): the resolver pipeline is edition-agnostic and
 * asks the rules object every question whose answer is edition-specific. The only
 * implementation now is {@see Rubrics1962Precedence}.
 *
 * The interface grows as the resolver epic (#29) advances: this scaffold (#30)
 * defines the table lookup; occurrence and transfer outcomes (#31–#34),
 * concurrence (#35), and commemoration limits (#36) are added by their issues.
 */
interface PrecedenceRules
{
    /**
     * The office's line in this edition's Table of Liturgical Days — the sort
     * key occurrence resolves on.
     */
    public function tierOf(RealizedObservance $observance, PrecedenceContext $context): PrecedenceTier;

    /**
     * The fate of the office that loses an occurrence to $winner on this day:
     * commemorated, transferred to another day, or omitted entirely. $winner is
     * assumed to already outrank $loser by {@see tierOf()}.
     */
    public function occurrenceOutcome(
        RealizedObservance $winner,
        RealizedObservance $loser,
        PrecedenceContext $context
    ): OccurrenceOutcome;

    /**
     * The rubrically fixed day a transferred feast must be kept on, or null if
     * it takes the ordinary next-free-day placement. The Annunciation, when
     * impeded into Holy Week or the Easter octave, is kept on the Monday after
     * Low Sunday (n. 96a).
     */
    public function forcedTransferDate(RealizedObservance $feast, PrecedenceContext $context): ?DateTimeImmutable;

    /**
     * How the evening between the office of the preceding day and the office of
     * the following day is resolved — whose Vespers is said and whether the other
     * is commemorated.
     */
    public function concurrenceOutcome(
        RealizedObservance $preceding,
        RealizedObservance $following,
        PrecedenceContext $context
    ): ConcurrenceOutcome;
}
