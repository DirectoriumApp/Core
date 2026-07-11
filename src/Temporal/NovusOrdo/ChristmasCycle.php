<?php

declare(strict_types=1);

namespace Directorium\Core\Temporal\NovusOrdo;

use DateTimeImmutable;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Observance\ObservanceId;
use Directorium\Core\Temporal\ChristmasCycle as TraditionalChristmasCycle;
use Directorium\Core\Temporal\Computus;
use Directorium\Core\Temporal\Season;
use Directorium\Core\Temporal\TemporalAttributes;
use Directorium\Core\Temporal\TemporalCalendar;
use Directorium\Core\Temporal\TemporalObservance;
use InvalidArgumentException;

/**
 * The temporal skeleton of the Novus-Ordo **Advent → Christmas Time** block — the
 * reform's counterpart of the traditional {@see TraditionalChristmasCycle}, running
 * from the First Sunday of Advent through the Baptism of the Lord.
 *
 * `forYear($year)` fills every day from the First Sunday of Advent of `$year` through
 * the **Baptism of the Lord** (the Sunday after 6 January of `$year + 1`, the last day
 * of Christmas Time); Ordinary Time then opens the following Monday, so this cycle and
 * {@see OrdinaryTime} tile the year with neither overlap nor gap. As in the traditional
 * cycle, `$year` is the civil year Advent begins and the Nativity falls; the octave day,
 * Epiphany, and the Baptism fall in `$year + 1`.
 *
 * What the reform re-shaped, relative to the tradition, is carried here:
 *
 *   * **Advent** keeps four violet Sundays (Gaudete rose on the third) but drops the
 *     Ember days and the "infra Hebdomadam" weekday phrasing, and from **17–24 December**
 *     names the privileged ferias by date ("Die 17 decembris" …, the O-antiphon days).
 *   * **Christmas Time** is one continuous white season from the Nativity to the Baptism —
 *     no distinct green time after the Epiphany. It carries the Christmas octave (26–31
 *     December, likewise date-named), the **Holy Family** on the Sunday within the octave
 *     (or 30 December when the octave has no Sunday), the octave day (1 January) as a
 *     white solemnity, the optional **Second Sunday after the Nativity** (a 1969+ Sunday,
 *     when one falls 2–5 January), the Epiphany, and the weekdays after it up to the
 *     Baptism.
 *
 * This is the *temporal skeleton* only: the sanctoral of the Christmas octave (Stephen,
 * John, the Holy Innocents), the Marian dedication of 1 January, and precedence are
 * composed on top by later passes. The office facts — kind, rank, colour, Latin name —
 * are read from the edition's temporal archetypes; only the date arithmetic and the
 * structural slug stay in code. The Advent anchor and the Baptism rule are edition-neutral,
 * so this reuses {@see TraditionalChristmasCycle::firstSundayOfAdvent()} and
 * {@see OrdinaryTime::baptismOfTheLord()} unchanged; only the archetype overlay is
 * Novus-Ordo.
 *
 * @internal Not part of the public API (docs/api-stability.md).
 */
final class ChristmasCycle
{
    private DateTimeImmutable $firstSunday;

    private DateTimeImmutable $christmas;

    private DateTimeImmutable $octaveDay;

    private DateTimeImmutable $epiphany;

    private DateTimeImmutable $baptism;

    /** The first date named by calendar day rather than by week — 17 December. */
    private DateTimeImmutable $privilegedFrom;

    /** The Holy Family: the Sunday within the Christmas octave, or 30 December if none. */
    private DateTimeImmutable $holyFamily;

    /** The Second Sunday after the Nativity — a Sunday falling 2–5 January, or null. */
    private ?DateTimeImmutable $secondSundayAfterChristmas;

    private TemporalAttributes $attributes;

    /** @var array<string, TemporalObservance> Keyed by 'Y-m-d', in chronological order. */
    private array $days;

