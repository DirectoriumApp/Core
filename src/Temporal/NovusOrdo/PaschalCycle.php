<?php

declare(strict_types=1);

namespace Directorium\Core\Temporal\NovusOrdo;

use DateTimeImmutable;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Observance\ObservanceId;
use Directorium\Core\Temporal\Computus;
use Directorium\Core\Temporal\PaschalSkeleton;
use Directorium\Core\Temporal\Season;
use Directorium\Core\Temporal\TemporalAttributes;
use Directorium\Core\Temporal\TemporalCalendar;
use Directorium\Core\Temporal\TemporalObservance;
use Directorium\Core\Temporal\TriduumWindow;
use InvalidArgumentException;

/**
 * The temporal skeleton of the Novus-Ordo **paschal half** — Lent, Holy Week, the
 * Sacred Triduum, and Easter Time — the reform's counterpart of the traditional
 * {@see \Directorium\Core\Temporal\LentenCycle} + {@see \Directorium\Core\Temporal\HolyWeek}
 * + {@see \Directorium\Core\Temporal\Eastertide} trio, gathered into one filler.
 *
 * `forYear($year)` fills every day of the paschal window `[Ash Wednesday, Pentecost]`
 * of the civil year in which Easter falls. Ordinary Time (its {@see OrdinaryTime} Block I)
 * hands off the day before Ash Wednesday and resumes (Block II) the Monday after
 * Pentecost, so this filler and Ordinary Time tile the year with neither overlap nor gap.
 *
 * What the reform re-shaped, relative to the tradition:
 *
 *   * **Lent** keeps Ash Wednesday, the "post Cineres" ferias, and the five Sundays "in
 *     Quadragesima" (Laetare rose on the fourth) under their ancient names, but drops
 *     Septuagesima (now Ordinary Time), the Lenten Ember days, and the distinct Passiontide,
 *     and re-words the weekday form to "… Hebdomadae … Quadragesimae". Lent runs to the
 *     Mass of the Lord's Supper, so every day here up to and including Holy Saturday is the
 *     violet {@see Season::lent()}.
 *   * **Holy Week** merges the old Palm Sunday and Passion Sunday into "Dominica in Palmis de
 *     Passione Domini" (red), and turns the Good Friday liturgy red. The three days of the
 *     Sacred Triduum — Holy Thursday, Good Friday, Holy Saturday — are minted kind=feria so
 *     the precedence engine routes them to the apex line when {@see isTriduum()} is true.
 *   * **Easter Time** is one fifty-day white season (red on Pentecost). The eight days of the
 *     Octave of Easter are each a Solemnity of the Lord (n. 24); the octave closes on the
 *     Second Sunday of Easter "de divina Misericordia". There is no Ascension octave and no
 *     Pentecost octave. Ascension is the fortieth day (Thursday) in the universal calendar.
 *
 * This is the *temporal skeleton* only; the sanctoral and precedence compose on top. The
 * office facts — kind, rank, colour, Latin name — are read from the edition's temporal
 * archetypes; only the Easter arithmetic (shared, edition-invariant, via {@see PaschalSkeleton})
 * and the structural slug stay in code. The movable solemnities that overlay Ordinary Time
 * either side of this window (Trinity, Corpus Christi, the Sacred Heart, Christ the King) are
 * minted by {@see MovableFeasts}, not here.
 *
 * @internal Not part of the public API (docs/api-stability.md).
 */
final class PaschalCycle implements TriduumWindow
{
    private DateTimeImmutable $easter;

    private DateTimeImmutable $ashWednesday;

    private DateTimeImmutable $palmSunday;

    private DateTimeImmutable $maundyThursday;

    private DateTimeImmutable $goodFriday;

    private DateTimeImmutable $holySaturday;

    private DateTimeImmutable $ascension;

    private DateTimeImmutable $pentecost;

    private TemporalAttributes $attributes;

    /** @var array<string, TemporalObservance> Keyed by 'Y-m-d', in chronological order. */
    private array $days;

