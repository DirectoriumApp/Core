<?php

declare(strict_types=1);

namespace Directorium\Core\Temporal;

use DateTimeImmutable;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Observance\ObservanceId;
use InvalidArgumentException;
use LogicException;

/**
 * The temporal skeleton of Eastertide (Tempus Paschale) — Easter Sunday through
 * the Saturday after Pentecost, the festal half of the year completed.
 *
 * `forYear($year)` fills the fifty-six days `[Easter, Trinity Sunday)` (Easter+0
 * to Easter+55) under the 1960 rubrics, Easter-anchored via the
 * {@see PaschalSkeleton}. `$year` is the year Easter falls. Holy Week (#19) hands
 * off at Easter; Trinity Sunday, the first Sunday after Pentecost, opens the Time
 * after Pentecost (#21). Every day belongs to the {@see Season::eastertide()}
 * season — Eastertide runs to the None of the Saturday after Pentecost, so the
 * red Pentecost octave is still Eastertide, not yet the green Time after
 * Pentecost.
 *
 * Under the 1960 rubrics only the privileged octaves of Easter and Pentecost
 * survive (the Ascension octave was suppressed in 1955): the days within them are
 * all first class — white for Easter, red for Pentecost, whose vigil and Ember
 * days are red too (the joyful fast, the one exception to violet vigils/Embers).
 * The Rogation days keep their penitential violet even in Eastertide. Within-day
 * colour changes (the violet-to-white Paschal note, the red-and-white Pentecost
 * vigil) are a per-element concern of the rubrics layer. See
 * docs/design/temporal-fill-model.md.
 */
final class Eastertide
{
    private int $year;

    private DateTimeImmutable $easter;

    private DateTimeImmutable $ascension;

    private DateTimeImmutable $pentecost;

    private DateTimeImmutable $trinitySunday;

    private TemporalAttributes $attributes;

    /** @var array<string, TemporalObservance> Keyed by 'Y-m-d', in chronological order. */
    private array $days;