    private function __construct(int $year, ?Corpus $corpus = null, ?string $editionDir = null)
    {
        if ($year < Computus::GREGORIAN_REFORM_YEAR) {
            throw new InvalidArgumentException(sprintf(
                'The Novus-Ordo Christmas cycle is defined from %d onward; got %d.',
                Computus::GREGORIAN_REFORM_YEAR,
                $year
            ));
        }

        $this->attributes = new TemporalAttributes($corpus, $editionDir);
        $this->firstSunday = TraditionalChristmasCycle::firstSundayOfAdvent($year);
        $this->christmas = TemporalCalendar::utcDate($year, 12, 25);
        $this->octaveDay = TemporalCalendar::utcDate($year + 1, 1, 1);
        $this->epiphany = TemporalCalendar::utcDate($year + 1, 1, 6);
        $this->baptism = OrdinaryTime::baptismOfTheLord($year + 1);
        $this->privilegedFrom = TemporalCalendar::utcDate($year, 12, 17);
        $this->holyFamily = self::computeHolyFamily($year);
        $this->secondSundayAfterChristmas = self::computeSecondSundayAfterChristmas($year);

        $this->days = [];
        for ($date = $this->firstSunday; $date <= $this->baptism; $date = TemporalCalendar::addDays($date, 1)) {
            $this->days[$date->format('Y-m-d')] = $this->classify($date);
        }
    }

    public static function forYear(int $year, ?Corpus $corpus = null, ?string $editionDir = null): self
    {
        return new self($year, $corpus, $editionDir);
    }

    /** The Baptism of the Lord — the last day of Christmas Time, the exclusive-below boundary of Ordinary Time. */
    public function baptism(): DateTimeImmutable
    {
        return $this->baptism;
    }

