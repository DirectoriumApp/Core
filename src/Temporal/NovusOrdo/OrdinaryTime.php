<?php

declare(strict_types=1);

namespace Directorium\Core\Temporal\NovusOrdo;

use DateTimeImmutable;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Observance\ObservanceId;
use Directorium\Core\Temporal\ChristmasCycle;
use Directorium\Core\Temporal\Computus;
use Directorium\Core\Temporal\PaschalSkeleton;
use Directorium\Core\Temporal\Season;
use Directorium\Core\Temporal\TemporalAttributes;
use Directorium\Core\Temporal\TemporalCalendar;
use Directorium\Core\Temporal\TemporalObservance;
use InvalidArgumentException;

/**
 * The temporal skeleton of the Novus-Ordo **Ordinary Time** (tempus per annum) —
 * the two disjoint green blocks that carry the single 1–34 week series
 * (Norms n. 43–44). This is the one genuinely new season the Ordinary Form adds
 * (docs/design/novus-ordo-calendar-model.md, #258); the other Novus-Ordo seasons
 * reuse the traditional Advent/Christmas/Lent/Easter shapes and arrive with the
 * general-calendar corpus (#108).
 *
 * `forYear($year)` fills every Ordinary-Time day of the civil `$year`:
 *
 *   * **Block I** begins the Monday after the **Baptism of the Lord** (the Sunday
 *     after 6 January) as *week 1* and runs through the Tuesday before Ash
 *     Wednesday. There is no "First Sunday in Ordinary Time" — the Baptism occupies
 *     that Sunday, so the first Sunday this block mints is the **Second** (week 2).
 *   * **Block II** resumes the Monday after **Pentecost** and runs to the eve of the
 *     First Sunday of Advent, closing with the **34th** Sunday (over which Christ
 *     the King is laid — that solemnity is a movable-feast overlay, not minted here;
 *     this filler emits the green Sunday beneath it, exactly as the traditional
 *     {@see \Directorium\Core\Temporal\TimeAfterPentecost} emits the green Sunday
 *     beneath Trinity/Corpus Christi).
 *
 * The week number is engineered so the last Sunday before Advent is always the 34th:
 * Block I counts **forward from the Baptism** (week 1) and Block II counts
 * **backward from Christ the King** (week 34) — the same backward-count technique the
 * traditional Time after Pentecost already uses. In a 33-week year the two anchors
 * leave exactly one week unfilled (the week that would have followed Block I); a
 * genuine 34-week year is continuous. No week is ever double-assigned.
 *
 * This is the *temporal skeleton* only: the movable solemnities that overlay the
 * early and late weeks (Trinity, Corpus Christi, the Sacred Heart, Christ the King)
 * and the Sunday sanctoral are composed on top by later passes (precedence #257,
 * corpus #108). The office facts — kind, rank, colour, Latin name — are read from
 * the edition's temporal archetypes (`ot-sunday`, `ot-feria`); only the date
 * arithmetic and the structural slug stay in code.
 *
 * The Easter arithmetic (Ash Wednesday, Pentecost) is edition-invariant, so this
 * reuses the shared {@see PaschalSkeleton} unchanged; only the archetype overlay is
 * per-edition.
 */
final class OrdinaryTime
{
    /** The last Sunday of Ordinary Time (Christ the King) is always the 34th. */
    private const LAST_SUNDAY_NUMBER = 34;

    private int $year;

    private DateTimeImmutable $baptism;

    private DateTimeImmutable $ashWednesday;

    private DateTimeImmutable $pentecost;

    private DateTimeImmutable $firstSundayOfAdvent;

    /** Christ the King — the Sunday before Advent I, the 34th and last Ordinary-Time Sunday. */
    private DateTimeImmutable $christTheKing;

    private TemporalAttributes $attributes;

    /** @var array<string, TemporalObservance> Keyed by 'Y-m-d', in chronological order. */
    private array $days;

    private function __construct(int $year, ?Corpus $corpus = null, ?string $editionDir = null)
    {
        if ($year < Computus::GREGORIAN_REFORM_YEAR) {
            throw new InvalidArgumentException(sprintf(
                'Ordinary Time is defined from %d onward; got %d.',
                Computus::GREGORIAN_REFORM_YEAR,
                $year
            ));
        }

        $this->attributes = new TemporalAttributes($corpus, $editionDir);
        $this->year = $year;

        $skeleton = PaschalSkeleton::forYear($year);
        $this->ashWednesday = $skeleton->ashWednesday();
        $this->pentecost = $skeleton->pentecost();
        $this->baptism = self::baptismOfTheLord($year);
        $this->firstSundayOfAdvent = ChristmasCycle::firstSundayOfAdvent($year);
        $this->christTheKing = TemporalCalendar::addDays($this->firstSundayOfAdvent, -7);

        $this->days = [];
        $this->fillBlock(
            TemporalCalendar::addDays($this->baptism, 1),   // Monday after the Baptism
            $this->ashWednesday,                            // exclusive: Ash Wednesday opens Lent
            true
        );
        $this->fillBlock(
            TemporalCalendar::addDays($this->pentecost, 1), // Monday after Pentecost
            $this->firstSundayOfAdvent,                     // exclusive: Advent I opens the new year
            false
        );
    }

