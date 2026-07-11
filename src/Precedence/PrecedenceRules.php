<?php

declare(strict_types=1);

namespace Directorium\Core\Precedence;

use DateTimeImmutable;
use Directorium\Core\Calendar\RealizedObservance;
use Directorium\Core\Trace\ResolutionReason;

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
     *
     * This is the day-specific *admitted* count the commemoration selector and the
     * resolution trace use; {@see commemorationClassLimit()} is the class-level cap
     * before the zero-commemoration special-casing.
     */
    public function commemorationLimit(RealizedObservance $celebration, PrecedenceContext $context): int;

    /**
     * The commemoration cap for the celebrated office's day-class alone — the
     * per-edition class count (1962: I/II 1, III/IV 2; 1954: 3 for every class;
     * 1955: 0/1/2 by class, with the Sunday elevation folded into the class), before
     * the day-specific reductions {@see commemorationLimit()} applies. This is the
     * class-level figure the output contract reports (see docs/design/output-contract.md);
     * the days that admit no commemoration at all are distinguished only in the
     * resolution trace, so the contract's `commemorationLimit` stays a stable
     * property of the day's class across editions.
     */
    public function commemorationClassLimit(RealizedObservance $celebration): int;

    /** Whether $office, when commemorated, ranks as a privileged commemoration (n. 108). */
    public function isPrivilegedCommemoration(RealizedObservance $office): bool;

    /**
     * Whether the privileged commemorations are exempt from the per-day count limit —
     * kept even when {@see commemorationLimit()} is spent (or zero). True under the 1955
     * rubrics ({@see Rubrics1955Precedence}: Cum nostra Title III.2 makes them "in
     * addition to" the Title III.4 caps); false under 1954 and 1962, whose caps bound
     * every commemoration alike.
     */
    public function privilegedCommemorationsExemptFromLimit(): bool;

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

    /**
     * Whether the Office of the Dead (All Souls) yields the celebration to a Sunday and is
     * transferred to the next free day, though it outranks the Sunday in the Table of
     * Liturgical Days — a requiem is never sung on a Sunday (the rubric of 2 November; a
     * displaced All Souls takes the next-free-day placement, n. 96b). True for the
     * traditional editions; the reformed calendar, which keeps All Souls on the Sunday,
     * overrides it to false.
     */
    public function officeOfTheDeadYieldsToSunday(): bool;

    /**
     * Whether the edition resolves an evening Vespers concurrence at all. The traditional
     * Office contests the evening between one day's Second Vespers and the next day's First
     * Vespers, so the resolver runs a concurrence pass (true). The Novus-Ordo Office gives
     * First Vespers only to Sundays and solemnities and has no such contest, so stamping a
     * second-Vespers outcome on an ordinary weekday would be meaningless — it answers false
     * and the resolver skips the pass entirely.
     */
    public function observesVespersConcurrence(): bool;

    /**
     * Whether $observance is an *electable* optional memorial — one the celebrant may freely choose
     * but which never DISPLACES the day's ordinary office. The Novus Ordo alone has such offices
     * (an optional memorial, {@see \Directorium\Core\Attribute\RankClass} 4, of a saint), so the day
     * resolves deterministically to the obligatory memorial or the feria and the optional memorials
     * are electable alternatives rather than losers of the occurrence contest. The resolver removes
     * an electable office from the winning contest before choosing the celebration (so an optional
     * memorial cannot outrank a feria at line 12 vs 13). False for the three traditional editions,
     * which have no electable office (their lowest grade is a commemoration, resolved by the
     * occurrence rules). The electable options themselves are surfaced in the contract's
     * `optionalMemorials` slot by the later exposure slice (#260); until then they simply lapse.
     */
    public function isElectableOptionalMemorial(RealizedObservance $observance): bool;
}