    /** The Holy Family — the Sunday within the Christmas octave, or 30 December when the octave has no Sunday. */
    public function holyFamily(): DateTimeImmutable
    {
        return $this->holyFamily;
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

    /**
     * The Holy Family of a given Advent year: the Sunday falling within the octave of the
     * Nativity (26–31 December), or — in a year when the octave holds no Sunday (Christmas
     * on a Sunday) — 30 December.
     */
    private static function computeHolyFamily(int $year): DateTimeImmutable
    {
        $last = TemporalCalendar::utcDate($year, 12, 31);
        $date = TemporalCalendar::utcDate($year, 12, 26);
        for (; $date <= $last; $date = TemporalCalendar::addDays($date, 1)) {
            if (TemporalCalendar::isSunday($date)) {
                return $date;
            }
        }

        return TemporalCalendar::utcDate($year, 12, 30);
    }

    /**
     * The Second Sunday after the Nativity of a given Advent year: the Sunday falling 2–5
     * January of the following year, before the Epiphany. It exists only when 1 January is
     * a Wednesday–Saturday; otherwise there is no such Sunday (the Epiphany or the octave
     * day takes it) and Christmas Time has no second Sunday.
     */
    private static function computeSecondSundayAfterChristmas(int $year): ?DateTimeImmutable
    {
        $last = TemporalCalendar::utcDate($year + 1, 1, 5);
        $date = TemporalCalendar::utcDate($year + 1, 1, 2);
        for (; $date <= $last; $date = TemporalCalendar::addDays($date, 1)) {
            if (TemporalCalendar::isSunday($date)) {
                return $date;
            }
        }

        return null;
    }

    private function classify(DateTimeImmutable $date): TemporalObservance
    {
        // The great feasts and the fixed movable feasts take the day-slot before the season
        // ranges fill the remainder (feast › Sunday › octave weekday › feria), so a Sunday that
        // is the Holy Family or the octave day keeps its feast, not a generic Sunday office.
        if (TemporalCalendar::sameDay($date, $this->christmas)) {
            return $this->mint('roman:temporale:christmas:nativity', Season::christmastide(), 'nativity', $date);
        }

        if (TemporalCalendar::sameDay($date, $this->epiphany)) {
            return $this->mint('roman:temporale:epiphany:domini', Season::christmastide(), 'epiphany', $date);
        }

        if (TemporalCalendar::sameDay($date, $this->baptism)) {
            return $this->mint(
                'roman:temporale:epiphany:baptism-of-the-lord',
                Season::christmastide(),
                'no-baptism',
                $date
            );
        }

        if (TemporalCalendar::sameDay($date, $this->octaveDay)) {
            return $this->mint('roman:temporale:christmas:octave-day', Season::christmastide(), 'no-octave-day', $date);
        }

        if (TemporalCalendar::sameDay($date, $this->holyFamily)) {
            return $this->mint(
                'roman:temporale:christmas:holy-family',
                Season::christmastide(),
                'no-holy-family',
                $date
            );
        }

        $secondSunday = $this->secondSundayAfterChristmas;
        if ($secondSunday !== null && TemporalCalendar::sameDay($date, $secondSunday)) {
            return $this->mint(
                'roman:temporale:christmas:sunday-after-octave',
                Season::christmastide(),
                'no-christmas-sunday-2',
                $date
            );
        }

        if ($date < $this->christmas) {
            return $this->classifyAdvent($date);
        }

        if ($date < $this->octaveDay) {
            return $this->classifyWithinOctave($date);
        }

        if ($date < $this->epiphany) {
            return $this->classifyBeforeEpiphany($date);
        }

        return $this->classifyAfterEpiphany($date);
    }

    private function classifyAdvent(DateTimeImmutable $date): TemporalObservance
    {
        if (TemporalCalendar::isSunday($date)) {
            $n = intdiv(TemporalCalendar::daysBetween($this->firstSunday, $date), 7) + 1;
            $archetype = $n === 3 ? 'no-advent-sunday-gaudete' : 'no-advent-sunday';

            return $this->mint('roman:temporale:advent:sunday-' . $n, Season::advent(), $archetype, $date, $n);
        }

        // 17–24 December: the privileged weekdays of the O antiphons, named by calendar date.
        if ($date >= $this->privilegedFrom) {
            return $this->mint(
                'roman:temporale:advent:december-' . (int) $date->format('j'),
                Season::advent(),
                'no-advent-privileged',
                $date
            );
        }

        $week = intdiv(TemporalCalendar::daysBetween($this->firstSunday, $date), 7) + 1;

        return $this->mint(
            'roman:temporale:advent:week-' . $week . ':' . TemporalCalendar::feriaToken($date),
            Season::advent(),
            'no-advent-feria',
            $date,
            $week
        );
    }

    /** 26–31 December: the weekday floor within the octave of the Nativity, named by date. */
    private function classifyWithinOctave(DateTimeImmutable $date): TemporalObservance
    {
        return $this->mint(
            'roman:temporale:christmas:december-' . (int) $date->format('j'),
            Season::christmastide(),
            'no-nativity-octave-weekday',
            $date
        );
    }

    /** 2–5 January: the weekdays after the octave day and before the Epiphany, named by date. */
    private function classifyBeforeEpiphany(DateTimeImmutable $date): TemporalObservance
    {
        return $this->mint(
            'roman:temporale:christmas:january-' . (int) $date->format('j'),
            Season::christmastide(),
            'no-christmas-weekday-january',
            $date
        );
    }

    /** After the Epiphany up to the Baptism: the weekdays "post Epiphaniam", still Christmas Time. */
    private function classifyAfterEpiphany(DateTimeImmutable $date): TemporalObservance
    {
        return $this->mint(
            'roman:temporale:epiphany:post-epiphaniam:' . TemporalCalendar::feriaToken($date),
            Season::christmastide(),
            'no-after-epiphany-feria',
            $date
        );
    }

    /**
     * Mint the temporal office of a day: the slug and season are structural, the kind, rank,
     * colour, and Latin name come from the edition's temporal archetype overlay, rendered
     * with the day's ordinal (week / Sunday number) and, for a date-named day, its date.
     */
    private function mint(
        string $slug,
        Season $season,
        string $archetype,
        DateTimeImmutable $date,
        int $ord = 0
    ): TemporalObservance {
        $office = $this->attributes->archetype($archetype);

        return new TemporalObservance(
            ObservanceId::parse($slug),
            $office->kind(),
            $season,
            $office->rank(),
            $office->colour(),
            $office->renderName($date, $ord)
        );
    }
}
