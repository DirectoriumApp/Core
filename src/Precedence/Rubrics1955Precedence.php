<?php

declare(strict_types=1);

namespace Directorium\Core\Precedence;

use DateInterval;
use DateTimeImmutable;
use Directorium\Core\Attribute\LegacyRank;
use Directorium\Core\Calendar\RealizedObservance;
use Directorium\Core\Observance\ObservanceKind;
use Directorium\Core\Sanctoral\SanctoralObservance;
use Directorium\Core\Temporal\Computus;
use Directorium\Core\Temporal\Season;
use Directorium\Core\Temporal\TemporalObservance;
use Directorium\Core\Trace\ResolutionReason;

/**
 * Precedence under the 1955 interim rubrics — the Cum nostra hac aetate edition
 * (`roman:rubricae-1955`).
 *
 * This is the middle sibling of {@see Rubrics1954Precedence} and
 * {@see Rubrics1962Precedence}: the resolver pipeline is edition-agnostic and asks
 * the rules object every edition-specific question, and this class answers them for
 * the reduced rubrics of 23 March 1955 (in force 1 January 1956). The tier ordinals,
 * the named membership sets, and the commemoration limits are CORPUS DATA read
 * through {@see PrecedenceTable} against the `roman-rubricae-1955` edition dir
 * (facts/editions/roman-rubricae-1955/precedence.yaml); this class holds only the
 * branching logic and the occurrence / transfer / concurrence / commemoration
 * decisions.
 *
 * 1955 is the pre-1955 Divino Afflatu system with the Cum nostra reductions applied,
 * so most of the occurrence machinery is inherited from that edition. What Cum nostra
 * changed, all cited to the decree (cn-1955) and cross-checked against the Divinum
 * Officium "Reduced - 1955" engine (do-1955, issue #71):
 *
 *  1. The SEMIDOUBLE grade is abolished (Title II.1, II.20): former semidoubles are
 *     graded `simplex` in the derived 1955 data (so they resolve on the `simple`
 *     tier), and former simples are reduced to a commemoration (Title II.21, graded
 *     `commemoratio`, so they take the floor `commemoration` tier and can never win
 *     an occurrence). No observance ever lands on a semidouble tier, and it is
 *     absent from the 1955 table.
 *  2. The Sundays of Advent, of Lent up to Low Sunday, and Pentecost are ALL of the
 *     first class (Title II.3), outranking every feast — the `first-class-sunday`
 *     membership set, read from the table, elevates Advent II-IV that were second
 *     class under 1954. A feast or mystery of the Lord below the first class (the
 *     Exaltation of the Cross) takes a per-annum Sunday's place (Title II.7 — the
 *     `lord-mystery` set). An impeded Sunday is neither anticipated nor resumed (II.6).
 *  2a. Translation is restricted to feasts of the FIRST class (with All Souls): an
 *     impeded second-class feast is commemorated in place, not moved — where the
 *     pre-1955 rite also translated Doubles of the II class.
 *
 * The finer 1955 Tabella distinctions among second-class feasts (an APOSTLE's feast
 * outranks a per-annum Sunday where a martyr's cedes to it; the named Christmastide
 * ferias; the moveable feasts of the Lord and BVM the temporal layer does not yet
 * carry) are deferred per-day-type casuistry, tracked against the DO "Reduced - 1955"
 * oracle (see docs/design/rubric-system-model.md, Seam 6a, and the validation baseline).
 *  3. A common vigil that falls on a Sunday is OMITTED, not anticipated to the
 *     preceding Saturday (Title II.10) — {@see anticipatesSundayVigils()} is false,
 *     as under 1962.
 *  4. Commemorations are capped by the day's class (Title III.4): a first-class day
 *     admits no ADDITIONAL commemoration, a second-class day one, any day at most
 *     two. The never-omitted privileged commemorations (Title III.2 — any Sunday, a
 *     first-class feast, the ferias of Lent and Advent) are made OVER these caps;
 *     {@see privilegedCommemorationsExemptFromLimit()} is true, so the selector keeps
 *     them even on a zero-cap day.
 *
 * Scope is calendar-level (docs/design/rubric-system-model.md, Seam 6a): the office of
 * the day, its rank/colour/season, its commemorations, and the displaced/transferred
 * offices — not the Divine Office casuistry. The privileged commemorations of the
 * September Ember days (III.2d) and the Major Litanies (III.2e) are not enumerated
 * here because those observances are themselves deferred from the current temporal
 * corpus (see TimeAfterPentecost and PaschalSkeleton); they are added with the
 * observance, not guessed ahead of it.
 */
