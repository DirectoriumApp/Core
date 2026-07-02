<?php

declare(strict_types=1);

namespace Introibo\Core\Precedence;

use Introibo\Core\Calendar\RealizedObservance;
use Introibo\Core\Observance\ObservanceKind;
use Introibo\Core\Temporal\Season;
use Introibo\Core\Temporal\TemporalObservance;

/**
 * Precedence under the 1962 rubrics (Rubricae 1960 / editio typica 1962).
 *
 * The tier ordinals below ARE the line numbers of the 1960 Table of Liturgical
 * Days (Codex Rubricarum n. 91), so the gaps are meaningful: lines that this
 * edition's data cannot yet tell apart (a "proper" vs a "universal-Church" vs an
 * "indult" feast of the same class — n. 91 lines 12/13, 19/20, 23) collapse onto
 * the universal-Church line, and are refined when the corpus (#38) carries that
 * provenance. Every line the engine CAN detect maps to its exact n. 91 position.
 *
 * The named great feasts (lines 1, 3, 4, 5) are recognised by their canonical
 * {@see \Introibo\Core\Observance\ObservanceId} — Easter and Pentecost are
 * `kind=sunday` yet must not fall into the first-class-Sunday line, so identity
 * is checked before the structural branches. Everything else is derived from
 * kind, class, and (for temporal offices) season. No accessor is added to
 * {@see RealizedObservance}: season is read through a narrow capability check on
 * {@see TemporalObservance}, keeping the shared seam edition-neutral.
 *
 * Sources: the New Rubrics of the Roman Breviary and Missal (1960) n. 91;
 * cross-checked against the SSPX "Classifications of Feasts" transcription.
 */
final class Rubrics1962Precedence implements PrecedenceRules
{
    // First class (n. 91 lines 1–13).
    private const TIER_GREATEST = 1;              // Christmas, Easter, Pentecost
    private const TIER_TRIDUUM = 2;              // the Sacred Triduum
    private const TIER_GREAT_LORD = 3;           // Epiphany, Ascension, and the other great feasts of the Lord
    private const TIER_GREAT_LADY = 4;           // Immaculate Conception, Assumption
    private const TIER_CHRISTMAS_VIGIL_OCTAVE = 5; // Vigil (24 Dec) and Octave-day (1 Jan) of Christmas
    private const TIER_FIRST_SUNDAY = 6;         // Sundays of Advent, Lent, Passiontide, and Low Sunday
    private const TIER_FIRST_FERIA = 7;          // Ash Wednesday and Mon/Tue/Wed of Holy Week
    private const TIER_ALL_SOULS = 8;            // Commemoration of All the Faithful Departed
    private const TIER_PENTECOST_VIGIL = 9;      // Vigil of Pentecost
    private const TIER_PASCHAL_OCTAVE = 10;      // days within the Easter and Pentecost octaves
    private const TIER_FIRST_FEAST = 11;         // other first-class feasts (universal/proper/indult)

    // Second class (n. 91 lines 14–21).
    private const TIER_SECOND_LORD_FEAST = 14;   // second-class feasts of the Lord
    private const TIER_SECOND_SUNDAY = 15;       // second-class Sundays
    private const TIER_SECOND_FEAST = 16;        // other second-class feasts
    private const TIER_CHRISTMAS_OCTAVE = 17;    // days within the octave of Christmas
    private const TIER_SECOND_FERIA = 18;        // greater Advent ferias and the Ember Days
    private const TIER_SECOND_VIGIL = 21;        // second-class vigils

    // Third class (n. 91 lines 22–26).
    private const TIER_LENT_FERIA = 22;          // ferias of Lent and Passiontide (except Ember Days)
    private const TIER_THIRD_FEAST = 24;         // third-class feasts
    private const TIER_ADVENT_FERIA = 25;        // ferias of Advent to 16 December (except Ember Days)
    private const TIER_THIRD_VIGIL = 26;         // third-class vigils

    // Fourth class (n. 91 lines 27–28).
    private const TIER_LADY_ON_SATURDAY = 27;    // the Saturday Office of Our Lady
    private const TIER_FOURTH = 28;              // ferias of the fourth class and commemorations

    /** @var list<string> Line 1: the three greatest feasts (I class with octave). */
    private const GREATEST = [
        'roman:temporale:christmas:nativity',
        'roman:temporale:paschal:easter',
        'roman:temporale:paschal:pentecost',
    ];