    private function __construct(int $year, ?Corpus $corpus = null, ?string $editionDir = null)
    {
        if ($year < Computus::GREGORIAN_REFORM_YEAR) {
            throw new InvalidArgumentException(sprintf(
                'The Novus-Ordo paschal cycle is defined from %d onward; got %d.',
                Computus::GREGORIAN_REFORM_YEAR,
                $year
            ));
        }

        $this->attributes = new TemporalAttributes($corpus, $editionDir);
        $skeleton = PaschalSkeleton::forYear($year);
        $this->easter = $skeleton->easter();
        $this->ashWednesday = $skeleton->ashWednesday();
        $this->palmSunday = $skeleton->date('palm-sunday');
        $this->maundyThursday = $skeleton->date('maundy-thursday');
        $this->goodFriday = $skeleton->date('good-friday');
        $this->holySaturday = $skeleton->date('holy-saturday');
        $this->ascension = $skeleton->ascension();
        $this->pentecost = $skeleton->pentecost();

        $this->days = [];
        for ($date = $this->ashWednesday; $date <= $this->pentecost; $date = TemporalCalendar::addDays($date, 1)) {
            $this->days[$date->format('Y-m-d')] = $this->classify($date);
        }
    }

    public static function forYear(int $year, ?Corpus $corpus = null, ?string $editionDir = null): self
    {
        return new self($year, $corpus, $editionDir);
    }

    public function easter(): DateTimeImmutable
    {
        return $this->easter;
    }

    /** Ash Wednesday: the first day of this block. */
    public function ashWednesday(): DateTimeImmutable
    {
        return $this->ashWednesday;
    }

    public function palmSunday(): DateTimeImmutable
    {
        return $this->palmSunday;
    }

    public function ascension(): DateTimeImmutable
    {
        return $this->ascension;
    }

