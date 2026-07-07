<?php

declare(strict_types=1);

namespace Directorium\Core\Temporal;

use DateTimeImmutable;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Observance\ObservanceId;
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

    private TemporalAttributes $attributes;

    /** @var array<string, TemporalObservance> Keyed by 'Y-m-d', in chronological order. */
    private array $days;

    private function __construct(int $year, ?Corpus $corpus = null, ?string $editionDir = null)
    {
        if ($year < Computus::GREGORIAN_REFORM_YEAR) {
            throw new InvalidArgumentException(sprintf(
                'The Time after Pentecost is defined from %d onward; got %d.',
                Computus::GREGORIAN_REFORM_YEAR,
                $year
            ));
        }

        $this->attributes = new TemporalAttributes($corpus, $editionDir);
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

    public static function forYear(int $year, ?Corpus $corpus = null, ?string $editionDir = null): self
    {
        return new self($year, $corpus, $editionDir);
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
        // The two 1954 privileged temporal octaves overlaying the early green weeks are tested
        // first; outside their windows (and for 1962, which declares neither archetype) this is a
        // no-op and the ordinary green Sunday/feria classification runs. Crucially the octave
        // overlay does NOT touch the week computation below, which drives the resumed-Sunday tail.
        $octave = $this->privilegedOctave($date);
        if ($octave !== null) {
            return $octave;
        }

        $week = intdiv(TemporalCalendar::daysBetween($this->trinitySunday, $date), 7) + 1; // 1 … sundayCount

        if (TemporalCalendar::isSunday($date)) {
            return $this->classifySunday($week, $date);
        }

        // The ferial weeks are counted from the octave of Pentecost (Eastertide runs
        // through it); only the Sundays are titled "post Pentecosten".
        return $this->mint(
            'roman:temporale:paschal:pentecost-time:week-' . $week . ':' . TemporalCalendar::feriaToken($date),
            'pentecost-time-feria',
            $date,
            $week
        );
    }

    /**
     * The two 1954 privileged temporal octaves that overlay the early green weeks — the octave
     * of Corpus Christi (2nd order; days within Easter+61..+66, octave day Easter+67) and the
     * octave of the Sacred Heart (3rd order; days within Easter+69..+74, octave day Easter+75).
     * Each feast is day 1 (Corpus Christi Easter+60, the Sacred Heart Easter+68), minted by
     * {@see MovableFeasts}, so the octave proper begins at day 2. The Sunday within each octave
     * (the 2nd Sunday after Pentecost inside the Corpus octave, the 3rd inside the Sacred Heart
     * octave) keeps its own green Sunday office and commemorates the octave. Returns null outside
     * both windows, on a Sunday within, or for an edition that declares neither archetype (1962).
     */
    private function privilegedOctave(DateTimeImmutable $date): ?TemporalObservance
    {
        $offset = TemporalCalendar::daysBetween($this->easter, $date);
        if ($offset >= 61 && $offset <= 67) {
            // Corpus Christi (Easter+60) = day 1, so day-of-octave = offset - 59.
            return $this->octaveOffice(
                $date,
                $offset - 59,
                'corpus-christi',
                'roman:temporale:paschal:corpus-christi-octave'
            );
        }
        if ($offset >= 69 && $offset <= 75) {
            // The Sacred Heart (Easter+68) = day 1, so day-of-octave = offset - 67.
            return $this->octaveOffice(
                $date,
                $offset - 67,
                'sacred-heart',
                'roman:temporale:paschal:sacred-heart-octave'
            );
        }

        return null;
    }

    /**
     * One day of a privileged temporal octave: the octave day (day 8) or a day within (days
     * 2..7, skipping a Sunday, which keeps its own office). `$stem` is the octave's archetype /
     * observance-id stem (`corpus-christi` / `sacred-heart`), `$slug` its observance-id prefix.
     */
    private function octaveOffice(
        DateTimeImmutable $date,
        int $dayOfOctave,
        string $stem,
        string $slug
    ): ?TemporalObservance {
        if ($dayOfOctave === 8 && $this->attributes->has($stem . '-octave-day')) {
            return $this->mint($slug . ':octave-day', $stem . '-octave-day', $date);
        }
        if (
            $dayOfOctave >= 2 && $dayOfOctave <= 7
            && !TemporalCalendar::isSunday($date)
            && $this->attributes->has($stem . '-within-octave')
        ) {
            return $this->mint($slug . ':day-' . $dayOfOctave, $stem . '-within-octave', $date, $dayOfOctave);
        }

        return null;
    }

    private function classifySunday(int $week, DateTimeImmutable $date): TemporalObservance
    {
        // The last Sunday before Advent always takes the 24th ("and last") Mass.
        if ($week === $this->sundayCount) {
            return $this->mint(
                'roman:temporale:paschal:pentecost-time:sunday-ultima',
                'pentecost-time-sunday-ultima',
                $date
            );
        }

        // The first 23 Sundays keep their own "post Pentecosten" Mass (the 1st is
        // the slot the Most Holy Trinity overlays).
        if ($week <= 23) {
            return $this->mint(
                'roman:temporale:paschal:pentecost-time:sunday-' . $week,
                'pentecost-time-sunday',
                $date,
                $week
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
            'pentecost-time-resumed-epiphany',
            $date,
            $epiphanySunday
        );
    }

    /**
     * Mint the temporal office of a Time-after-Pentecost day: the slug is
     * structural, the season is always the green Pentecost season, and the kind,
     * rank, colour, and Latin name come from the corpus archetype overlay,
     * rendered with the day's `$ord` (Sunday / week number) and weekday.
     */
    private function mint(string $slug, string $archetype, DateTimeImmutable $date, int $ord = 0): TemporalObservance
    {
        $office = $this->attributes->archetype($archetype);

        return new TemporalObservance(
            ObservanceId::parse($slug),
            $office->kind(),
            Season::pentecost(),
            $office->rank(),
            $office->colour(),
            $office->renderName($date, $ord)
        );
    }
}