final class Rubrics1955Precedence implements PrecedenceRules
{
    /** The path-safe directory key of the 1955 (Cum nostra hac aetate) edition. */
    private const EDITION_DIR = 'roman-rubricae-1955';

    private PrecedenceTable $table;

    public function __construct(?PrecedenceTable $table = null)
    {
        $this->table = $table ?? new PrecedenceTable(null, self::EDITION_DIR);
    }

    public function tierOf(RealizedObservance $observance, PrecedenceContext $context): PrecedenceTier
    {
        $id = $observance->id()->toString();
        $kind = $observance->kind()->value();

        // The COMMEMORATION_ONLY kind is a 1962 attribute carried on the shared identity: a
        // saint the 1960 reform reduced to a bare commemoration was, under the 1955 rubrics,
        // often still a real (simplex) office that IS the day on a free feria. Classify it by
        // its native 1955 grade; only a genuine commemoration — one carrying no grade, or the
        // `commemoratio` grade Cum nostra reduced the simples to — takes the floor tier.
        if ($kind === ObservanceKind::COMMEMORATION_ONLY) {
            $grade = $this->legacyGradeOf($observance);
            if ($grade === null || $grade === LegacyRank::COMMEMORATIO) {
                return $this->table->tier('commemoration');
            }
        }

        // The three greatest feasts, by identity — Easter and Pentecost are kind=sunday and
        // Christmas is kind=feast, so identity is checked before the structural branches.
        if ($this->table->isMember('greatest', $id)) {
            return $this->table->tier('greatest');
        }
        // The Sacred Triduum's own office holds the apex; a saint merely coincident keeps its
        // far lower tier.
        if ($context->isTriduum() && $kind === ObservanceKind::FERIA) {
            return $this->table->tier('triduum');
        }

        // Sundays: the two 1955 classes by identity, else the lesser per-annum Sunday. Handled
        // as a block so a Sunday never falls into a grade tier. The first-class set now carries
        // all of Advent and Lent up to Low Sunday (Title II.3).
        if ($kind === ObservanceKind::SUNDAY) {
            if ($this->table->isMember('first-class-sunday', $id)) {
                return $this->table->tier('first-class-sunday');
            }
            if ($this->table->isMember('second-class-sunday', $id)) {
                return $this->table->tier('second-class-sunday');
            }

            return $this->table->tier('lesser-sunday');
        }

        // The first-class (privileged) vigils with their own proper office — Christmas and
        // Pentecost — and the days within the privileged Easter/Pentecost octaves.
        if ($this->table->isMember('privileged-vigil', $id)) {
            return $this->table->tier('privileged-vigil');
        }
        if ($this->isWithinPaschalOctave($id)) {
            return $this->table->tier('paschal-octave');
        }
        // The privileged ferias that admit no feast — Ash Wednesday and Monday/Tuesday/
        // Wednesday of Holy Week — carry the first-class ferial rank in the temporal data.
        if ($kind === ObservanceKind::FERIA && $observance->rank()->ordinal() === 1) {
            return $this->table->tier('privileged-feria');
        }
        // All Souls (the Office of the Dead) is kept on 2 November, transferred when impeded.
        if ($kind === ObservanceKind::OFFICE_OF_THE_DEAD) {
            return $this->table->tier('all-souls');
        }
        // Feasts of the Lord that are themselves Doubles of the I class — mapped to the Double
        // I class tier.
        if ($this->table->isMember('great-lord', $id)) {
            return $this->table->tier('double-i-class');
        }
        // A sanctoral feast or mystery of the Lord below the first class (the Transfiguration,
        // the Exaltation and Finding of the Cross, the Dedication of the Saviour's basilica):
        // Title II.7 lifts it above the per-annum Sunday it commemorates, where an ordinary
        // saint's feast of the same grade yields to that Sunday instead.
        if ($this->table->isMember('lord-mystery', $id)) {
            return $this->table->tier('lord-mystery');
        }

        // The votive Office of Our Lady on a free Saturday (#453), retained by Cum nostra, is
        // minted on the temporal path (no legacy grade): route it by KIND before the grade ladder,
        // to its own tier ABOVE the Simple (a coincident Simple — now a commemoration — is
        // commemorated under it). It yields to every feast above a simple and every privileged
        // feria / vigil; a displaced lady office is DROPPED, not commemorated (decideOccurrence).
        if ($kind === ObservanceKind::LADY_ON_SATURDAY) {
            return $this->table->tier('lady-on-saturday');
        }

        // The sanctoral grade ladder, by legacy token. Cum nostra suppressed all sanctoral
        // octaves, so no `octaveOf` record survives in the 1955 data to reach these tiers; a
        // retained common vigil carries `vigilia`, and the reduced grades resolve as below.
        // The `semiduplex` branch of the 1954 ladder is intentionally absent (Title II.1):
        // no 1955 datum carries that grade.
        $grade = $this->legacyGradeOf($observance);
        if ($grade === LegacyRank::DUPLEX_I_CLASSIS) {
            return $this->table->tier('double-i-class');
        }
        if ($grade === LegacyRank::DUPLEX_II_CLASSIS) {
            return $this->table->tier('double-ii-class');
        }
        // A feast of the Lord below the Double II class (the Holy Name, the Holy Family) still
        // takes a LESSER Sunday's place.
        if ($this->table->isMember('feasts-of-the-lord', $id)) {
            return $this->table->tier('feast-of-the-lord');
        }
        // A moveable feast that is a greater double of a saint / the BVM — the Passiontide Seven
        // Sorrows (#453). Cum nostra leaves a double unchanged, so it keeps the Duplex maius grade
        // 1954 gives it; minted on the temporal path it carries no legacy grade, and its numeric
        // rank (III) cannot tell a greater double from an ordinary double — so it is named to the
        // greater-double tier by identity, exactly as in the Divino Afflatu edition.
        if ($this->table->isMember('moveable-greater-double', $id)) {
            return $this->table->tier('greater-double');
        }
        if ($grade === LegacyRank::DUPLEX_MAIUS) {
            return $this->table->tier('greater-double');
        }
        if ($grade === LegacyRank::DUPLEX) {
            return $this->table->tier('double');
        }
        if ($grade === LegacyRank::VIGILIA) {
            return $this->table->tier('common-vigil');
        }
        if ($grade === LegacyRank::SIMPLEX) {
            // Two Simples on one day: the more worthy by the general Table of Precedence is
            // celebrated (the `dignior-simple` tier, above a plain Simple) and the other
            // commemorated — instead of the resolver's arbitrary id-string tie-break (#453).
            if ($this->table->isMember('dignior-simple', $id)) {
                return $this->table->tier('dignior-simple');
            }

            return $this->table->tier('simple');
        }
        // A former simple reduced to a bare commemoration (Title II.21): it never celebrates,
        // taking the floor tier below the whole Table so it can only ever be commemorated.
        if ($grade === LegacyRank::COMMEMORATIO) {
            return $this->table->tier('commemoration');
        }

        // A day within the (temporal, retained) Octave of Christmas — always commemorated, so
        // it sits below the feasts that displace it.
        if ($this->isChristmasOctaveWithin($id)) {
            return $this->table->tier('christmas-octave-within');
        }
        if ($this->isFeriaLike($kind)) {
            return $this->feriaTier($observance);
        }

        // Safety net: a temporal office without a legacy grade that escaped the membership
        // sets maps by its normalised class, keeping tierOf() total against a future addition.
        return $this->fallbackTier($observance);
    }

