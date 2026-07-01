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

    /** @var array<string, TemporalObservance> Keyed by 'Y-m-d', in chronological order. */
    private array $days;

    private function __construct(int $year)
    {
        if ($year < Computus::GREGORIAN_REFORM_YEAR) {
            throw new InvalidArgumentException(sprintf(
                'Eastertide is defined from %d onward; got %d.',
                Computus::GREGORIAN_REFORM_YEAR,
                $year
            ));
        }

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

    public static function forYear(int $year): self
    {
        return new self($year);
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
            return $this->white(
                'roman:temporale:paschal:easter',
                ObservanceKind::SUNDAY,
                RankClass::classI(),
                'Dominica Resurrectionis'
            );
        }
        if ($offset >= 1 && $offset <= 6) {
            return $this->easterOctaveFeria($date, $offset);
        }
        if ($offset === 7) {
            return $this->white(
                'roman:temporale:paschal:low-sunday',
                ObservanceKind::SUNDAY,
                RankClass::classI(),
                'Dominica in Albis'
            );
        }

        // Sundays II–V after Easter, and the ferias of the weeks after the Easter octave.
        if ($offset === 14 || $offset === 21 || $offset === 28 || $offset === 35) {
            $n = intdiv($offset, 7); // 2..5
            return $this->white(
                'roman:temporale:paschal:paschaltide:sunday-' . $n,
                ObservanceKind::SUNDAY,
                RankClass::classII(),
                'Dominica ' . TemporalCalendar::roman($n) . ' post Pascha'
            );
        }
        if ($offset >= 8 && $offset <= 34) {
            $week = intdiv($offset - 8, 7) + 1; // weeks I–IV post Octavam Paschae
            $phrase = 'infra Hebdomadam ' . TemporalCalendar::roman($week) . ' post Octavam Paschae';
            return $this->white(
                'roman:temporale:paschal:paschaltide:week-' . $week . ':' . TemporalCalendar::feriaToken($date),
                ObservanceKind::FERIA,
                RankClass::classIV(),
                TemporalCalendar::feriaLatin($date, $phrase)
            );
        }

        // The Minor Rogations — fourth-class ferias, but penitential violet even
        // in Eastertide (the higher rank sometimes cited is the votive Rogation
        // Mass, a precedence matter for #29, not the ferial rank).
        if ($offset === 36 || $offset === 37 || $offset === 38) {
            return $this->mint(
                'roman:temporale:paschal:rogation:' . TemporalCalendar::feriaToken($date),
                ObservanceKind::ROGATION_DAY,
                RankClass::classIV(),
                ElementColour::of(Colour::violet()),
                TemporalCalendar::feriaLatin($date, 'in Rogationibus')
            );
        }

        if ($offset === 39) {
            return $this->white(
                'roman:temporale:paschal:ascension',
                ObservanceKind::FEAST,
                RankClass::classI(),
                'In Ascensione Domini'
            );
        }
        if ($offset === 40 || $offset === 41) {
            return $this->white(
                'roman:temporale:paschal:ascension-week:' . TemporalCalendar::feriaToken($date),
                ObservanceKind::FERIA,
                RankClass::classIV(),
                TemporalCalendar::feriaLatin($date, 'post Ascensionem')
            );
        }
        if ($offset === 42) {
            return $this->white(
                'roman:temporale:paschal:sunday-after-ascension',
                ObservanceKind::SUNDAY,
                RankClass::classII(),
                'Dominica post Ascensionem'
            );
        }
        if ($offset >= 43 && $offset <= 47) {
            // The week naming does not restart at the Sunday after Ascension: every
            // feria from Ascension to the Pentecost vigil is "N post Ascensionem".
            return $this->white(
                'roman:temporale:paschal:post-ascension:' . TemporalCalendar::feriaToken($date),
                ObservanceKind::FERIA,
                RankClass::classIV(),
                TemporalCalendar::feriaLatin($date, 'post Ascensionem')
            );
        }

        // The Vigil of Pentecost — first class, red (not violet: the joyful fast).
        if ($offset === 48) {
            return $this->mint(
                'roman:temporale:paschal:pentecost-vigil',
                ObservanceKind::VIGIL,
                RankClass::classI(),
                ElementColour::of(Colour::red()),
                'Sabbato in Vigilia Pentecostes'
            );
        }

        return $this->classifyPentecost($date, $offset);
    }

    private function classifyPentecost(DateTimeImmutable $date, int $offset): TemporalObservance
    {
        if ($offset === 49) {
            return $this->red('roman:temporale:paschal:pentecost', ObservanceKind::SUNDAY, 'Dominica Pentecostes');
        }

        // The Ember Days of Pentecost (Wednesday, Friday, Saturday) — red, first class.
        if ($offset === 52 || $offset === 54 || $offset === 55) {
            return $this->red(
                'roman:temporale:paschal:pentecost-octave:quattuor-temporum:' . TemporalCalendar::feriaToken($date),
                ObservanceKind::EMBER_DAY,
                TemporalCalendar::feriaLatin($date, 'Quatuor Temporum Pentecostes')
            );
        }

        // The other days within the Octave of Pentecost (Monday, Tuesday, Thursday).
        if ($offset === 50 || $offset === 51 || $offset === 53) {
            return $this->red(
                'roman:temporale:paschal:pentecost-octave:' . TemporalCalendar::feriaToken($date),
                ObservanceKind::WITHIN_OCTAVE,
                TemporalCalendar::feriaLatin($date, 'infra Octavam Pentecostes')
            );
        }

        throw new LogicException(sprintf('Unclassified Eastertide day at Easter offset %d.', $offset));
    }

    private function easterOctaveFeria(DateTimeImmutable $date, int $offset): TemporalObservance
    {
        // The Saturday within the octave has its own name; the rest are numbered ferias.
        $latin = $offset === 6
            ? 'Sabbato in Albis'
            : TemporalCalendar::feriaLatin($date, 'infra Octavam Paschae');

        return $this->white(
            'roman:temporale:paschal:easter-octave:' . TemporalCalendar::feriaToken($date),
            ObservanceKind::WITHIN_OCTAVE,
            RankClass::classI(),
            $latin
        );
    }

    private function white(string $slug, string $kind, RankClass $rank, string $latinName): TemporalObservance
    {
        return $this->mint($slug, $kind, $rank, ElementColour::of(Colour::white()), $latinName);
    }

    private function red(string $slug, string $kind, string $latinName): TemporalObservance
    {
        return $this->mint($slug, $kind, RankClass::classI(), ElementColour::of(Colour::red()), $latinName);
    }

    private function mint(
        string $slug,
        string $kind,
        RankClass $rank,
        ElementColour $colour,
        string $latinName
    ): TemporalObservance {
        return new TemporalObservance(
            ObservanceId::parse($slug),
            ObservanceKind::fromString($kind),
            Season::eastertide(),
            $rank,
            $colour,
            $latinName
        );
    }
}