    private function __construct(int $year, ?Corpus $corpus = null, ?string $editionDir = null)
    {
        if ($year < Computus::GREGORIAN_REFORM_YEAR) {
            throw new InvalidArgumentException(sprintf(
                'Eastertide is defined from %d onward; got %d.',
                Computus::GREGORIAN_REFORM_YEAR,
                $year
            ));
        }

        $this->attributes = new TemporalAttributes($corpus, $editionDir);
        $skeleton = PaschalSkeleton::forYear($year);
        $this->year = $year;
        $this->easter = $skeleton->easter();
        $this->ascension = $skeleton->ascension();
        $this->pentecost = $skeleton->pentecost();
        $this->trinitySunday = $skeleton->date('trinity-sunday');

        $this->days = [];
        for ($date = $this->easter; $date < $this->trinitySunday; $date = TemporalCalendar::addDays($date, 1)) {
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

    /** Easter Sunday: the first day of this block. */
    public function easter(): DateTimeImmutable
    {
        return $this->easter;
    }

    public function ascension(): DateTimeImmutable
    {
        return $this->ascension;
    }

    public function pentecost(): DateTimeImmutable
    {
        return $this->pentecost;
    }

    /** Trinity Sunday: the first day NOT in this block (handed to the Time after Pentecost, #21). */
    public function endsBefore(): DateTimeImmutable
    {
        return $this->trinitySunday;
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
        $offset = TemporalCalendar::daysBetween($this->easter, $date); // days after Easter, 0..55

        if ($offset === 0) {
            return $this->mint('roman:temporale:paschal:easter', 'easter', $date);
        }
        if ($offset >= 1 && $offset <= 6) {
            return $this->easterOctaveFeria($date, $offset);
        }
        if ($offset === 7) {
            return $this->mint('roman:temporale:paschal:low-sunday', 'low-sunday', $date);
        }

        // Sundays II–V after Easter, and the ferias of the weeks after the Easter octave.
        if ($offset === 14 || $offset === 21 || $offset === 28 || $offset === 35) {
            $n = intdiv($offset, 7); // 2..5
            return $this->mint('roman:temporale:paschal:paschaltide:sunday-' . $n, 'paschaltide-sunday', $date, $n);
        }
        if ($offset >= 8 && $offset <= 34) {
            $week = intdiv($offset - 8, 7) + 1; // weeks I–IV post Octavam Paschae
            return $this->mint(
                'roman:temporale:paschal:paschaltide:week-' . $week . ':' . TemporalCalendar::feriaToken($date),
                'paschaltide-week-feria',
                $date,
                $week
            );
        }

        // The Minor Rogations — fourth-class ferias, but penitential violet even
        // in Eastertide (the higher rank sometimes cited is the votive Rogation
        // Mass, a precedence matter for #29, not the ferial rank).
        if ($offset === 36 || $offset === 37 || $offset === 38) {
            return $this->mint(
                'roman:temporale:paschal:rogation:' . TemporalCalendar::feriaToken($date),
                'rogation',
                $date
            );
        }

        if ($offset === 39) {
            return $this->mint('roman:temporale:paschal:ascension', 'ascension', $date);
        }
        if ($offset >= 40 && $offset <= 46) {
            $octave = $this->ascensionOctave($date, $offset);
            if ($octave !== null) {
                return $octave;
            }
        }
        if ($offset === 40 || $offset === 41) {
            return $this->mint(
                'roman:temporale:paschal:ascension-week:' . TemporalCalendar::feriaToken($date),
                'ascension-week-feria',
                $date
            );
        }
        if ($offset === 42) {
            return $this->mint(
                'roman:temporale:paschal:sunday-after-ascension',
                'sunday-after-ascension',
                $date
            );
        }
        if ($offset >= 43 && $offset <= 47) {
            // The week naming does not restart at the Sunday after Ascension: every
            // feria from Ascension to the Pentecost vigil is "N post Ascensionem".
            return $this->mint(
                'roman:temporale:paschal:post-ascension:' . TemporalCalendar::feriaToken($date),
                'post-ascension-feria',
                $date
            );
        }

        // The Vigil of Pentecost — first class, red (not violet: the joyful fast).
        if ($offset === 48) {
            return $this->mint('roman:temporale:paschal:pentecost-vigil', 'pentecost-vigil', $date);
        }

        return $this->classifyPentecost($date, $offset);
    }

    private function classifyPentecost(DateTimeImmutable $date, int $offset): TemporalObservance
    {
        if ($offset === 49) {
            return $this->mint('roman:temporale:paschal:pentecost', 'pentecost', $date);
        }

        // The Ember Days of Pentecost (Wednesday, Friday, Saturday) — red, first class.
        if ($offset === 52 || $offset === 54 || $offset === 55) {
            return $this->mint(
                'roman:temporale:paschal:pentecost-octave:quattuor-temporum:' . TemporalCalendar::feriaToken($date),
                'pentecost-ember',
                $date
            );
        }

        // The other days within the Octave of Pentecost (Monday, Tuesday, Thursday).
        if ($offset === 50 || $offset === 51 || $offset === 53) {
            return $this->mint(
                'roman:temporale:paschal:pentecost-octave:' . TemporalCalendar::feriaToken($date),
                'pentecost-octave-feria',
                $date
            );
        }

        throw new LogicException(sprintf('Unclassified Eastertide day at Easter offset %d.', $offset));
    }

    /**
     * The 1954 privileged octave of the Ascension (3rd order): the days within (Easter+40..+45,
     * excluding the Sunday after the Ascension at Easter+42, which keeps its own office and
     * commemorates the octave) and the octave day (Easter+46). The Ascension itself (Easter+39)
     * is day 1, minted above. Returns null when the edition declares no such archetype (1962) or
     * the date is the Sunday within, so the caller falls through to the ordinary post-Ascension
     * classification — the golden fixture is unmoved by construction.
     */
    private function ascensionOctave(DateTimeImmutable $date, int $offset): ?TemporalObservance
    {
        $dayOfOctave = $offset - 38; // Ascension (Easter+39) = day 1
        if ($dayOfOctave === 8 && $this->attributes->has('ascension-octave-day')) {
            return $this->mint('roman:temporale:paschal:ascension-octave:octave-day', 'ascension-octave-day', $date);
        }
        if (
            $dayOfOctave >= 2 && $dayOfOctave <= 7
            && !TemporalCalendar::isSunday($date)
            && $this->attributes->has('ascension-within-octave')
        ) {
            return $this->mint(
                'roman:temporale:paschal:ascension-octave:day-' . $dayOfOctave,
                'ascension-within-octave',
                $date,
                $dayOfOctave
            );
        }

        return null;
    }

    private function easterOctaveFeria(DateTimeImmutable $date, int $offset): TemporalObservance
    {
        // The Saturday within the octave has its own name; the rest are numbered ferias.
        $archetype = $offset === 6 ? 'easter-octave-saturday' : 'easter-octave-feria';

        return $this->mint(
            'roman:temporale:paschal:easter-octave:' . TemporalCalendar::feriaToken($date),
            $archetype,
            $date
        );
    }

    /**
     * Mint the temporal office of an Eastertide day: the slug is structural, the
     * season is always Eastertide, and the kind, rank, colour, and Latin name
     * come from the corpus archetype overlay, rendered with the day's `$ord`
     * (Sunday / week number) and weekday.
     */
    private function mint(string $slug, string $archetype, DateTimeImmutable $date, int $ord = 0): TemporalObservance
    {
        $office = $this->attributes->archetype($archetype);

        return new TemporalObservance(
            ObservanceId::parse($slug),
            $office->kind(),
            Season::eastertide(),
            $office->rank(),
            $office->colour(),
            $office->renderName($date, $ord)
        );
    }
}