    /**
     * Ferias by dignity: the greater (major) ferias of Advent, Lent, and Passiontide, the
     * Ember days, and the Rogation days are commemorated when impeded; the ordinary green
     * weekdays are omitted.
     */
    private function feriaTier(RealizedObservance $observance): PrecedenceTier
    {
        return $this->isGreaterFeria($observance)
            ? $this->table->tier('greater-feria')
            : $this->table->tier('ordinary-feria');
    }

    private function fallbackTier(RealizedObservance $observance): PrecedenceTier
    {
        switch ($observance->rank()->ordinal()) {
            case 1:
                return $this->table->tier('double-i-class');
            case 2:
                return $this->table->tier('double-ii-class');
            case 3:
                return $this->table->tier('double');
            default:
                return $this->table->tier('ordinary-feria');
        }
    }

    public function occurrenceOutcome(
        RealizedObservance $winner,
        RealizedObservance $loser,
        PrecedenceContext $context
    ): OccurrenceOutcome {
        return $this->decideOccurrence($winner, $loser, $context)[0];
    }

    public function explainOccurrence(
        RealizedObservance $winner,
        RealizedObservance $loser,
        PrecedenceContext $context
    ): ResolutionReason {
        return $this->decideOccurrence($winner, $loser, $context)[1];
    }

