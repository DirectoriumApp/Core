<?php

declare(strict_types=1);

namespace Directorium\Core\Precedence;

use DateInterval;
use DateTimeImmutable;
use Directorium\Core\Calendar\RealizedObservance;
use Directorium\Core\Observance\ObservanceKind;
use Directorium\Core\Temporal\Computus;
use Directorium\Core\Temporal\Season;
use Directorium\Core\Temporal\TemporalObservance;
use Directorium\Core\Trace\ResolutionReason;

/**
 * Precedence under the Novus Ordo (Ordinary Form): the **Table of Liturgical Days**
 * of the reformed calendar (Normae universales de Anno Liturgico et de Calendario,
 * 1969, n. 59), read line by line — a lower line wins, exactly like the traditional
 * n. 91 table, but flatter (docs/design/novus-ordo-calendar-model.md, #257).
 *
 * The reformed table is genuinely simpler than the traditional one:
 *
 * - **No commemorations.** When two celebrations occur the higher wins; the loser is
 *   *transferred* (an impeded Solemnity, reusing the transfer ledger — e.g. the
 *   Annunciation out of Holy Week / the Easter octave) or *omitted* (everything else).
 *   {@see commemorationLimit()} and {@see commemorationClassLimit()} are 0 and
 *   {@see tierOf()} never returns a commemoration tier, so the resolver's selector
 *   short-circuits to an empty commemoration list with no special-casing here.
 * - **All Souls stays on a Sunday.** {@see officeOfTheDeadYieldsToSunday()} is false —
 *   2 November sits at line 3 and displaces a Sunday of Ordinary/Christmas Time (line 6).
 * - **No Vespers concurrence.** {@see observesVespersConcurrence()} is false, so the
 *   resolver skips the concurrence pass; {@see concurrenceOutcome()} is a plain
 *   higher-office-holds-the-evening rule for the rare caller that still asks.
 *
 * As with the traditional editions the tier ordinals, the membership id-sets, and the
 * (here empty) limits are corpus data read through {@see PrecedenceTable}; this class
 * holds only the branching logic that maps an observance to its line, so the reformed
 * general calendar and its later national overlays supply their own table with no edit.
 * The rank scale is the Novus-Ordo grade normalised to a {@see \Directorium\Core\Attribute\RankClass}
 * (solemnity 1 / feast 2 / memorial 3 / optional memorial 4).
 *
 * The branching reads three membership sets — `privileged-temporal` (the four unmovable feasts of
 * the Lord), `privileged-temporal-weekday` (Ash Wednesday, the Holy-Week weekdays, and the days of
 * the Octave of Easter), and `feasts-of-the-lord` — and the tiers named in {@see tierOf()}. The
 * shipped edition carries no precedence data yet; only the minimal fixture
 * (tests/fixtures/novus-ordo-temporal) defines these, so the reformed edition is not resolvable
 * through {@see DayResolver} until the real cited Table of Liturgical Days is authored with the
 * sanctoral and validation (#260) — that table MUST define all three sets (in particular
 * `privileged-temporal-weekday`, whose absence would throw the moment a paschal weekday is graded).
 */
final class NovusOrdoPrecedence implements PrecedenceRules
{
    /** The Novus-Ordo edition dir the reformed Table of Liturgical Days is read from. */
    private const EDITION_DIR = 'roman-novus-ordo-2002';

    /** The Normae universales (1969), cited for the reformed precedence facts. */
    private const CITE = 'nu-1969';

    private PrecedenceTable $table;

    public function __construct(?PrecedenceTable $table = null)
    {
        $this->table = $table ?? new PrecedenceTable(null, self::EDITION_DIR);
    }