    /** Pentecost: the last day of this block (Ordinary Time resumes the following Monday). */
    public function pentecost(): DateTimeImmutable
    {
        return $this->pentecost;
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

    public function isTriduum(DateTimeImmutable $date): bool
    {
        return TemporalCalendar::sameDay($date, $this->maundyThursday)
            || TemporalCalendar::sameDay($date, $this->goodFriday)
            || TemporalCalendar::sameDay($date, $this->holySaturday);
    }

    private function classify(DateTimeImmutable $date): TemporalObservance
    {
        $offset = $date < $this->easter
            ? -TemporalCalendar::daysBetween($date, $this->easter) // days before Easter, negative
            : TemporalCalendar::daysBetween($this->easter, $date); // days after Easter, 0..49

        return $offset < 0 ? $this->classifyLent($date, $offset) : $this->classifyEaster($date, $offset);
    }

    /**
     * Lent and Holy Week — the violet run from Ash Wednesday (Easter−46) to Holy Saturday
     * (Easter−1), all the {@see Season::lent()} season (Lent extends to the Mass of the
     * Lord's Supper; the Triduum days carry no season token of their own in the five-season
     * reformed vocabulary, so they stay lent for output).
     */
    private function classifyLent(DateTimeImmutable $date, int $offset): TemporalObservance
    {
        if ($offset === -46) {
            return $this->mint('roman:temporale:paschal:lent:ash-wednesday', 'no-ash-wednesday', $date);
        }
        if ($offset >= -45 && $offset <= -43) {
            return $this->mint(
                'roman:temporale:paschal:lent:post-cineres:' . TemporalCalendar::feriaToken($date),
                'no-post-cineres-feria',
                $date
            );
        }

        // The five Sundays of Lent (Easter−42 … −14, each a multiple of a week before Easter);
        // Laetare (the fourth) permits rose.
        if ($offset % 7 === 0 && $offset >= -42 && $offset <= -14) {
            $n = intdiv($offset + 49, 7); // −42 => 1 … −14 => 5
            $archetype = $n === 4 ? 'no-lent-sunday-laetare' : 'no-lent-sunday';

            return $this->mint('roman:temporale:paschal:lent:sunday-' . $n, $archetype, $date, $n);
        }

        if ($offset === -7) {
            return $this->mint('roman:temporale:paschal:holy-week:palm-sunday', 'no-palm-sunday', $date);
        }

        // The Sacred Triduum: Maundy Thursday (the shared base key), Good Friday, Holy Saturday.
        if ($offset === -3) {
            return $this->mint('roman:temporale:paschal:holy-week:maundy-thursday', 'maundy-thursday', $date);
        }
        if ($offset === -2) {
            return $this->mint('roman:temporale:paschal:holy-week:good-friday', 'no-good-friday', $date);
        }
        if ($offset === -1) {
            return $this->mint('roman:temporale:paschal:holy-week:holy-saturday', 'no-holy-saturday', $date);
        }

        // Monday–Wednesday of Holy Week.
        if ($offset >= -6 && $offset <= -4) {
            return $this->mint(
                'roman:temporale:paschal:holy-week:' . TemporalCalendar::feriaToken($date),
                'no-holy-week-feria',
                $date
            );
        }

        // The ordinary weekdays of the five weeks of Lent.
        $week = intdiv($offset + 42, 7) + 1;

        return $this->mint(
            'roman:temporale:paschal:lent:week-' . $week . ':' . TemporalCalendar::feriaToken($date),
            'no-lent-feria',
            $date,
            $week
        );
    }

    /**
     * Easter Time — the white fifty days from Easter Sunday (Easter+0) to Pentecost
     * (Easter+49), all the {@see Season::eastertide()} season.
     */
    private function classifyEaster(DateTimeImmutable $date, int $offset): TemporalObservance
    {
        if ($offset === 0) {
            return $this->mint('roman:temporale:paschal:easter', 'no-easter', $date);
        }

        // The weekdays of the Octave of Easter (Easter+1 … +6), each a Solemnity of the Lord.
        if ($offset >= 1 && $offset <= 6) {
            return $this->mint(
                'roman:temporale:paschal:easter-octave:' . TemporalCalendar::feriaToken($date),
                'no-easter-octave-feria',
                $date
            );
        }

        // The Second Sunday of Easter "de divina Misericordia" closes the octave.
        if ($offset === 7) {
            return $this->mint('roman:temporale:paschal:easter:sunday-2', 'no-easter-sunday-mercy', $date);
        }

        // Ascension — the fortieth day (Thursday) in the universal calendar.
        if ($offset === 39) {
            return $this->mint('roman:temporale:paschal:ascension', 'ascension', $date);
        }

        // Pentecost closes Easter Time.
        if ($offset === 49) {
            return $this->mint('roman:temporale:paschal:pentecost', 'pentecost', $date);
        }

        // Sundays III–VII of Easter (Easter+14, +21, +28, +35, +42).
        if ($offset % 7 === 0) {
            $n = intdiv($offset, 7) + 1; // 14 => 3 … 42 => 7

            return $this->mint('roman:temporale:paschal:easter:sunday-' . $n, 'no-easter-sunday', $date, $n);
        }

        // The ordinary weekdays of Easter Time, weeks II–VII.
        $week = intdiv($offset, 7) + 1;

        return $this->mint(
            'roman:temporale:paschal:easter:week-' . $week . ':' . TemporalCalendar::feriaToken($date),
            'no-easter-feria',
            $date,
            $week
        );
    }

    /**
     * Mint the temporal office of a day: the slug and the day's season are structural, and the
     * kind, rank, colour, and Latin name come from the edition's temporal archetype overlay,
     * rendered with the day's ordinal (Sunday / week number) and weekday. Every day before
     * Easter is {@see Season::lent()}, every day from Easter on {@see Season::eastertide()}.
     */
    private function mint(string $slug, string $archetype, DateTimeImmutable $date, int $ord = 0): TemporalObservance
    {
        $office = $this->attributes->archetype($archetype);
        $season = $date < $this->easter ? Season::lent() : Season::eastertide();

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
