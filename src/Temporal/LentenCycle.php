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
 * The temporal skeleton of the Septuagesima-to-Passiontide block — the
 * penitential run toward Easter, entirely anchored to Easter via the
 * {@see PaschalSkeleton}.
 *
 * `forYear($year)` fills every day from Septuagesima Sunday through the eve of
 * Palm Sunday with its principal temporal office under the 1960 rubrics. `$year`
 * is the civil year in which Easter falls (the whole block lies within it). The
 * exclusive end boundary is Palm Sunday, handed off to the Holy Week block (#19);
 * Septuagesima Sunday is where the {@see ChristmasCycle} hands off.
 *
 * Three seasons pass in order: **Septuagesima** (the violet fore-season, no
 * fasting), **Lent** (from Ash Wednesday), and **Passiontide** (from Passion
 * Sunday). Everything is violet. Under the 1960 rubrics the Lenten ferias carry
 * a raised rank — Ash Wednesday is a first-class feria, the Ember Days of Lent
 * are second class, and the remaining ferias of Lent and Passiontide are third
 * class — while the pre-Lenten ferias remain fourth class. This is the temporal
 * skeleton only; the sanctoral and precedence compose on top (#23 / #29). See
 * docs/design/temporal-fill-model.md.
 */
final class LentenCycle
{
    private int $year;

    private DateTimeImmutable $easter;

    private DateTimeImmutable $septuagesima;

    private DateTimeImmutable $ashWednesday;

    private DateTimeImmutable $passionSunday;

    private DateTimeImmutable $palmSunday;

    /** @var array<string, TemporalObservance> Keyed by 'Y-m-d', in chronological order. */
    private array $days;

    private function __construct(int $year)
    {
        if ($year < Computus::GREGORIAN_REFORM_YEAR) {
            throw new InvalidArgumentException(sprintf(
                'The Lenten cycle is defined from %d onward; got %d.',
                Computus::GREGORIAN_REFORM_YEAR,
                $year
            ));
        }

        $skeleton = PaschalSkeleton::forYear($year);
        $this->year = $year;
        $this->easter = $skeleton->easter();
        $this->septuagesima = $skeleton->septuagesima();
        $this->ashWednesday = $skeleton->ashWednesday();
        $this->passionSunday = $skeleton->date('passion-sunday');
        $this->palmSunday = $skeleton->date('palm-sunday');

        $this->days = [];
        for ($date = $this->septuagesima; $date < $this->palmSunday; $date = TemporalCalendar::addDays($date, 1)) {
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

    /** Septuagesima Sunday: the first day of this block. */
    public function septuagesima(): DateTimeImmutable
    {
        return $this->septuagesima;
    }

    public function ashWednesday(): DateTimeImmutable
    {
        return $this->ashWednesday;
    }

    public function passionSunday(): DateTimeImmutable
    {
        return $this->passionSunday;
    }

    /** Palm Sunday: the first day NOT in this block (handed to Holy Week, #19). */
    public function endsBefore(): DateTimeImmutable
    {
        return $this->palmSunday;
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
        $offset = -TemporalCalendar::daysBetween($date, $this->easter); // days before Easter, as a negative

        // Pre-Lenten Sundays (Septuagesima season) — second class, violet.
        if ($offset === -63) {
            return $this->preLentSunday('septuagesima', 'in Septuagesima');
        }
        if ($offset === -56) {
            return $this->preLentSunday('sexagesima', 'in Sexagesima');
        }
        if ($offset === -49) {
            return $this->preLentSunday('quinquagesima', 'in Quinquagesima');
        }

        // Ash Wednesday — the first-class feria that opens Lent.
        if ($offset === -46) {
            return $this->mint(
                'roman:temporale:paschal:ash-wednesday',
                ObservanceKind::FERIA,
                Season::lent(),
                RankClass::classI(),
                ElementColour::of(Colour::violet()),
                'Feria IV Cinerum'
            );
        }

        // Sundays of Lent — first class, violet (Laetare permits rose).
        if ($offset === -42 || $offset === -35 || $offset === -28 || $offset === -21) {
            $n = intdiv($offset + 49, 7); // -42 => 1 … -21 => 4
            $colour = $offset === -21 ? ElementColour::violetWithRose() : ElementColour::of(Colour::violet());

            return $this->mint(
                'roman:temporale:paschal:lent-' . $n,
                ObservanceKind::SUNDAY,
                Season::lent(),
                RankClass::classI(),
                $colour,
                'Dominica ' . TemporalCalendar::roman($n) . ' in Quadragesima'
            );
        }

        // Passion Sunday — first class, opens Passiontide.
        if ($offset === -14) {
            return $this->mint(
                'roman:temporale:paschal:passion-sunday',
                ObservanceKind::SUNDAY,
                Season::passiontide(),
                RankClass::classI(),
                ElementColour::of(Colour::violet()),
                'Dominica I Passionis'
            );
        }

        // Ferias.
        if ($offset >= -62 && $offset <= -57) {
            return $this->preLentFeria($date, 'septuagesima', 'Septuagesimae');
        }
        if ($offset >= -55 && $offset <= -50) {
            return $this->preLentFeria($date, 'sexagesima', 'Sexagesimae');
        }
        if ($offset >= -48 && $offset <= -47) {
            return $this->preLentFeria($date, 'quinquagesima', 'Quinquagesimae');
        }

        // Thursday–Saturday after Ash Wednesday — third-class Lenten ferias.
        if ($offset >= -45 && $offset <= -43) {
            return $this->mint(
                'roman:temporale:paschal:post-cineres:' . TemporalCalendar::feriaToken($date),
                ObservanceKind::FERIA,
                Season::lent(),
                RankClass::classIII(),
                ElementColour::of(Colour::violet()),
                TemporalCalendar::feriaLatin($date, 'post Cineres')
            );
        }

        if ($offset >= -41 && $offset <= -15) {
            return $this->lentenFeria($date, $offset);
        }

        // Ferias of Passion Week (Monday–Saturday before Palm Sunday) — third class.
        if ($offset >= -13 && $offset <= -8) {
            return $this->mint(
                'roman:temporale:paschal:passion-week:' . TemporalCalendar::feriaToken($date),
                ObservanceKind::FERIA,
                Season::passiontide(),
                RankClass::classIII(),
                ElementColour::of(Colour::violet()),
                TemporalCalendar::feriaLatin($date, 'infra Hebdomadam Passionis')
            );
        }

        throw new LogicException(sprintf('Unclassified Lenten-cycle day at Easter offset %d.', $offset));
    }

    private function preLentSunday(string $slug, string $latinSuffix): TemporalObservance
    {
        return $this->mint(
            'roman:temporale:paschal:' . $slug,
            ObservanceKind::SUNDAY,
            Season::septuagesima(),
            RankClass::classII(),
            ElementColour::of(Colour::violet()),
            'Dominica ' . $latinSuffix
        );
    }

    private function preLentFeria(DateTimeImmutable $date, string $slug, string $latinWeek): TemporalObservance
    {
        return $this->mint(
            'roman:temporale:paschal:' . $slug . ':' . TemporalCalendar::feriaToken($date),
            ObservanceKind::FERIA,
            Season::septuagesima(),
            RankClass::classIV(),
            ElementColour::of(Colour::violet()),
            TemporalCalendar::feriaLatin($date, 'infra Hebdomadam ' . $latinWeek)
        );
    }

    private function lentenFeria(DateTimeImmutable $date, int $offset): TemporalObservance
    {
        // Ember Days of Lent — Wed/Fri/Sat of the first week — second class.
        if ($offset === -39 || $offset === -37 || $offset === -36) {
            return $this->mint(
                'roman:temporale:paschal:quattuor-temporum-quadragesimae:' . TemporalCalendar::feriaToken($date),
                ObservanceKind::EMBER_DAY,
                Season::lent(),
                RankClass::classII(),
                ElementColour::of(Colour::violet()),
                TemporalCalendar::feriaLatin($date, 'Quatuor Temporum Quadragesimae')
            );
        }

        $week = 1 + intdiv($offset + 42, 7); // ferias of Lenten weeks I–IV
        $phrase = 'infra Hebdomadam ' . TemporalCalendar::roman($week) . ' in Quadragesima';

        return $this->mint(
            'roman:temporale:paschal:lent-week-' . $week . ':' . TemporalCalendar::feriaToken($date),
            ObservanceKind::FERIA,
            Season::lent(),
            RankClass::classIII(),
            ElementColour::of(Colour::violet()),
            TemporalCalendar::feriaLatin($date, $phrase)
        );
    }

    private function mint(
        string $slug,
        string $kind,
        Season $season,
        RankClass $rank,
        ElementColour $colour,
        string $latinName
    ): TemporalObservance {
        return new TemporalObservance(
            ObservanceId::parse($slug),
            ObservanceKind::fromString($kind),
            $season,
            $rank,
            $colour,
            $latinName
        );
    }
}
