<?php

declare(strict_types=1);

namespace Introibo\Core\Temporal;

use DateTimeImmutable;
use Introibo\Core\Attribute\Colour;
use Introibo\Core\Attribute\ElementColour;
use Introibo\Core\Attribute\RankClass;
use Introibo\Core\Observance\ObservanceId;
use Introibo\Core\Observance\ObservanceKind;
use InvalidArgumentException;

/**
 * The temporal skeleton of the Time after Pentecost — the long green season from
 * Trinity Sunday to the eve of Advent, the "Tempus per annum post Pentecosten".
 *
 * `forYear($year)` fills every day from the first Sunday after Pentecost (Trinity
 * Sunday, Easter+56) up to the First Sunday of Advent under the 1960 rubrics.
 * `$year` is the year Easter falls (Advent of that same year closes the season).
 * Eastertide (#20) hands off at Trinity Sunday. Everything is the green
 * {@see Season::pentecost()} season.
 *
 * The number of Sundays after Pentecost varies with the date of Easter (an early
 * Easter gives a long green season, a late Easter a short one). Two rules make
 * the count come out right for every year: the **last** Sunday before Advent
 * always takes the "24th and last" Mass, and when a year has more than 24
 * Sundays, the **Sundays after Epiphany that were crowded out before
 * Septuagesima are resumed** here, filling the slots between the 23rd Sunday and
 * the last. So the leftover Epiphany Sundays are never dropped — they reappear
 * with their own office while the count of Sundays after Pentecost stays
 * continuous.
 *
 * This is the *temporal skeleton*: the Easter-anchored feasts that overlay the
 * early weeks (the Most Holy Trinity, Corpus Christi, the Sacred Heart) and the
 * month-computed September Ember days are deferred to the movable-feast (#22) and
 * later passes; the skeleton emits the underlying green Sunday or feria beneath
 * them. See docs/design/temporal-fill-model.md.
 */
final class TimeAfterPentecost
{
    /** The last Sunday before Advent always takes this Mass, however many Sundays the year has. */
    private const LAST_MASS_NUMBER = 24;

    /** The Sundays after Epiphany the Missal provides, any surplus of which is resumed here. */
    private const EPIPHANY_SUNDAYS_AVAILABLE = 6;

    private int $year;

    private DateTimeImmutable $easter;

    private DateTimeImmutable $trinitySunday;

    private DateTimeImmutable $firstSundayOfAdvent;

    /** Sundays after Pentecost this year (Trinity Sunday through the last before Advent). */
    private int $sundayCount;

    /** @var array<string, TemporalObservance> Keyed by 'Y-m-d', in chronological order. */
    private array $days;

    private function __construct(int $year)
    {
        if ($year < Computus::GREGORIAN_REFORM_YEAR) {
            throw new InvalidArgumentException(sprintf(
                'The Time after Pentecost is defined from %d onward; got %d.',
                Computus::GREGORIAN_REFORM_YEAR,
                $year
            ));
        }

        $skeleton = PaschalSkeleton::forYear($year);
        $this->year = $year;
        $this->easter = $skeleton->easter();
        $this->trinitySunday = $skeleton->date('trinity-sunday');
        $this->firstSundayOfAdvent = ChristmasCycle::firstSundayOfAdvent($year);

        $span = TemporalCalendar::daysBetween($this->trinitySunday, $this->firstSundayOfAdvent);
        $this->sundayCount = intdiv($span, 7); // both are Sundays, so this is a whole number of weeks

        $this->days = [];
        $advent = $this->firstSundayOfAdvent;
        for ($date = $this->trinitySunday; $date < $advent; $date = TemporalCalendar::addDays($date, 1)) {
            $this->days[$date->format('Y-m-d')] = $this->classify($date);
        }
    }

    public static function forYear(int $year): self
    {
        return new self($year);
    }

    public function year(): int
    {
        return $this->year;
    }

    public function easter(): DateTimeImmutable
    {
        return $this->easter;
    }