    /**
     * The single occurrence decision — the loser's fate AND the cited reason — produced
     * together so the outcome and its explanation can never disagree. Branch order is the
     * 1955 order of precedence, inherited from the Divino Afflatu occurrence table (rg-da)
     * except where Cum nostra (cn-1955) reduced it.
     *
     * @return array{0: OccurrenceOutcome, 1: ResolutionReason}
     */
    private function decideOccurrence(
        RealizedObservance $winner,
        RealizedObservance $loser,
        PrecedenceContext $context
    ): array {
        // ONLY a feast of the first class (and All Souls) is translated to the next free day;
        // Cum nostra dropped the pre-1955 translation of second-class feasts, which are now
        // commemorated or omitted in place.
        if ($this->isTransferable($loser)) {
            if ($loser->kind()->value() === ObservanceKind::OFFICE_OF_THE_DEAD) {
                return [OccurrenceOutcome::transfer(), ResolutionReason::cited(
                    'cn-all-souls-transfer',
                    'transferred: All Souls is kept on the next free day when impeded',
                    'rg-da'
                )];
            }

            return [OccurrenceOutcome::transfer(), ResolutionReason::cited(
                'cn-double-transfer',
                'transferred: a feast of the first class is moved to the next free day when impeded',
                'cn-1955'
            )];
        }

        // The Triduum, the privileged (Easter/Pentecost) octaves, and Easter and Pentecost
        // themselves admit no commemoration at all — not even a privileged one.
        if ($this->admitsNoCommemoration($winner, $context)) {
            return [OccurrenceOutcome::omit(), ResolutionReason::cited(
                'cn-no-commemoration-admitted',
                'omitted: this day admits no commemoration (the Triduum or a privileged octave)',
                'rg-da'
            )];
        }

        // An ordinary (minor) feria yields to the feast with no commemoration; only the
        // greater ferias (Advent/Lent/Passiontide, Ember, Rogation) are commemorated.
        if ($this->isOrdinaryFeria($loser)) {
            return [OccurrenceOutcome::omit(), ResolutionReason::cited(
                'cn-ordinary-feria',
                'omitted: an ordinary feria yields to the feast without a commemoration',
                'rg-da'
            )];
        }

        // A displaced votive Office of Our Lady on Saturday is DROPPED, not commemorated: it is
        // not a feast of the saints of the day, so when a feast above a simple — or a privileged
        // feria / vigil / Ember day — takes the Saturday, the lady office simply yields (#453).
        if ($loser->kind()->value() === ObservanceKind::LADY_ON_SATURDAY) {
            return [OccurrenceOutcome::omit(), ResolutionReason::cited(
                'cn-lady-on-saturday-dropped',
                'omitted: the votive Office of Our Lady on Saturday yields without a commemoration',
                'rg-da'
            )];
        }

        // Everything else impeded is commemorated, subject to the day's Title III.4 count
        // limit applied by the resolver — but a privileged commemoration (Title III.2) is kept
        // even on a day whose ADDITIONAL-commemoration count is zero.
        return [OccurrenceOutcome::commemorate(), ResolutionReason::cited(
            'cn-commemoration-admitted',
            'commemorated: an impeded office is kept as a commemoration within the day\'s 1955 limit'
                . ' (a privileged commemoration is never omitted)',
            'cn-1955'
        )];
    }