    public function tierOf(RealizedObservance $observance, PrecedenceContext $context): PrecedenceTier
    {
        $id = $observance->id()->toString();
        $kind = $observance->kind()->value();

        // Line 1: the sacred feria of the Triduum wins outright; a coincident saint keeps its
        // own far lower tier (the kind guard mirrors the 1962 engine).
        if ($context->isTriduum() && $kind === ObservanceKind::FERIA) {
            return $this->table->tier('triduum');
        }

        // Line 3: All Souls (2 Nov). Checked before the rank branches because its kind is not a
        // feast; it stays on a Sunday (officeOfTheDeadYieldsToSunday() is false), so at line 3 it
        // outranks the line-6 Sunday.
        if ($kind === ObservanceKind::OFFICE_OF_THE_DEAD) {
            return $this->table->tier('all-souls');
        }

        // Line 2: the four unmovable feasts of the Lord (Nativity, Epiphany, Ascension,
        // Pentecost), recognised by identity because they are not "solemnities" by grade.
        if ($this->table->isMember('privileged-temporal', $id)) {
            return $this->table->tier('privileged-temporal');
        }

        // Line 2: the privileged temporal weekdays and octave days the reformed table ranks
        // among the highest — Ash Wednesday, the weekdays of Holy Week (Mon–Wed; Holy Thursday
        // is the Triduum, caught above), and the days of the Octave of Easter (each celebrated
        // as a Solemnity of the Lord, n. 24). They are weekdays / days-within-an-octave by kind
        // but privileged by identity, so the table names them in a membership set. This is
        // load-bearing for the Annunciation transfer: {@see forcedTransferDate()} moves the
        // impeded Solemnity only once it is outranked, which needs the Holy-Week / Easter-octave
        // day it lands on to sit at line 2 (above the line-3 Solemnity).
        if ($this->table->isMember('privileged-temporal-weekday', $id)) {
            return $this->table->tier('privileged-temporal');
        }

        $season = $this->seasonOf($observance);

        // Line 9: the days within the Octave of the Nativity (the days within the Octave of
        // Easter are caught above at line 2). A within-octave day is neither a Sunday nor a
        // plain feria, so it is routed here before the sanctoral rank switch.
        if ($kind === ObservanceKind::WITHIN_OCTAVE) {
            return $this->table->tier('privileged-weekday');
        }

        if ($kind === ObservanceKind::SUNDAY) {
            // Line 2: Sundays of Advent, Lent, and Easter — privileged over everything but the
            // Triduum. Line 6: the Sundays of Christmas Time and Ordinary Time.
            //
            // This branch grades by KIND before the rank switch below, so a Solemnity or Feast of
            // the Lord that OCCUPIES a Sunday (Trinity, Corpus Christi, Christ the King, the
            // Baptism, the Holy Family) must be authored kind=feast, not kind=sunday — #108 mints
            // them as movable-feast overlays so the rank switch routes them to line 3 / 5. A
            // green Sunday minted beneath them by OrdinaryTime is correctly line 6.
            return in_array($season, [Season::ADVENT, Season::LENT, Season::EASTERTIDE], true)
                ? $this->table->tier('privileged-temporal')
                : $this->table->tier('sunday');
        }

        if ($kind === ObservanceKind::FERIA) {
            // Line 9: the ordinary weekdays of Lent (Ash Wednesday and the Holy-Week weekdays are
            // caught at line 2 above). Line 13: every other weekday — Ordinary Time, Easter Time,
            // and Christmas Time. The late-Advent 17-24 December weekdays also belong at line 9;
            // sorting them there is a refinement of the real cited Table (#108 sanctoral slice /
            // #260), where the day-set is named — the fixture ranks them at line 13 for now. The
            // line 9-vs-13 distinction only orders a weekday against a lower-ranked office (a
            // memorial, lines 10-12), of which the reformed sanctoral carries none yet, so it
            // cannot change a resolved day until that sanctoral is authored.
            return $season === Season::LENT
                ? $this->table->tier('privileged-weekday')
                : $this->table->tier('weekday');
        }

        // The sanctoral grades, by the normalised class of the Novus-Ordo rank.
        switch ($observance->rank()->ordinal()) {
            case 1: // Solemnity (line 3; proper solemnities at line 4 arrive with national overlays).
                return $this->table->tier('solemnity');
            case 2: // Feast: of the Lord (line 5) outranks a Sunday; of the BVM or a saint (line 7) does not.
                return $this->table->isMember('feasts-of-the-lord', $id)
                    ? $this->table->tier('feast-of-the-lord')
                    : $this->table->tier('feast');
            case 3: // Obligatory memorial (line 10; proper obligatory memorials at line 11 later).
                return $this->table->tier('obligatory-memorial');
            default: // Optional memorial (line 12).
                return $this->table->tier('optional-memorial');
        }
    }

    public function occurrenceOutcome(
        RealizedObservance $winner,
        RealizedObservance $loser,
        PrecedenceContext $context
    ): OccurrenceOutcome {
        return $this->decideOccurrence($loser)[0];
    }

