<?php

declare(strict_types=1);

namespace Introibo\Core\Temporal;

use DateInterval;
use DateTimeImmutable;
use Introibo\Core\Observance\ObservanceId;
use InvalidArgumentException;

/**
 * The temporal skeleton of the Advent-to-Epiphany block — traditionally the
 * *Christmas cycle*, the counterpart of the Easter cycle built on the
 * {@see PaschalSkeleton}.
 *
 * `forYear($year)` fills every day from the First Sunday of Advent through the
 * eve of Septuagesima with its principal temporal office (a
 * {@see TemporalObservance}) under the 1960 rubrics. `$year` is the civil year in
 * which Advent begins and Christmas falls; the Epiphany and the Sundays after it
 * fall in `$year + 1`, and Septuagesima of that following year — computed from
 * {@see PaschalSkeleton} — is the exclusive end boundary handed off to the Easter
 * cycle (#18).
 *
 * Advent is anchored structurally to the Sunday nearest St Andrew (30 November),
 * never to Easter: the fourth Sunday of Advent is the latest Sunday on or before
 * 24 December, and the first is three weeks earlier. This is the *temporal
 * skeleton* only — special movable feasts (Holy Name, Holy Family, the
 * Commemoration of the Baptism: #22), the sanctoral of the Christmas octave
 * (#23), and precedence/commemoration (#29) compose on top of it. See
 * docs/design/temporal-fill-model.md.
 */
final class ChristmasCycle
{
    private int $year;

    private DateTimeImmutable $firstSunday;

    private DateTimeImmutable $vigil;

    private DateTimeImmutable $christmas;

    private DateTimeImmutable $octaveDay;

    private DateTimeImmutable $epiphany;

    private DateTimeImmutable $firstSundayAfterEpiphany;

    private DateTimeImmutable $septuagesima;

    private DateTimeImmutable $emberWednesday;

    private DateTimeImmutable $emberFriday;

    private DateTimeImmutable $emberSaturday;

    private TemporalAttributes $attributes;

    /** @var array<string, TemporalObservance> Keyed by 'Y-m-d', in chronological order. */
    private array $days;

    private function __construct(int $year)
    {
        if ($year < Computus::GREGORIAN_REFORM_YEAR) {
            throw new InvalidArgumentException(sprintf(
                'The Christmas cycle is defined from %d onward; got %d.',
                Computus::GREGORIAN_REFORM_YEAR,
                $year
            ));
        }

        $this->attributes = TemporalAttributes::default();
        $this->year = $year;
        $this->firstSunday = self::firstSundayOfAdvent($year);
        $this->christmas = TemporalCalendar::utcDate($year, 12, 25);
        $this->vigil = TemporalCalendar::utcDate($year, 12, 24);
        $this->octaveDay = TemporalCalendar::utcDate($year + 1, 1, 1);
        $this->epiphany = TemporalCalendar::utcDate($year + 1, 1, 6);
        $this->septuagesima = PaschalSkeleton::forYear($year + 1)->septuagesima();

        $thirdSunday = TemporalCalendar::addDays($this->firstSunday, 14);
        $this->emberWednesday = TemporalCalendar::addDays($thirdSunday, 3);
        $this->emberFriday = TemporalCalendar::addDays($thirdSunday, 5);
        $this->emberSaturday = TemporalCalendar::addDays($thirdSunday, 6);

        $epiphanyDow = (int) $this->epiphany->format('w'); // 0 = Sunday … 6 = Saturday
        $this->firstSundayAfterEpiphany = TemporalCalendar::addDays($this->epiphany, 7 - $epiphanyDow);

        $this->days = [];
        for ($date = $this->firstSunday; $date < $this->septuagesima; $date = TemporalCalendar::addDays($date, 1)) {
            $this->days[$date->format('Y-m-d')] = $this->classify($date);
        }
    }

    public static function forYear(int $year): self
    {
        return new self($year);
    }

    /**
     * The First Sunday of Advent: the Sunday nearest St Andrew (30 November),
     * equivalently the fourth Sunday before Christmas. Always falls 27 November –
     * 3 December.
     */
    public static function firstSundayOfAdvent(int $year): DateTimeImmutable
    {
        $christmasEve = TemporalCalendar::utcDate($year, 12, 24);
        $dow = (int) $christmasEve->format('w'); // 0 = Sunday … 6 = Saturday
        $fourthSunday = $dow === 0 ? $christmasEve : $christmasEve->sub(new DateInterval('P' . $dow . 'D'));

        return $fourthSunday->sub(new DateInterval('P21D'));
    }

    public function year(): int
    {
        return $this->year;
    }

    public function firstSunday(): DateTimeImmutable
    {
        return $this->firstSunday;
    }

    public function christmas(): DateTimeImmutable
    {
        return $this->christmas;
    }

    /** The Octave Day of the Nativity — the Circumcision, 1 January. */
    public function circumcision(): DateTimeImmutable
    {
        return $this->octaveDay;
    }

    public function epiphany(): DateTimeImmutable
    {
        return $this->epiphany;
    }

    /** Septuagesima of the following year: the first day NOT in this block. */
    public function endsBefore(): DateTimeImmutable
    {
        return $this->septuagesima;
    }

    /**
     * A short Advent — the fourth Sunday of Advent falls on Christmas Eve, so the
     * Vigil of the Nativity supersedes it. Happens exactly when Christmas is a
     * Monday.
     */
    public function isShortAdvent(): bool
    {
        return TemporalCalendar::addDays($this->firstSunday, 21)->format('Y-m-d') === $this->vigil->format('Y-m-d');
    }