    public function explainPrecedence(RealizedObservance $winner, PrecedenceContext $context): ResolutionReason
    {
        $tier = $this->tierOf($winner, $context);
        $selector = $tier->selector();
        $named = $selector !== null ? sprintf(' (%s)', str_replace('-', ' ', $selector)) : '';

        return ResolutionReason::cited(
            'cn-order-of-precedence',
            sprintf(
                'celebrated as the day\'s highest office in the 1955 order of precedence'
                    . ' (the pre-1955 table as reduced by Cum nostra)%s',
                $named
            ),
            'cn-1955'
        );
    }

    public function explainCommemorationLimit(
        RealizedObservance $celebration,
        PrecedenceContext $context
    ): ResolutionReason {
        if ($this->admitsNoCommemoration($celebration, $context)) {
            return ResolutionReason::cited(
                'cn-no-commemoration-admitted',
                'no commemoration is admitted (the Triduum or a privileged octave)',
                'rg-da'
            );
        }

        $limit = $this->limitFor($celebration);

        return ResolutionReason::cited(
            'cn-commemoration-limit',
            sprintf(
                'the 1955 rite admits %d additional commemoration(s) by the day\'s class (Title III.4),'
                    . ' beyond the never-omitted privileged commemorations',
                $limit
            ),
            'cn-1955'
        );
    }

    public function explainColour(RealizedObservance $celebration): ResolutionReason
    {
        $colour = $celebration->colour();
        $rose = $colour->roseAllowed() ? ', with rose permitted on Gaudete and Laetare' : '';

        return ResolutionReason::cited(
            'cn-colour-of-celebration',
            sprintf('%s: the liturgical colour of the celebrated office%s', $colour->base()->value(), $rose),
            'rg-da'
        );
    }

    public function explainSeason(?string $season): ResolutionReason
    {
        if ($season === null) {
            return ResolutionReason::uncited(
                'cn-no-temporal-season',
                'no temporal office governs the day, so it carries no season'
            );
        }

        return ResolutionReason::cited(
            'cn-season-of-temporal-office',
            sprintf('%s: the season of the day\'s temporal office', $season),
            'rg-da'
        );
    }

    public function anticipatesSundayVigils(): bool
    {
        // A common vigil that falls on a Sunday is OMITTED, not anticipated to the preceding
        // Saturday (Title II.10) — the pre-1955 anticipation Cum nostra abolished, matching
        // the later 1960 rubrics (n. 33).
        return false;
    }

    public function officeOfTheDeadYieldsToSunday(): bool
    {
        // As under 1954/1962: All Souls is never sung on a Sunday, so its Office and Mass are
        // deferred to the next free day (3 November when 2 November is a Sunday).
        return true;
    }

    public function privilegedCommemorationsExemptFromLimit(): bool
    {
        // Title III.2: the privileged commemorations (any Sunday, a first-class feast, the
        // ferias of Lent and Advent, ...) are "never to be omitted" and are made in ADDITION
        // to the Title III.4 counts — so a first-class day, whose additional-commemoration
        // count is zero, still keeps them. (1954 and 1962 apply their caps to every
        // commemoration alike, so both return false.)
        return true;
    }