    /** @var list<string> Line 3: the other great first-class feasts of the Lord. */
    private const GREAT_LORD = [
        'roman:temporale:epiphany:domini',
        'roman:temporale:paschal:ascension',
        'roman:temporale:paschal:trinity-sunday',
        'roman:temporale:paschal:corpus-christi',
        'roman:temporale:paschal:sacred-heart',
        'roman:temporale:month-computed:christ-the-king',
    ];

    /** @var list<string> Line 4: the two named first-class feasts of Our Lady. */
    private const GREAT_LADY = [
        'roman:sanctorale:immaculata-conceptio',
        'roman:sanctorale:assumptio',
    ];

    /** @var list<string> Line 5: the Vigil and Octave-day of Christmas. */
    private const CHRISTMAS_VIGIL_OCTAVE = [
        'roman:temporale:christmas:vigil',
        'roman:temporale:christmas:octave-day',
    ];

    private const PENTECOST_VIGIL = 'roman:temporale:paschal:pentecost-vigil';

    /** @var list<string> Second-class feasts of the Lord (line 14). */
    private const SECOND_LORD_FEASTS = [
        'roman:temporale:christmas:holy-name',
        'roman:temporale:epiphany:holy-family',
    ];

    public function tierOf(RealizedObservance $observance, PrecedenceContext $context): PrecedenceTier
    {
        $id = $observance->id()->toString();

        // Named great feasts, by identity — checked first because Easter and
        // Pentecost are kind=sunday and must not fall into the Sunday line.
        if (in_array($id, self::GREATEST, true)) {
            return PrecedenceTier::of(self::TIER_GREATEST);
        }
        if ($context->isTriduum()) {
            return PrecedenceTier::of(self::TIER_TRIDUUM);
        }
        if (in_array($id, self::GREAT_LORD, true)) {
            return PrecedenceTier::of(self::TIER_GREAT_LORD);
        }
        if (in_array($id, self::GREAT_LADY, true)) {
            return PrecedenceTier::of(self::TIER_GREAT_LADY);
        }
        if (in_array($id, self::CHRISTMAS_VIGIL_OCTAVE, true)) {
            return PrecedenceTier::of(self::TIER_CHRISTMAS_VIGIL_OCTAVE);
        }

        $kind = $observance->kind()->value();
        $class = $observance->rank()->ordinal();

        if ($class === 1) {
            return $this->firstClassTier($kind, $id);
        }
        if ($class === 2) {
            return $this->secondClassTier($kind, $id);
        }
        if ($class === 3) {
            return $this->thirdClassTier($observance, $kind);
        }

        // Fourth class: the Saturday Office of Our Lady, else ferias and commemorations.
        if ($kind === ObservanceKind::LADY_ON_SATURDAY) {
            return PrecedenceTier::of(self::TIER_LADY_ON_SATURDAY);
        }

        return PrecedenceTier::of(self::TIER_FOURTH);
    }

    private function firstClassTier(string $kind, string $id): PrecedenceTier
    {
        if ($kind === ObservanceKind::SUNDAY) {
            return PrecedenceTier::of(self::TIER_FIRST_SUNDAY);
        }
        if ($kind === ObservanceKind::FERIA) {
            return PrecedenceTier::of(self::TIER_FIRST_FERIA);
        }
        if ($kind === ObservanceKind::OFFICE_OF_THE_DEAD) {
            return PrecedenceTier::of(self::TIER_ALL_SOULS);
        }
        if ($id === self::PENTECOST_VIGIL) {
            return PrecedenceTier::of(self::TIER_PENTECOST_VIGIL);
        }
        if ($this->isWithinPaschalOctave($id)) {
            return PrecedenceTier::of(self::TIER_PASCHAL_OCTAVE);
        }

        return PrecedenceTier::of(self::TIER_FIRST_FEAST);
    }

