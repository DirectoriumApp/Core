<?php

declare(strict_types=1);

namespace Introibo\Core\Precedence;

use DateInterval;
use DateTimeImmutable;
use Introibo\Core\Calendar\RealizedObservance;
use Introibo\Core\Observance\ObservanceKind;
use Introibo\Core\Temporal\Computus;
use Introibo\Core\Temporal\Season;
use Introibo\Core\Temporal\TemporalObservance;
use Introibo\Core\Trace\ResolutionReason;

/**
 * Precedence under the 1962 rubrics (Rubricae 1960 / editio typica 1962).
 *
 * The tier ordinals ARE the line numbers of the 1960 Table of Liturgical Days
 * (Codex Rubricarum n. 91). They, the named membership sets, and the commemoration
 * limits are now CORPUS DATA read through {@see PrecedenceTable} (#43): this class
 * holds the branching logic (which selector a day maps to), the table holds the
 * facts (which ordinal a selector is, which ids are great feasts, how many
 * commemorations a class admits). A later rules-family supplies its own table with
 * no edit here. The gaps in the ordinals are meaningful: lines this edition's data
 * cannot yet tell apart (a "proper" vs a "universal-Church" vs an "indult" feast of
 * the same class — n. 91 lines 12/13, 19/20, 23) collapse onto the line the engine
 * can detect, and are refined when the corpus (#38) carries that provenance.
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
    private PrecedenceTable $table;

    public function __construct(?PrecedenceTable $table = null)
    {
        $this->table = $table ?? PrecedenceTable::default();
    }

    public function tierOf(RealizedObservance $observance, PrecedenceContext $context): PrecedenceTier
    {
        $id = $observance->id()->toString();

        // A commemoration has no proper office (nn. 106-114): it never celebrates,
        // always yielding the day to a real office (feria, Sunday, feast) and being
        // merely commemorated — or omitted where the day admits none. It therefore
        // takes the lowest tier, below the whole Table of Liturgical Days, so it can
        // never win an occurrence. (Checked first: nothing lifts a commemoration.)
        if ($observance->kind()->value() === ObservanceKind::COMMEMORATION_ONLY) {
            return $this->table->tier('commemoration');
        }

        // Named great feasts, by identity — checked first because Easter and
        // Pentecost are kind=sunday and must not fall into the Sunday line.
        if ($this->table->isMember('greatest', $id)) {
            return $this->table->tier('greatest');
        }
        // The apex 'triduum' tier belongs to the Triduum's own office — a feria of
        // Holy Thursday, Good Friday, or Holy Saturday — not to every observance
        // that merely falls on those dates. A coincident saint keeps its own (far
        // lower) tier so the sacred feria always wins and the saint is omitted
        // (n. 23). Without this kind guard the two shared the apex tier, and the
        // equal-tier tie-break (by id) let a III-class saint displace Holy Thursday.
        if ($context->isTriduum() && $observance->kind()->value() === ObservanceKind::FERIA) {
            return $this->table->tier('triduum');
        }
        if ($this->table->isMember('great-lord', $id)) {
            return $this->table->tier('great-lord');
        }
        if ($this->table->isMember('great-lady', $id)) {
            return $this->table->tier('great-lady');
        }
        if ($this->table->isMember('christmas-vigil-octave', $id)) {
            return $this->table->tier('christmas-vigil-octave');
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
            return $this->table->tier('lady-on-saturday');
        }

        return $this->table->tier('fourth');
    }

    private function firstClassTier(string $kind, string $id): PrecedenceTier
    {
        if ($kind === ObservanceKind::SUNDAY) {
            return $this->table->tier('first-sunday');
        }
        if ($kind === ObservanceKind::FERIA) {
            return $this->table->tier('first-feria');
        }
        if ($kind === ObservanceKind::OFFICE_OF_THE_DEAD) {
            return $this->table->tier('all-souls');
        }
        if ($this->table->isMember('pentecost-vigil', $id)) {
            return $this->table->tier('pentecost-vigil');
        }
        if ($this->isWithinPaschalOctave($id)) {
            return $this->table->tier('paschal-octave');
        }

        return $this->table->tier('first-feast');
    }

    private function secondClassTier(string $kind, string $id): PrecedenceTier
    {
        if ($kind === ObservanceKind::FEAST) {
            return $this->table->isMember('second-lord-feasts', $id)
                ? $this->table->tier('second-lord-feast')
                : $this->table->tier('second-feast');
        }
        if ($kind === ObservanceKind::SUNDAY) {
            return $this->table->tier('second-sunday');
        }
        if ($kind === ObservanceKind::WITHIN_OCTAVE || $kind === ObservanceKind::OCTAVE_DAY) {
            return $this->table->tier('christmas-octave');
        }
        if ($kind === ObservanceKind::VIGIL) {
            return $this->table->tier('second-vigil');
        }

        // Greater Advent ferias (17–23 Dec) and the Ember Days.
        return $this->table->tier('second-feria');
    }

    private function thirdClassTier(RealizedObservance $observance, string $kind): PrecedenceTier
    {
        if ($kind === ObservanceKind::FEAST) {
            return $this->table->tier('third-feast');
        }
        if ($kind === ObservanceKind::VIGIL) {
            return $this->table->tier('third-vigil');
        }

        // Ferias: Lent/Passiontide (privileged, above third-class feasts) vs Advent.
        if ($this->seasonOf($observance) === Season::ADVENT) {
            return $this->table->tier('advent-feria');
        }

        return $this->table->tier('lent-feria');
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
     * The single occurrence decision: the loser's fate AND the cited reason for it,
     * produced together so {@see occurrenceOutcome()} and {@see explainOccurrence()}
     * can never disagree. The branch order is the rubrics' own order of precedence.
     *
     * @return array{0: OccurrenceOutcome, 1: ResolutionReason}
     */
    private function decideOccurrence(
        RealizedObservance $winner,
        RealizedObservance $loser,
        PrecedenceContext $context
    ): array {
        // n. 95: only first-class feasts (and, n. 96b, All Souls) are transferred;
        // every other impeded office is commemorated or omitted.
        if ($this->isTransferable($loser)) {
            if ($loser->kind()->value() === ObservanceKind::OFFICE_OF_THE_DEAD) {
                return [OccurrenceOutcome::transfer(), ResolutionReason::cited(
                    'n96-all-souls-transfer',
                    'transferred: All Souls is kept on the next free day when impeded',
                    'rg-1960:96'
                )];
            }

            return [OccurrenceOutcome::transfer(), ResolutionReason::cited(
                'n95-first-class-transfer',
                'transferred: only a first-class feast is moved to another day when impeded',
                'rg-1960:95'
            )];
        }

        // n. 23 / 30 / 66: the Triduum, the days within the Easter and Pentecost
        // octaves, and the first-class vigils admit no commemoration at all.
        if ($this->admitsNoCommemoration($winner, $context)) {
            return [OccurrenceOutcome::omit(), ResolutionReason::cited(
                'no-commemoration-admitted',
                'omitted: this day admits no commemoration (the Triduum, a privileged octave, or a first-class vigil)',
                'rg-1960:23'
            )];
        }

        // n. 15 / n. 112(b): a feast of the Lord and a Sunday do not commemorate
        // each other — the loser is omitted rather than commemorated. (A feast of
        // Our Lady or a saint on a Sunday still commemorates it.)
        if ($this->lordSundayExclusion($winner, $loser)) {
            return [OccurrenceOutcome::omit(), ResolutionReason::cited(
                'n112-lord-sunday-exclusion',
                'omitted: a feast of the Lord and a Sunday do not commemorate each other',
                'rg-1960:15'
            )];
        }

        // An ordinary feria (not of Advent, Lent, or Passiontide) carries no
        // commemoration when impeded — only privileged ferias are commemorated
        // (n. 108e); the ferial office simply yields to the feast.
        if ($this->isOrdinaryFeria($loser)) {
            return [OccurrenceOutcome::omit(), ResolutionReason::cited(
                'n108-ordinary-feria',
                'omitted: an ordinary feria yields to the feast without a commemoration',
                'rg-1960:108'
            )];
        }

        // n. 111(a): a first-class day admits only a privileged commemoration.
        if ($winner->rank()->ordinal() === 1) {
            return $this->isPrivilegedCommemoration($loser)
                ? [OccurrenceOutcome::commemorate(), ResolutionReason::cited(
                    'n111-first-class-privileged',
                    'commemorated: a first-class day admits a privileged commemoration',
                    'rg-1960:111'
                )]
                : [OccurrenceOutcome::omit(), ResolutionReason::cited(
                    'n111-first-class-privileged-only',
                    'omitted: a first-class day admits only a privileged commemoration',
                    'rg-1960:111'
                )];
        }

        // Second- to fourth-class days admit the loser as a commemoration; the
        // per-day count limit (n. 111b–d / 114) is applied by the resolver (#36).
        return [OccurrenceOutcome::commemorate(), ResolutionReason::cited(
            'commemoration-admitted',
            'commemorated: an impeded office is kept as a commemoration within the celebrated office',
            'rg-1960:112'
        )];
    }

    public function explainPrecedence(RealizedObservance $winner, PrecedenceContext $context): ResolutionReason
    {
        $tier = $this->tierOf($winner, $context);
        $line = $tier->line();
        $selector = $tier->selector();
        $where = $line !== null
            ? sprintf('line %d of the Table of Liturgical Days', $line)
            : 'the Table of Liturgical Days';
        $named = $selector !== null ? sprintf(' (%s)', str_replace('-', ' ', $selector)) : '';

        return ResolutionReason::cited(
            'n91-table-of-liturgical-days',
            sprintf('celebrated as the day\'s highest office, %s%s', $where, $named),
            'rg-1960:91'
        );
    }

    public function explainCommemorationLimit(
        RealizedObservance $celebration,
        PrecedenceContext $context
    ): ResolutionReason {
        if ($this->admitsNoCommemoration($celebration, $context)) {
            return ResolutionReason::cited(
                'no-commemoration-admitted',
                'no commemoration is admitted (the Triduum, a privileged octave, or a first-class vigil)',
                'rg-1960:23'
            );
        }

        $class = $celebration->rank()->ordinal();

        return ResolutionReason::cited(
            'n111-commemoration-limit',
            sprintf('a class %d day admits %d commemoration(s)', $class, $this->table->commemorationLimit($class)),
            'rg-1960:111'
        );
    }

    public function explainColour(RealizedObservance $celebration): ResolutionReason
    {
        $colour = $celebration->colour();
        $rose = $colour->roseAllowed() ? ', with rose permitted on Gaudete and Laetare' : '';

        return ResolutionReason::cited(
            'colour-of-celebration',
            sprintf('%s: the liturgical colour of the celebrated office%s', $colour->base()->value(), $rose),
            'rg-1960'
        );
    }

    public function explainSeason(?string $season): ResolutionReason
    {
        if ($season === null) {
            return ResolutionReason::uncited(
                'no-temporal-season',
                'no temporal office governs the day, so it carries no season'
            );
        }

        return ResolutionReason::cited(
            'season-of-temporal-office',
            sprintf('%s: the season of the day\'s temporal office', $season),
            'rg-1960'
        );
    }

    public function anticipatesSundayVigils(): bool
    {
        // The 1960 rubrics omit a vigil that falls on a Sunday (n. 33); they do not
        // anticipate it to the preceding Saturday.
        return false;
    }

    public function forcedTransferDate(RealizedObservance $feast, PrecedenceContext $context): ?DateTimeImmutable
    {
        // n. 96(a): the Annunciation, impeded into Holy Week or the Easter octave,
        // is kept on the Monday after Low Sunday (Low Sunday is Easter + 7).
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

        // The more dignified office holds the evening; an equal-rank concurrence
        // goes to the following day's First Vespers (a capitulo de sequenti).
        if (!$precedingTier->isHigherThan($followingTier)) {
            return $this->ratesVespersCommemoration($preceding)
                ? ConcurrenceOutcome::followingWithCommemorationOfPreceding()
                : ConcurrenceOutcome::fullOfFollowing();
        }

        return $this->ratesVespersCommemoration($following)
            ? ConcurrenceOutcome::precedingWithCommemorationOfFollowing()
            : ConcurrenceOutcome::fullOfPreceding();
    }

    private function ratesVespersCommemoration(RealizedObservance $office): bool
    {
        // A fourth-class feria carries no Vespers commemoration; higher days do.
        return $office->rank()->ordinal() < 4 || $office->kind()->value() === ObservanceKind::SUNDAY;
    }

    private function isOrdinaryFeria(RealizedObservance $office): bool
    {
        return $office->kind()->value() === ObservanceKind::FERIA
            && !$this->isPrivilegedCommemoration($office);
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

        return $id === 'roman:temporale:christmas:vigil' || $this->table->isMember('pentecost-vigil', $id);
    }

    public function commemorationLimit(RealizedObservance $celebration, PrecedenceContext $context): int
    {
        if ($this->admitsNoCommemoration($celebration, $context)) {
            return 0;
        }

        // The day takes the class of its celebrated office (n. 111b–d).
        return $this->table->commemorationLimit($celebration->rank()->ordinal());
    }

    public function isPrivilegedCommemoration(RealizedObservance $office): bool
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

    private function lordSundayExclusion(RealizedObservance $winner, RealizedObservance $loser): bool
    {
        $winnerSunday = $winner->kind()->value() === ObservanceKind::SUNDAY;
        $loserSunday = $loser->kind()->value() === ObservanceKind::SUNDAY;

        return ($loserSunday && $this->isFeastOfTheLord($winner))
            || ($winnerSunday && $this->isFeastOfTheLord($loser));
    }

    private function isFeastOfTheLord(RealizedObservance $office): bool
    {
        $id = $office->id()->toString();

        return $this->table->isMember('greatest', $id)
            || $this->table->isMember('great-lord', $id)
            || $this->table->isMember('second-lord-feasts', $id);
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
