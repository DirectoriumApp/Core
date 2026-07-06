<?php

declare(strict_types=1);

namespace Introibo\Core\Precedence;

use DateTimeImmutable;
use Introibo\Core\Calendar\RealizedObservance;
use Introibo\Core\Trace\ResolutionReason;

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

    /**
     * How many commemorations the day of $celebration admits — the celebrated
     * office's class count (n. 111b–d), reduced to zero on the days that admit
     * none at all (the Triduum, the privileged octaves, the first-class vigils).
     */
    public function commemorationLimit(RealizedObservance $celebration, PrecedenceContext $context): int;

    /** Whether $office, when commemorated, ranks as a privileged commemoration (n. 108). */
    public function isPrivilegedCommemoration(RealizedObservance $office): bool;

    /**
     * Why $winner is the office of the day — the cited reason the resolution trace
     * (#233) reports for the celebration (its line in the Table of Liturgical Days).
     */
    public function explainPrecedence(RealizedObservance $winner, PrecedenceContext $context): ResolutionReason;

    /**
     * The cited reason $loser met its {@see occurrenceOutcome()} — produced from the
     * same decision, so the explanation can never disagree with the outcome.
     */
    public function explainOccurrence(
        RealizedObservance $winner,
        RealizedObservance $loser,
        PrecedenceContext $context
    ): ResolutionReason;

    /** The cited reason the day admits the number of commemorations it does. */
    public function explainCommemorationLimit(
        RealizedObservance $celebration,
        PrecedenceContext $context
    ): ResolutionReason;

    /** The cited reason the day is the liturgical colour it is — the colour of the celebrated office. */
    public function explainColour(RealizedObservance $celebration): ResolutionReason;

    /** The cited reason the day is in the season it is — the season of its temporal office, or none. */
    public function explainSeason(?string $season): ResolutionReason;

    /**
     * Whether a common sanctoral vigil that falls on a Sunday is anticipated to the
     * preceding Saturday (the pre-1955 rule) rather than omitted (1955 and 1962). This is a
     * placement-time question the resolver hands to the sanctoral layer, so it is answered
     * by the edition's rules — false for {@see Rubrics1962Precedence}, true for the pre-1955
     * {@see Rubrics1954Precedence}.
     */
    public function anticipatesSundayVigils(): bool;
}