    private function secondClassTier(string $kind, string $id): PrecedenceTier
    {
        if ($kind === ObservanceKind::FEAST) {
            return in_array($id, self::SECOND_LORD_FEASTS, true)
                ? PrecedenceTier::of(self::TIER_SECOND_LORD_FEAST)
                : PrecedenceTier::of(self::TIER_SECOND_FEAST);
        }
        if ($kind === ObservanceKind::SUNDAY) {
            return PrecedenceTier::of(self::TIER_SECOND_SUNDAY);
        }
        if ($kind === ObservanceKind::WITHIN_OCTAVE || $kind === ObservanceKind::OCTAVE_DAY) {
            return PrecedenceTier::of(self::TIER_CHRISTMAS_OCTAVE);
        }
        if ($kind === ObservanceKind::VIGIL) {
            return PrecedenceTier::of(self::TIER_SECOND_VIGIL);
        }

        // Greater Advent ferias (17–23 Dec) and the Ember Days.
        return PrecedenceTier::of(self::TIER_SECOND_FERIA);
    }

    private function thirdClassTier(RealizedObservance $observance, string $kind): PrecedenceTier
    {
        if ($kind === ObservanceKind::FEAST) {
            return PrecedenceTier::of(self::TIER_THIRD_FEAST);
        }
        if ($kind === ObservanceKind::VIGIL) {
            return PrecedenceTier::of(self::TIER_THIRD_VIGIL);
        }

        // Ferias: Lent/Passiontide (privileged, above third-class feasts) vs Advent.
        if ($this->seasonOf($observance) === Season::ADVENT) {
            return PrecedenceTier::of(self::TIER_ADVENT_FERIA);
        }

        return PrecedenceTier::of(self::TIER_LENT_FERIA);
    }

    public function occurrenceOutcome(
        RealizedObservance $winner,
        RealizedObservance $loser,
        PrecedenceContext $context
    ): OccurrenceOutcome {
        // n. 95: only first-class feasts (and, n. 96b, All Souls) are transferred;
        // every other impeded office is commemorated or omitted.
        if ($this->isTransferable($loser)) {
            return OccurrenceOutcome::transfer();
        }

        // n. 23 / 30 / 66: the Triduum, the days within the Easter and Pentecost
        // octaves, and the first-class vigils admit no commemoration at all.
        if ($this->admitsNoCommemoration($winner, $context)) {
            return OccurrenceOutcome::omit();
        }

        // n. 111(a): a first-class day admits only a privileged commemoration.
        if ($winner->rank()->ordinal() === 1) {
            return $this->isPrivilegedCommemoration($loser)
                ? OccurrenceOutcome::commemorate()
                : OccurrenceOutcome::omit();
        }

        // Second- to fourth-class days admit the loser as a commemoration; the
        // per-day count limit (n. 111b–d / 114) is applied by the resolver (#36).
        return OccurrenceOutcome::commemorate();
    }

    private function isTransferable(RealizedObservance $office): bool
    {
        // All Souls is reassigned to the next day when impeded (n. 96b).
        if ($office->kind()->value() === ObservanceKind::OFFICE_OF_THE_DEAD) {
            return true;
        }

        return $office->rank()->ordinal() === 1
            && $office->kind()->value() === ObservanceKind::FEAST;
    }

    private function admitsNoCommemoration(RealizedObservance $winner, PrecedenceContext $context): bool
    {
        if ($context->isTriduum()) {
            return true;
        }

        $id = $winner->id()->toString();
        if ($this->isWithinPaschalOctave($id)) {
            return true;
        }

        return $id === 'roman:temporale:christmas:vigil' || $id === self::PENTECOST_VIGIL;
    }

    private function isPrivilegedCommemoration(RealizedObservance $office): bool
    {
        $kind = $office->kind()->value();

        // (a) a Sunday; (b) a first-class day.
        if ($kind === ObservanceKind::SUNDAY || $office->rank()->ordinal() === 1) {
            return true;
        }

        // (c) a day within the octave of Christmas.
        if (strpos($office->id()->toString(), 'christmas:within-octave') !== false) {
            return true;
        }

        // (e) a feria of Advent, Lent, or Passiontide.
        if ($kind === ObservanceKind::FERIA) {
            return in_array(
                $this->seasonOf($office),
                [Season::ADVENT, Season::LENT, Season::PASSIONTIDE],
                true
            );
        }

        // (d) the September Ember days and (f) the greater Litanies are added
        // with the data that carries them (#36 / #38).
        return false;
    }

    private function isWithinPaschalOctave(string $id): bool
    {
        return strpos($id, ':easter-octave') !== false
            || strpos($id, ':pentecost-octave') !== false;
    }

    private function seasonOf(RealizedObservance $observance): ?string
    {
        return $observance instanceof TemporalObservance ? $observance->season()->value() : null;
    }
}