    public function explainOccurrence(
        RealizedObservance $winner,
        RealizedObservance $loser,
        PrecedenceContext $context
    ): ResolutionReason {
        return $this->decideOccurrence($loser)[1];
    }

    /**
     * The reformed occurrence decision: an impeded Solemnity is transferred to a free day;
     * every other impeded office is omitted for the year — the Novus Ordo never commemorates
     * (n. 60). Produced as one decision so the outcome and its cited reason cannot disagree.
     *
     * @return array{0: OccurrenceOutcome, 1: ResolutionReason}
     */
    private function decideOccurrence(RealizedObservance $loser): array
    {
        if ($this->isTransferable($loser)) {
            return [OccurrenceOutcome::transfer(), ResolutionReason::cited(
                'nu60-solemnity-transfer',
                'transferred: an impeded Solemnity is moved to the nearest free day',
                self::CITE . ':60'
            )];
        }

        return [OccurrenceOutcome::omit(), ResolutionReason::cited(
            'nu60-no-commemoration',
            'omitted: the reformed calendar keeps no commemoration, so an impeded lesser office lapses',
            self::CITE . ':60'
        )];
    }

    private function isTransferable(RealizedObservance $office): bool
    {
        // Only a Solemnity is transferred when impeded (by the Triduum, an Easter-octave day,
        // or a privileged Sunday); feasts and memorials simply lapse for that year.
        return $office->rank()->ordinal() === 1;
    }

    public function forcedTransferDate(RealizedObservance $feast, PrecedenceContext $context): ?DateTimeImmutable
    {
        // The Annunciation, impeded into Holy Week or the Easter octave, is kept on the Monday
        // after the Second Sunday of Easter (Easter + 8) — the reformed analogue of n. 96a.
        // Dormant until the Novus-Ordo sanctoral is authored (#108); forward-ready here.
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
        // The reformed Office has no Second-Vespers commemoration: the more dignified office
        // simply holds the evening, an equal or lower preceding office yields to the following
        // day's First Vespers. The resolver skips this pass entirely (observesVespersConcurrence()
        // is false); this stays a total, side-effect-free rule for a direct caller.
        return $this->tierOf($preceding, $context)->isHigherThan($this->tierOf($following, $context))
            ? ConcurrenceOutcome::fullOfPreceding()
            : ConcurrenceOutcome::fullOfFollowing();
    }

    public function commemorationLimit(RealizedObservance $celebration, PrecedenceContext $context): int
    {
        return 0;
    }

    public function commemorationClassLimit(RealizedObservance $celebration): int
    {
        return 0;
    }

    public function isPrivilegedCommemoration(RealizedObservance $office): bool
    {
        return false;
    }

    public function privilegedCommemorationsExemptFromLimit(): bool
    {
        return false;
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
            'nu59-table-of-liturgical-days',
            sprintf('celebrated as the day\'s highest office, %s%s', $where, $named),
            self::CITE . ':59'
        );
    }

    public function explainCommemorationLimit(
        RealizedObservance $celebration,
        PrecedenceContext $context
    ): ResolutionReason {
        return ResolutionReason::cited(
            'nu60-no-commemoration',
            'the reformed calendar admits no commemoration',
            self::CITE . ':60'
        );
    }

    public function explainColour(RealizedObservance $celebration): ResolutionReason
    {
        $colour = $celebration->colour();
        $rose = $colour->roseAllowed() ? ', with rose permitted on Gaudete and Laetare' : '';

        return ResolutionReason::cited(
            'colour-of-celebration',
            sprintf('%s: the liturgical colour of the celebrated office%s', $colour->base()->value(), $rose),
            self::CITE
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
            self::CITE
        );
    }

    public function anticipatesSundayVigils(): bool
    {
        // The reformed calendar omits a vigil that falls on a Sunday; it is not anticipated.
        return false;
    }

    public function officeOfTheDeadYieldsToSunday(): bool
    {
        // All Souls (2 November) IS celebrated on a Sunday in the reformed calendar — it sits at
        // line 3 and displaces the Sunday of Ordinary/Christmas Time. It does not yield.
        return false;
    }

    public function observesVespersConcurrence(): bool
    {
        // The reformed Office gives First Vespers only to Sundays and solemnities, so there is no
        // general evening concurrence to resolve; the resolver skips the pass.
        return false;
    }

    private function seasonOf(RealizedObservance $observance): ?string
    {
        return $observance instanceof TemporalObservance ? $observance->season()->value() : null;
    }
}