    public function forcedTransferDate(RealizedObservance $feast, PrecedenceContext $context): ?DateTimeImmutable
    {
        // The Annunciation, impeded into Holy Week or the Easter octave, is kept on the Monday
        // after Low Sunday (Low Sunday is Easter + 7) — the same fixed landing as 1954/1962.
        if ($feast->id()->toString() === 'roman:sanctorale:annuntiatio') {
            $year = (int) $context->date()->format('Y');

            return Computus::gregorianEaster($year)->add(new DateInterval('P8D'));
        }

        return null;
    }

    public function concurrenceOutcome(
        RealizedObservance $preceding,
        RealizedObservance $following,
        PrecedenceContext $context
    ): ConcurrenceOutcome {
        $precedingTier = $this->tierOf($preceding, $context);
        $followingTier = $this->tierOf($following, $context);

        // The more dignified office holds the evening; an equal-rank concurrence goes to the
        // following day's First Vespers, commemorating the preceding. The finer split is
        // deferred to the Office layer.
        if (!$precedingTier->isHigherThan($followingTier)) {
            return $this->ratesVespersCommemoration($preceding)
                ? ConcurrenceOutcome::followingWithCommemorationOfPreceding()
                : ConcurrenceOutcome::fullOfFollowing();
        }

        return $this->ratesVespersCommemoration($following)
            ? ConcurrenceOutcome::precedingWithCommemorationOfFollowing()
            : ConcurrenceOutcome::fullOfPreceding();
    }

    public function commemorationLimit(RealizedObservance $celebration, PrecedenceContext $context): int
    {
        if ($this->admitsNoCommemoration($celebration, $context)) {
            return 0;
        }

        return $this->commemorationClassLimit($celebration);
    }

    public function commemorationClassLimit(RealizedObservance $celebration): int
    {
        return $this->limitFor($celebration);
    }

    private function limitFor(RealizedObservance $celebration): int
    {
        // Title III.4: 0/1/2 additional commemorations by the day's class, the Sunday
        // elevation (Title II.3) folded into the class via dayClassFor().
        return $this->table->commemorationLimit($this->dayClassFor($celebration));
    }

    /**
     * The day's class for the Title III.4 commemoration cap. Cum nostra (Title II.3) raised the
     * Sundays of Advent and Lent to the first class, but their rank ATTRIBUTE is shared with
     * 1962 (temporal attributes are edition-invariant in the corpus), so Advent II-IV still
     * read as second class by ordinal. The elevation lives in the precedence tier, so the
     * first-class Sundays are recognised from the membership set — not the ordinal — and take
     * the zero additional-commemoration cap of a first-class day (Title III.4a).
     */
    private function dayClassFor(RealizedObservance $celebration): int
    {
        if ($celebration->kind()->value() === ObservanceKind::SUNDAY) {
            $id = $celebration->id()->toString();
            if ($this->table->isMember('greatest', $id) || $this->table->isMember('first-class-sunday', $id)) {
                return 1;
            }
        }

        return $celebration->rank()->ordinal();
    }

    public function isPrivilegedCommemoration(RealizedObservance $office): bool
    {
        $kind = $office->kind()->value();

        // (a) any Sunday.
        if ($kind === ObservanceKind::SUNDAY) {
            return true;
        }
        // (b) a feast of the first class (a Double of the I class, incl. the great feasts of
        // the Lord).
        if ($this->legacyGradeOf($office) === LegacyRank::DUPLEX_I_CLASSIS) {
            return true;
        }
        if ($this->table->isMember('great-lord', $office->id()->toString())) {
            return true;
        }
        // (c) a feria of Lent or Advent (Passiontide is the close of Lent).
        if ($kind === ObservanceKind::FERIA) {
            return in_array(
                $this->seasonOf($office),
                [Season::ADVENT, Season::LENT, Season::PASSIONTIDE],
                true
            );
        }

        // (d) the September Ember days and (e) the Major Litanies are likewise never omitted
        // (Title III.2), but both are deferred from the current temporal corpus, so no such
        // observance reaches this method yet; they join with the observance.
        return false;
    }

