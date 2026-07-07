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
 * Precedence under the pre-1955 rubrics — the 1954 Divino Afflatu edition
 * (`roman:divino-afflatu`).
 *
 * This is the sibling of {@see Rubrics1962Precedence}: the resolver pipeline is
 * edition-agnostic and asks the rules object every edition-specific question, and
 * this class answers them for the older Tabella Occurrentiae. The tier ordinals,
 * the named membership sets, and the commemoration limits are CORPUS DATA read
 * through {@see PrecedenceTable} against the `roman-divino-afflatu` edition dir
 * (facts/editions/roman-divino-afflatu/precedence.yaml); this class holds only the
 * branching logic (which selector a day maps to) and the occurrence / transfer /
 * concurrence / commemoration decisions.
 *
 * The pre-1955 system differs from 1962 in four load-bearing ways, all verified
 * against the Divino-Afflatu-era Rubricae Generales (rg-da) and cross-checked to
 * the St. Lawrence Press pre-1955 Ordo (ordo-1954):
 *
 *  1. A live six-rung grade ladder — Duplex I classis > Duplex II classis > Duplex
 *     maius > Duplex > Semiduplex > Simplex (the Semiduplex grade the 1955 reform
 *     abolished). Tiers branch on the {@see LegacyRank} token, not the four classes.
 *  2. The Divino Afflatu Sunday elevation — three Sunday classes; a lesser
 *     (per-annum) Sunday yields ONLY to a Double of the I/II class and to a feast
 *     of the Lord, and outranks every ordinary double, semidouble, simple, octave,
 *     vigil, and feria below it (impeded, it is commemorated, never resumed).
 *  3. Transfer restricted to Doubles of the I and II class (Tit. IV §3-4) — every
 *     other impeded office is commemorated or omitted in place, not translated.
 *  4. Free commemoration — no flat integer cap; a privileged commemoration is made
 *     even on a Double of the I class (Tit. VII §1), the sharpest 1954-vs-1962
 *     contrast.
 *
 * Scope is calendar-level (see docs/design/rubric-system-model.md, Seam 6): the
 * office of the day, its rank/colour/season, its commemorations, and the
 * displaced/transferred offices — not the Divine Office casuistry. The DEFERRED
 * items the research left genuinely open (the privileged 2nd/3rd-order temporal
 * octaves, not yet minted for 1954; the fine per-day-type commemoration set; the
 * full-year oracle sweep) are flagged there and not guessed here.
 */
final class Rubrics1954Precedence implements PrecedenceRules
{
    /** The path-safe directory key of the 1954 (Divino Afflatu) edition. */
    private const EDITION_DIR = 'roman-divino-afflatu';

    private PrecedenceTable $table;

    public function __construct(?PrecedenceTable $table = null)
    {
        $this->table = $table ?? new PrecedenceTable(null, self::EDITION_DIR);
    }