    /** Trinity Sunday: the first Sunday after Pentecost, and the first day of this block. */
    public function trinitySunday(): DateTimeImmutable
    {
        return $this->trinitySunday;
    }

    /** The First Sunday of Advent: the first day NOT in this block. */
    public function endsBefore(): DateTimeImmutable
    {
        return $this->firstSundayOfAdvent;
    }

    /** How many Sundays after Pentecost this year has (23–28). */
    public function sundaysAfterPentecost(): int
    {
        return $this->sundayCount;
    }

    /** How many Sundays after Epiphany are resumed before the last Sunday (0 when the year has ≤ 24). */
    public function resumedSundays(): int
    {
        return max(0, $this->sundayCount - self::LAST_MASS_NUMBER);
    }

    /**
     * Every filled day, keyed by 'Y-m-d', in chronological order.
     *
     * @return array<string, TemporalObservance>
     */
    public function days(): array
    {
        return $this->days;
    }

    /** The temporal office of one day, or null if the date is outside this block. */
    public function on(DateTimeImmutable $date): ?TemporalObservance
    {
        return $this->days[$date->format('Y-m-d')] ?? null;
    }

    private function classify(DateTimeImmutable $date): TemporalObservance
    {
        $week = intdiv(TemporalCalendar::daysBetween($this->trinitySunday, $date), 7) + 1; // 1 … sundayCount

        if (TemporalCalendar::isSunday($date)) {
            return $this->classifySunday($week);
        }

        // The ferial weeks are counted from the octave of Pentecost (Eastertide runs
        // through it); only the Sundays are titled "post Pentecosten".
        $phrase = 'infra Hebdomadam ' . TemporalCalendar::roman($week) . ' post Octavam Pentecostes';

        return $this->mint(
            'roman:temporale:paschal:pentecost-time:week-' . $week . ':' . TemporalCalendar::feriaToken($date),
            ObservanceKind::FERIA,
            RankClass::classIV(),
            TemporalCalendar::feriaLatin($date, $phrase)
        );
    }

    private function classifySunday(int $week): TemporalObservance
    {
        // The last Sunday before Advent always takes the 24th ("and last") Mass.
        if ($week === $this->sundayCount) {
            return $this->mint(
                'roman:temporale:paschal:pentecost-time:sunday-ultima',
                ObservanceKind::SUNDAY,
                RankClass::classII(),
                'Dominica ultima post Pentecosten'
            );
        }

        // The first 23 Sundays keep their own "post Pentecosten" Mass (the 1st is
        // the slot the Most Holy Trinity overlays).
        if ($week <= 23) {
            return $this->mint(
                'roman:temporale:paschal:pentecost-time:sunday-' . $week,
                ObservanceKind::SUNDAY,
                RankClass::classII(),
                'Dominica ' . TemporalCalendar::roman($week) . ' post Pentecosten'
            );
        }

        // Beyond the 23rd and before the last: a resumed Sunday after Epiphany.
        // The resumed Sundays end with the VIth (on the second-to-last Sunday) and
        // count backward, so a year with N resumed slots revives the LAST N Sundays
        // after Epiphany (… IV, V, VI); any earlier crowded-out ones simply lapse.
        $resumedOrdinal = $week - 23;                        // 1st, 2nd, … resumed Sunday
        $epiphanySunday = self::EPIPHANY_SUNDAYS_AVAILABLE - $this->resumedSundays() + $resumedOrdinal;

        return $this->mint(
            'roman:temporale:paschal:pentecost-time:resumed-epiphany-' . $epiphanySunday,
            ObservanceKind::SUNDAY,
            RankClass::classII(),
            'Dominica ' . TemporalCalendar::roman($epiphanySunday) . ' quae superfuit post Epiphaniam'
        );
    }

    private function mint(string $slug, string $kind, RankClass $rank, string $latinName): TemporalObservance
    {
        return new TemporalObservance(
            ObservanceId::parse($slug),
            ObservanceKind::fromString($kind),
            Season::pentecost(),
            $rank,
            ElementColour::of(Colour::green()),
            $latinName
        );
    }
}