    private function ratesVespersCommemoration(RealizedObservance $office): bool
    {
        // Doubles (and Sundays) have Vespers to commemorate; a fourth-class feria, a simple, a
        // vigil, or a bare commemoration does not.
        return $office->rank()->ordinal() < 4 || $office->kind()->value() === ObservanceKind::SUNDAY;
    }

    private function isTransferable(RealizedObservance $office): bool
    {
        // All Souls is reassigned to the next day when impeded.
        if ($office->kind()->value() === ObservanceKind::OFFICE_OF_THE_DEAD) {
            return true;
        }

        // A great feast of the Lord (a temporal Double I class) is transferred if impeded.
        if ($this->table->isMember('great-lord', $office->id()->toString())) {
            return true;
        }

        // Cum nostra restricts translation to feasts of the FIRST class. An impeded feast of
        // the second class is commemorated (or omitted) in place, never translated — the
        // sharpest departure from the pre-1955 rite, which also moved Doubles of the II class.
        return $this->legacyGradeOf($office) === LegacyRank::DUPLEX_I_CLASSIS
            && $office->kind()->value() === ObservanceKind::FEAST;
    }

    private function admitsNoCommemoration(RealizedObservance $winner, PrecedenceContext $context): bool
    {
        if ($context->isTriduum()) {
            return true;
        }

        // The Office of the Dead (All Souls) is a Requiem: it admits no commemoration.
        if ($winner->kind()->value() === ObservanceKind::OFFICE_OF_THE_DEAD) {
            return true;
        }

        $id = $winner->id()->toString();
        if ($this->isWithinPaschalOctave($id)) {
            return true;
        }

        // Easter and Pentecost themselves admit no commemoration; Christmas, by contrast,
        // admits the octave commemorations, so it is not listed here.
        return $id === 'roman:temporale:paschal:easter' || $id === 'roman:temporale:paschal:pentecost';
    }

    private function isOrdinaryFeria(RealizedObservance $office): bool
    {
        return $office->kind()->value() === ObservanceKind::FERIA && !$this->isGreaterFeria($office);
    }

    /**
     * A greater (major) feria: the ferias of Advent, Lent, and Passiontide, plus the Ember and
     * Rogation days. These are commemorated when impeded; the ordinary green weekdays are
     * omitted. (The privileged first-class ferias — Ash Wednesday, Holy Week — are lifted to
     * their own tier before this is reached.)
     */
    private function isGreaterFeria(RealizedObservance $office): bool
    {
        $kind = $office->kind()->value();
        if ($kind === ObservanceKind::EMBER_DAY || $kind === ObservanceKind::ROGATION_DAY) {
            return true;
        }
        if (in_array($this->seasonOf($office), [Season::ADVENT, Season::LENT, Season::PASSIONTIDE], true)) {
            return true;
        }

        return false;
    }

    private function isFeriaLike(string $kind): bool
    {
        return $kind === ObservanceKind::FERIA
            || $kind === ObservanceKind::EMBER_DAY
            || $kind === ObservanceKind::ROGATION_DAY;
    }

    private function isWithinPaschalOctave(string $id): bool
    {
        return strpos($id, ':easter-octave') !== false
            || strpos($id, ':pentecost-octave') !== false;
    }

    private function isChristmasOctaveWithin(string $id): bool
    {
        return strpos($id, 'christmas:within-octave') !== false;
    }

    /** The pre-1960 grade token of a sanctoral office, or null for a temporal office. */
    private function legacyGradeOf(RealizedObservance $observance): ?string
    {
        if (!$observance instanceof SanctoralObservance) {
            return null;
        }
        $legacyRank = $observance->legacyRank();

        return $legacyRank !== null ? $legacyRank->value() : null;
    }

    private function seasonOf(RealizedObservance $observance): ?string
    {
        return $observance instanceof TemporalObservance ? $observance->season()->value() : null;
    }
}