    public function tierOf(RealizedObservance $observance, PrecedenceContext $context): PrecedenceTier
    {
        $id = $observance->id()->toString();
        $kind = $observance->kind()->value();

        // The COMMEMORATION_ONLY kind is a 1962 attribute: a saint the 1960 reform reduced
        // to a bare commemoration was, under the pre-1955 rubrics, usually still a real
        // (simplex or higher) office that IS the day on a free feria. So classify a 1954
        // observance by its native grade; only a genuine commemoration — one carrying no
        // grade, or the explicit `commemoratio` grade — takes the floor tier so it can
        // never win an occurrence.
        if ($kind === ObservanceKind::COMMEMORATION_ONLY) {
            $grade = $this->legacyGradeOf($observance);
            if ($grade === null || $grade === LegacyRank::COMMEMORATIO) {
                return $this->table->tier('commemoration');
            }
        }

        // The three greatest feasts, by identity — Easter and Pentecost are kind=sunday
        // and Christmas is kind=feast, so identity is checked before the structural branches.
        if ($this->table->isMember('greatest', $id)) {
            return $this->table->tier('greatest');
        }
        // The Sacred Triduum's own office (a feria of Holy Thursday, Good Friday, or Holy
        // Saturday) holds the apex; a saint merely coincident keeps its far lower tier.
        if ($context->isTriduum() && $kind === ObservanceKind::FERIA) {
            return $this->table->tier('triduum');
        }

        // Sundays: the three Divino Afflatu classes by identity, else the lesser per-annum
        // Sunday. Handled as a block so a Sunday never falls into a grade tier.
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
        // The four 1954 privileged TEMPORAL octaves (#453). They are temporal offices carrying no
        // legacy grade, so they are routed by identity here, before the sanctoral grade ladder.
        // A 2nd-order octave (Epiphany, Corpus Christi) yields ONLY to a Double of the I class —
        // its octave day above its days within — so a transferred lesser feast can never land on
        // one; a 3rd-order octave (Ascension, Sacred Heart) sits low, its days within beside the
        // Christmas octave within and its octave day a plain greater double. All four are always
        // commemorated (never omitted — see occurrenceOutcome / isOctaveOmitted).
        if ($this->isPrivilegedTemporalOctave($id)) {
            return $this->privilegedTemporalOctaveTier($id);
        }
        // The 1954 COMMON temporal octave (the moveable Solemnity of St Joseph, #453). Like the
        // privileged octaves it is a temporal office with no legacy grade, routed by identity: a day
        // within sits at the common-octave-within altitude (semidouble, but omitted under a Double
        // I/II — occurrenceOutcome), its octave day is a plain greater double.
        if ($this->isCommonTemporalOctave($id)) {
            return $this->commonTemporalOctaveTier($id);
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
        // Feasts of the Lord that are themselves Doubles of the I class (Epiphany, the Octave of
        // Christmas, Ascension, Corpus Christi, ...) sit on the I-class line but OUTRANK a I-class
        // feast of a saint — their own tier, a lower subOrder — so on occurrence the Lord's feast
        // is celebrated and the saint transferred. That keeps Corpus Christi / the Sacred Heart on
        // their own day (not transferred off it by a coincident I-class saint), and so keeps their
        // temporal octave attached to its feast (#453).
        if ($this->table->isMember('great-lord', $id)) {
            return $this->table->tier('great-lord');
        }

        // The votive Office of Our Lady on a free Saturday (#453) is minted on the temporal path,
        // so it carries no legacy grade: route it by KIND before the grade ladder, to its own tier
        // ABOVE the Simple — on a free Saturday it is the Office of the day and a coincident Simple
        // is commemorated under it (Rubr. Gen., De Officio S. Mariae in Sabbato). It still yields to
        // every Semidouble-or-higher and every privileged feria / vigil / Ember day; a displaced
        // lady office is DROPPED, not commemorated (decideOccurrence).
        if ($kind === ObservanceKind::LADY_ON_SATURDAY) {
            return $this->table->tier('lady-on-saturday');
        }

        // The sanctoral grade ladder, by legacy token. (Common-octave days-within carry
        // `semiduplex`, common-octave days `duplex-maius`, simple-octave days `simplex`, and
        // vigils `vigilia`, so they fall onto the matching grade tier — their special
        // occurrence behaviour lives in occurrenceOutcome(), not the tier.)
        $grade = $this->legacyGradeOf($observance);
        if ($grade === LegacyRank::DUPLEX_I_CLASSIS) {
            return $this->table->tier('double-i-class');
        }
        if ($grade === LegacyRank::DUPLEX_II_CLASSIS) {
            return $this->table->tier('double-ii-class');
        }
        // A feast of the Lord below the Double II class (an ordinary or greater double of the
        // Lord — the Holy Name, the Holy Family) still takes a LESSER Sunday's place.
        if ($this->table->isMember('feasts-of-the-lord', $id)) {
            return $this->table->tier('feast-of-the-lord');
        }
        // A moveable feast that is a greater double of a saint / the BVM, not the Lord — the
        // Passiontide Seven Sorrows (#453). It is temporal (no legacy grade), and its 1962-mapped
        // numeric rank (III) cannot tell a greater double from an ordinary double, so it is named
        // to the greater-double tier by identity.
        if ($this->table->isMember('moveable-greater-double', $id)) {
            return $this->table->tier('greater-double');
        }
        if ($grade === LegacyRank::DUPLEX_MAIUS) {
            return $this->table->tier('greater-double');
        }
        if ($grade === LegacyRank::DUPLEX) {
            return $this->table->tier('double');
        }
        if ($grade === LegacyRank::SEMIDUPLEX) {
            return $this->table->tier('semidouble');
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

        // A day within the (temporal, privileged 3rd-order) Octave of Christmas — always
        // commemorated, so it sits below the semidouble feasts that displace it.
        if ($this->isChristmasOctaveWithin($id)) {
            return $this->table->tier('christmas-octave-within');
        }
        if ($this->isFeriaLike($kind)) {
            return $this->feriaTier($observance);
        }

        // Safety net: a temporal office without a legacy grade that escaped the membership
        // sets maps by its normalised class. Every office in the current 1954 data is covered
        // by a branch above; this keeps tierOf() total against a future addition.
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
     * pre-1955 order of precedence.
     *
     * @return array{0: OccurrenceOutcome, 1: ResolutionReason}
     */
    private function decideOccurrence(
        RealizedObservance $winner,
        RealizedObservance $loser,
        PrecedenceContext $context
    ): array {
        // Tit. IV §3-4: ONLY a Double of the I or II class (and All Souls) is translated to
        // the next free day; every other impeded office is commemorated or omitted in place.
        if ($this->isTransferable($loser)) {
            if ($loser->kind()->value() === ObservanceKind::OFFICE_OF_THE_DEAD) {
                return [OccurrenceOutcome::transfer(), ResolutionReason::cited(
                    'da-all-souls-transfer',
                    'transferred: All Souls is kept on the next free day when impeded',
                    'rg-da'
                )];
            }

            return [OccurrenceOutcome::transfer(), ResolutionReason::cited(
                'da-double-transfer',
                'transferred: a Double of the I or II class is moved to the next free day when impeded',
                'rg-da'
            )];
        }

        // The Triduum, the privileged (Easter/Pentecost) octaves, and Easter and Pentecost
        // themselves admit no commemoration at all.
        if ($this->admitsNoCommemoration($winner, $context)) {
            return [OccurrenceOutcome::omit(), ResolutionReason::cited(
                'da-no-commemoration-admitted',
                'omitted: this day admits no commemoration (the Triduum or a privileged octave)',
                'rg-da'
            )];
        }

        // A common octave is omitted under a Double of the I or II class; a simple octave is
        // omitted under a Double of the I class. Otherwise the octave is commemorated.
        if ($this->isOctaveOmitted($winner, $loser)) {
            return [OccurrenceOutcome::omit(), ResolutionReason::cited(
                'da-octave-omitted',
                'omitted: the octave is suppressed under a Double of the I/II class',
                'rg-da'
            )];
        }

        // An ordinary (minor) feria yields to the feast with no commemoration; only the
        // greater ferias (Advent/Lent/Passiontide, Ember, Rogation) are commemorated.
        if ($this->isOrdinaryFeria($loser)) {
            return [OccurrenceOutcome::omit(), ResolutionReason::cited(
                'da-ordinary-feria',
                'omitted: an ordinary feria yields to the feast without a commemoration',
                'rg-da'
            )];
        }

        // A displaced votive Office of Our Lady on Saturday is DROPPED, not commemorated: it is
        // not a feast of the saints of the day, so when a Semidouble-or-higher — or a privileged
        // feria / vigil / Ember day — takes the Saturday, the lady office simply yields (#453).
        if ($loser->kind()->value() === ObservanceKind::LADY_ON_SATURDAY) {
            return [OccurrenceOutcome::omit(), ResolutionReason::cited(
                'da-lady-on-saturday-dropped',
                'omitted: the votive Office of Our Lady on Saturday yields without a commemoration',
                'rg-da'
            )];
        }

        // Everything else impeded is commemorated: the pre-1955 rite commemorates freely, and
        // a privileged commemoration is kept even on a Double of the I class (Tit. VII §1).
        return [OccurrenceOutcome::commemorate(), ResolutionReason::cited(
            'da-commemoration-admitted',
            'commemorated: an impeded office is kept as a commemoration within the celebrated office',
            'rg-da'
        )];
    }

    public function explainPrecedence(RealizedObservance $winner, PrecedenceContext $context): ResolutionReason
    {
        $tier = $this->tierOf($winner, $context);
        $selector = $tier->selector();
        $named = $selector !== null ? sprintf(' (%s)', str_replace('-', ' ', $selector)) : '';

        return ResolutionReason::cited(
            'da-tabella-occurrentiae',
            sprintf('celebrated as the day\'s highest office in the pre-1955 order of precedence%s', $named),
            'rg-da'
        );
    }

    public function explainCommemorationLimit(
        RealizedObservance $celebration,
        PrecedenceContext $context
    ): ResolutionReason {
        if ($this->admitsNoCommemoration($celebration, $context)) {
            return ResolutionReason::cited(
                'da-no-commemoration-admitted',
                'no commemoration is admitted (the Triduum or a privileged octave)',
                'rg-da'
            );
        }

        $limit = $this->limitFor($celebration);

        return ResolutionReason::cited(
            'da-commemoration-limit',
            sprintf('the pre-1955 rite admits up to %d commemorations (the odd-orations bound)', $limit),
            'rg-da'
        );
    }

    public function explainColour(RealizedObservance $celebration): ResolutionReason
    {
        $colour = $celebration->colour();
        $rose = $colour->roseAllowed() ? ', with rose permitted on Gaudete and Laetare' : '';

        return ResolutionReason::cited(
            'da-colour-of-celebration',
            sprintf('%s: the liturgical colour of the celebrated office%s', $colour->base()->value(), $rose),
            'rg-da'
        );
    }

    public function explainSeason(?string $season): ResolutionReason
    {
        if ($season === null) {
            return ResolutionReason::uncited(
                'da-no-temporal-season',
                'no temporal office governs the day, so it carries no season'
            );
        }

        return ResolutionReason::cited(
            'da-season-of-temporal-office',
            sprintf('%s: the season of the day\'s temporal office', $season),
            'rg-da'
        );
    }

    public function anticipatesSundayVigils(): bool
    {
        // A common vigil that falls on a Sunday is anticipated to the preceding Saturday
        // (CE "Eve of a Feast"; ordo-1954) — the pre-1955 rule the 1955 reform changed to
        // omission.
        return true;
    }

    public function officeOfTheDeadYieldsToSunday(): bool
    {
        // As under 1962: All Souls is never sung on a Sunday, so its Office and Mass are
        // anticipated/deferred to the next free day (3 November when 2 November is a Sunday).
        return true;
    }

    public function forcedTransferDate(RealizedObservance $feast, PrecedenceContext $context): ?DateTimeImmutable
    {
        // The Annunciation, impeded into Holy Week or the Easter octave, is kept on the Monday
        // after Low Sunday (Low Sunday is Easter + 7) — the same fixed landing as 1962.
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
        // following day's First Vespers "a capitulo de sequenti", commemorating the preceding
        // (Tit. VI §4). The finer split is deferred to the Office layer.
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
        // The pre-1955 odd-orations bound: three admitted commemorations for every class.
        return $this->table->commemorationLimit($celebration->rank()->ordinal());
    }

    public function isPrivilegedCommemoration(RealizedObservance $office): bool
    {
        $kind = $office->kind()->value();

        // A Sunday, and a Double of the I or II class (incl. the great feasts of the Lord).
        if ($kind === ObservanceKind::SUNDAY) {
            return true;
        }
        $grade = $this->legacyGradeOf($office);
        if ($grade === LegacyRank::DUPLEX_I_CLASSIS || $grade === LegacyRank::DUPLEX_II_CLASSIS) {
            return true;
        }
        if ($this->table->isMember('great-lord', $office->id()->toString())) {
            return true;
        }

        // A day within the octave of Christmas.
        if (strpos($office->id()->toString(), 'christmas:within-octave') !== false) {
            return true;
        }

        // A day within (or the octave day of) a privileged temporal octave (Epiphany, Ascension,
        // Corpus Christi, Sacred Heart): the octave is always commemorated, so its commemoration
        // is privileged and survives even on a Double of the I class.
        if ($this->isPrivilegedTemporalOctave($office->id()->toString())) {
            return true;
        }

        // A greater (privileged) feria of Advent, Lent, or Passiontide.
        if ($kind === ObservanceKind::FERIA) {
            return in_array(
                $this->seasonOf($office),
                [Season::ADVENT, Season::LENT, Season::PASSIONTIDE],
                true
            );
        }

        return false;
    }

    public function privilegedCommemorationsExemptFromLimit(): bool
    {
        // The pre-1955 odd-orations bound of three caps every commemoration alike; a
        // privileged commemoration is kept because the cap is generous, not exempt.
        return false;
    }

    private function ratesVespersCommemoration(RealizedObservance $office): bool
    {
        // Doubles and semidoubles (and Sundays) have Vespers to commemorate; a fourth-class
        // feria, a simple, or a vigil does not.
        return $office->rank()->ordinal() < 4 || $office->kind()->value() === ObservanceKind::SUNDAY;
    }

    private function isTransferable(RealizedObservance $office): bool
    {
        // All Souls is reassigned to the next day when impeded.
        if ($office->kind()->value() === ObservanceKind::OFFICE_OF_THE_DEAD) {
            return true;
        }

        // Only a Double of the I or II class is translated. A great feast of the Lord (a
        // temporal Double I class) is likewise transferred if ever impeded.
        if ($this->table->isMember('great-lord', $office->id()->toString())) {
            return true;
        }

        $grade = $this->legacyGradeOf($office);

        return ($grade === LegacyRank::DUPLEX_I_CLASSIS || $grade === LegacyRank::DUPLEX_II_CLASSIS)
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

        // Easter and Pentecost themselves admit no commemoration (until Vespers of Tuesday);
        // Christmas, by contrast, admits the octave commemorations, so it is not listed here.
        return $id === 'roman:temporale:paschal:easter' || $id === 'roman:temporale:paschal:pentecost';
    }

    /**
     * A sanctoral octave loser omitted (not commemorated) under a higher feast: a common
     * octave is suppressed under a Double of the I OR II class; a simple octave under a Double
     * of the I class. The temporal (privileged 3rd-order) Christmas octave carries no legacy
     * grade, so it is never caught here — it is always commemorated.
     */
    private function isOctaveOmitted(RealizedObservance $winner, RealizedObservance $loser): bool
    {
        // The moveable Solemnity of St Joseph's octave is minted on the temporal path, so its days
        // within are TemporalObservances, not SanctoralObservances, yet a COMMON octave's day within
        // is OMITTED (not commemorated) under a Double of the I or II class — exactly the sanctoral
        // common-octave rule below. Its octave DAY, a full greater-double office, is commemorated not
        // omitted (the days-within-vs-octave-day asymmetry; the octave day falls through to the
        // commemorate outcome). The privileged temporal octaves, by contrast, are never omitted.
        if ($this->isCommonTemporalOctaveWithin($loser->id()->toString())) {
            return $this->isDoubleFirstClass($winner) || $this->isDoubleSecondClass($winner);
        }

        if (!$loser instanceof SanctoralObservance) {
            return false;
        }
        $loserKind = $loser->kind()->value();
        if ($loserKind !== ObservanceKind::WITHIN_OCTAVE && $loserKind !== ObservanceKind::OCTAVE_DAY) {
            return false;
        }

        $winnerIsDoubleI = $this->isDoubleFirstClass($winner);
        $simpleOctave = $this->legacyGradeOf($loser) === LegacyRank::SIMPLEX;

        // Simple octave: only a Double I class suppresses it. Common octave: a Double I OR II.
        return $simpleOctave ? $winnerIsDoubleI : ($winnerIsDoubleI || $this->isDoubleSecondClass($winner));
    }

    private function isDoubleFirstClass(RealizedObservance $office): bool
    {
        return $this->legacyGradeOf($office) === LegacyRank::DUPLEX_I_CLASSIS
            || $this->table->isMember('great-lord', $office->id()->toString());
    }

    private function isDoubleSecondClass(RealizedObservance $office): bool
    {
        return $this->legacyGradeOf($office) === LegacyRank::DUPLEX_II_CLASSIS;
    }

    private function isOrdinaryFeria(RealizedObservance $office): bool
    {
        return $office->kind()->value() === ObservanceKind::FERIA && !$this->isGreaterFeria($office);
    }

    /**
     * A greater (major) feria: the ferias of Advent, Lent, and Passiontide, plus the Ember
     * and Rogation days. These are commemorated when impeded; the ordinary green weekdays are
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

        // The greater ferias are EXACTLY the Advent/Lent/Passiontide ferias plus the Ember
        // and Rogation days handled above; every other feria (Christmastide, Eastertide,
        // per-annum) is ordinary and omitted when impeded. No rank-based fallback, so a
        // future privileged feria cannot be silently commemorated by rank alone (#64).
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

    /**
     * Whether an id is a day of one of the four 1954 privileged temporal octaves (Epiphany,
     * Ascension, Corpus Christi, Sacred Heart) — a day within or the octave day. Temporal offices
     * carry no legacy grade, so they are recognised by id, which is disjoint from every sanctoral
     * id and from the octaves' own bearing feasts.
     */
    private function isPrivilegedTemporalOctave(string $id): bool
    {
        return $this->isSecondOrderTemporalOctave($id) || $this->isThirdOrderTemporalOctave($id);
    }

    /** The 2nd-order privileged octaves: Epiphany and Corpus Christi. */
    private function isSecondOrderTemporalOctave(string $id): bool
    {
        return strpos($id, ':epiphany:within-octave') !== false
            || strpos($id, ':epiphany:octave-day') !== false
            || strpos($id, ':corpus-christi-octave:') !== false;
    }

    /** The 3rd-order privileged octaves: the Ascension and the Sacred Heart. */
    private function isThirdOrderTemporalOctave(string $id): bool
    {
        return strpos($id, ':ascension-octave:') !== false
            || strpos($id, ':sacred-heart-octave:') !== false;
    }

    /**
     * The tier of a privileged temporal-octave day. A 2nd-order octave (Epiphany, Corpus Christi)
     * yields only to a Double of the I class: its octave day above its days within. A 3rd-order
     * octave (Ascension, Sacred Heart) sits low — its days within beside the Christmas octave
     * within, its octave day a plain greater double. The `:octave-day` suffix distinguishes the
     * octave day from a `:day-N` / `:within-octave:day-N` day within.
     */
    private function privilegedTemporalOctaveTier(string $id): PrecedenceTier
    {
        $isOctaveDay = strpos($id, ':octave-day') !== false;
        if ($this->isSecondOrderTemporalOctave($id)) {
            return $this->table->tier($isOctaveDay ? 'second-order-octave-day' : 'second-order-octave-within');
        }

        return $this->table->tier($isOctaveDay ? 'greater-double' : 'third-order-octave-within');
    }

    /**
     * Whether an id is a day of the one 1954 COMMON temporal octave — the moveable Solemnity of St
     * Joseph (#453). Unlike the four privileged octaves, a common octave's day within is omitted
     * under a Double of the I/II class ({@see isOctaveOmitted}), so it needs its own routing. A
     * common octave is minted only on the temporal path (a moveable feast, Easter-anchored); the
     * fixed-date common octaves (John Baptist, Peter & Paul, the Assumption) are sanctoral DATA.
     */
    private function isCommonTemporalOctave(string $id): bool
    {
        return strpos($id, ':solemnitas-ioseph-octave:') !== false;
    }

    /** A day WITHIN the common temporal octave (not its octave day). */
    private function isCommonTemporalOctaveWithin(string $id): bool
    {
        return $this->isCommonTemporalOctave($id) && strpos($id, ':octave-day') === false;
    }

    /**
     * The tier of a common temporal-octave day. The octave DAY (Easter+24) is a greater double; a
     * day WITHIN is a semidouble at the common-octave-within altitude — below the privileged
     * 3rd-order octave within, because a common octave is the weaker of the two (its days within are
     * omitted, not commemorated, under a Double I/II).
     */
    private function commonTemporalOctaveTier(string $id): PrecedenceTier
    {
        return strpos($id, ':octave-day') !== false
            ? $this->table->tier('greater-double')
            : $this->table->tier('common-octave-within');
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