    /** How many Sundays after the Epiphany occur before Septuagesima (1–6). */
    public function sundaysAfterEpiphany(): int
    {
        return intdiv(TemporalCalendar::daysBetween($this->firstSundayAfterEpiphany, $this->septuagesima), 7);
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
        // Fixed temporal feasts take the day (§ precedence: feast › Sunday › octave day › feria).
        if (TemporalCalendar::sameDay($date, $this->christmas)) {
            return $this->mint('roman:temporale:christmas:nativity', Season::christmastide(), 'nativity', $date);
        }

        if (TemporalCalendar::sameDay($date, $this->octaveDay)) {
            return $this->mint(
                'roman:temporale:christmas:octave-day',
                Season::christmastide(),
                'christmas-octave-day',
                $date
            );
        }

        if (TemporalCalendar::sameDay($date, $this->epiphany)) {
            return $this->mint('roman:temporale:epiphany:domini', Season::epiphany(), 'epiphany', $date);
        }

        if (TemporalCalendar::sameDay($date, $this->vigil)) {
            return $this->mint('roman:temporale:christmas:vigil', Season::advent(), 'christmas-vigil', $date);
        }

        if ($date < $this->christmas) {
            return $this->classifyAdvent($date);
        }

        if ($date < $this->octaveDay) {
            return $this->classifyWithinOctave($date);
        }

        if ($date < $this->epiphany) {
            return $this->classifyAfterOctave($date);
        }

        return $this->classifyAfterEpiphany($date);
    }

    private function classifyAdvent(DateTimeImmutable $date): TemporalObservance
    {
        if (TemporalCalendar::isSunday($date)) {
            $n = intdiv(TemporalCalendar::daysBetween($this->firstSunday, $date), 7) + 1;
            $archetype = $n === 1 ? 'advent-sunday-i' : ($n === 3 ? 'advent-sunday-gaudete' : 'advent-sunday');

            return $this->mint('roman:temporale:advent:sunday-' . $n, Season::advent(), $archetype, $date, $n);
        }

        if ($this->isEmberDay($date)) {
            return $this->mint(
                'roman:temporale:advent:quattuor-temporum:' . TemporalCalendar::feriaToken($date),
                Season::advent(),
                'advent-ember',
                $date
            );
        }

        $week = intdiv(TemporalCalendar::daysBetween($this->firstSunday, $date), 7) + 1;
        $isGreaterFeria = $date >= TemporalCalendar::utcDate($this->year, 12, 17); // greater ferias: 17–23 December

        return $this->mint(
            'roman:temporale:advent:week-' . $week . ':' . TemporalCalendar::feriaToken($date),
            Season::advent(),
            $isGreaterFeria ? 'advent-feria-greater' : 'advent-feria',
            $date,
            $week
        );
    }

    private function classifyWithinOctave(DateTimeImmutable $date): TemporalObservance
    {
        if (TemporalCalendar::isSunday($date)) {
            return $this->mint(
                'roman:temporale:christmas:sunday-within-octave',
                Season::christmastide(),
                'christmas-sunday-within-octave',
                $date
            );
        }

        $dayOfOctave = TemporalCalendar::daysBetween($this->christmas, $date) + 1; // 25 Dec = 1, so 26–31 Dec = 2–7

        return $this->mint(
            'roman:temporale:christmas:within-octave:day-' . $dayOfOctave,
            Season::christmastide(),
            'christmas-within-octave',
            $date,
            $dayOfOctave
        );
    }

    private function classifyAfterOctave(DateTimeImmutable $date): TemporalObservance
    {
        if (TemporalCalendar::isSunday($date)) {
            // The Sunday falling 2–5 January. The 1962 books have no "II Sunday
            // after Christmas" (that is a 1969+ construct); this is structurally
            // the Sunday after the Octave, over which the Most Holy Name is laid (#22).
            return $this->mint(
                'roman:temporale:christmas:sunday-after-octave',
                Season::christmastide(),
                'christmas-sunday-after-octave',
                $date
            );
        }

        return $this->mint(
            'roman:temporale:christmas:post-octavam:' . TemporalCalendar::feriaToken($date),
            Season::christmastide(),
            'christmas-post-octave-feria',
            $date
        );
    }

    private function classifyAfterEpiphany(DateTimeImmutable $date): TemporalObservance
    {
        if (TemporalCalendar::isSunday($date)) {
            $n = intdiv(TemporalCalendar::daysBetween($this->firstSundayAfterEpiphany, $date), 7) + 1;

            return $this->mint(
                'roman:temporale:epiphany:sunday-' . $n,
                Season::epiphany(),
                'epiphany-sunday',
                $date,
                $n
            );
        }

        if ($date < $this->firstSundayAfterEpiphany) {
            return $this->mint(
                'roman:temporale:epiphany:post-epiphaniam:' . TemporalCalendar::feriaToken($date),
                Season::epiphany(),
                'epiphany-feria',
                $date
            );
        }

        $week = intdiv(TemporalCalendar::daysBetween($this->firstSundayAfterEpiphany, $date), 7) + 1;

        return $this->mint(
            'roman:temporale:epiphany:week-' . $week . ':' . TemporalCalendar::feriaToken($date),
            Season::epiphany(),
            'epiphany-week-feria',
            $date,
            $week
        );
    }

    /**
     * Mint the temporal office of a day: the slug and season are structural (built
     * here from the date), the kind, rank, colour, and Latin name come from the
     * archetype overlay in the corpus, rendered with the day's `$ord` (week /
     * Sunday number) and weekday.
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

    private function isEmberDay(DateTimeImmutable $date): bool
    {
        return TemporalCalendar::sameDay($date, $this->emberWednesday)
            || TemporalCalendar::sameDay($date, $this->emberFriday)
            || TemporalCalendar::sameDay($date, $this->emberSaturday);
    }
}