    public static function forYear(int $year, ?Corpus $corpus = null, ?string $editionDir = null): self
    {
        return new self($year, $corpus, $editionDir);
    }

    /**
     * The Baptism of the Lord — the Sunday after 6 January (the last day of Christmas
     * Time; Ordinary Time opens the following Monday). This is the universal-calendar
     * rule; the conference variant that keeps the Baptism to the Monday after a
     * Sunday-transferred Epiphany is a resolve-time toggle deferred with edition
     * governance (#366).
     *
     * When 6 January is itself a Sunday (the Epiphany), "the Sunday after" is the
     * following Sunday, 13 January. That edge is oracle-pending (#260) and is why the
     * unit tests pin years where 6 January is a weekday.
     */
    public static function baptismOfTheLord(int $year): DateTimeImmutable
    {
        $epiphany = TemporalCalendar::utcDate($year, 1, 6);
        $dow = (int) $epiphany->format('w'); // 0 = Sunday … 6 = Saturday

        return TemporalCalendar::addDays($epiphany, 7 - $dow);
    }

    public function year(): int
    {
        return $this->year;
    }

    public function baptism(): DateTimeImmutable
    {
        return $this->baptism;
    }

    /** Christ the King — the 34th Sunday, the last day-slot of Ordinary Time before Advent. */
    public function christTheKing(): DateTimeImmutable
    {
        return $this->christTheKing;
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

    /** The temporal office of one day, or null if the date is outside both Ordinary-Time blocks. */
    public function on(DateTimeImmutable $date): ?TemporalObservance
    {
        return $this->days[$date->format('Y-m-d')] ?? null;
    }

    /**
     * Fill a half-open day range `[$start, $endExclusive)` with its Ordinary-Time
     * offices. Block I anchors the week count forward from the Baptism (week 1);
     * Block II anchors it backward from Christ the King (week 34).
     */
    private function fillBlock(DateTimeImmutable $start, DateTimeImmutable $endExclusive, bool $isBlockOne): void
    {
        for ($date = $start; $date < $endExclusive; $date = TemporalCalendar::addDays($date, 1)) {
            $week = $isBlockOne ? $this->weekBlockOne($date) : $this->weekBlockTwo($date);
            $this->days[$date->format('Y-m-d')] = TemporalCalendar::isSunday($date)
                ? $this->mintSunday($week, $date)
                : $this->mintFeria($week, $date);
        }
    }

    /**
     * Block I week: counted forward from the Baptism's Sunday. The Baptism occupies the
     * "first Sunday" slot, so its own week (the ferias that follow it) is week 1 and the
     * next Sunday is the 2nd Sunday of Ordinary Time.
     */
    private function weekBlockOne(DateTimeImmutable $date): int
    {
        return intdiv(TemporalCalendar::daysBetween($this->baptism, self::sundayOnOrBefore($date)), 7) + 1;
    }

    /**
     * Block II week: counted backward from Christ the King (week 34). The ferias of the
     * week after Pentecost take Pentecost as their "Sunday on or before", so they resume
     * the count at 35 − W (W = the Sundays from here to Advent) without that number being
     * computed explicitly — the backward difference yields it.
     */
    private function weekBlockTwo(DateTimeImmutable $date): int
    {
        $weeksToLast = intdiv(
            TemporalCalendar::daysBetween(self::sundayOnOrBefore($date), $this->christTheKing),
            7
        );

        return self::LAST_SUNDAY_NUMBER - $weeksToLast;
    }

    /** The Sunday on or before a date (the date itself when it is a Sunday). */
    private static function sundayOnOrBefore(DateTimeImmutable $date): DateTimeImmutable
    {
        return TemporalCalendar::addDays($date, -((int) $date->format('w')));
    }

    private function mintSunday(int $week, DateTimeImmutable $date): TemporalObservance
    {
        return $this->mint(
            'roman:temporale:ordinary-time:sunday-' . $week,
            'ot-sunday',
            $date,
            $week
        );
    }

    private function mintFeria(int $week, DateTimeImmutable $date): TemporalObservance
    {
        return $this->mint(
            'roman:temporale:ordinary-time:week-' . $week . ':' . TemporalCalendar::feriaToken($date),
            'ot-feria',
            $date,
            $week
        );
    }

    /**
     * Mint one Ordinary-Time office: the slug and the green {@see Season::ordinaryTime()}
     * are structural, and the kind, rank, colour, and Latin name come from the edition's
     * temporal archetype, rendered with the week ordinal and weekday.
     */
    private function mint(string $slug, string $archetype, DateTimeImmutable $date, int $week): TemporalObservance
    {
        $office = $this->attributes->archetype($archetype);

        return new TemporalObservance(
            ObservanceId::parse($slug),
            $office->kind(),
            Season::ordinaryTime(),
            $office->rank(),
            $office->colour(),
            $office->renderName($date, $week)
        );
    }
}
