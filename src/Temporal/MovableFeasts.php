<?php

declare(strict_types=1);

namespace Directorium\Core\Temporal;

use DateInterval;
use DateTimeImmutable;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Observance\ObservanceId;
use InvalidArgumentException;
use LogicException;

/**
 * The special movable feasts of the Lord — the ones placed by a Sunday rule
 * rather than filled as part of a contiguous block.
 *
 * `forYear($year)` computes the six for a civil year under the 1962 calendar and
 * emits each as a white {@see TemporalObservance} on its own date:
 *
 *  - **Easter-anchored** (signed offsets from Easter, via the {@see PaschalSkeleton}):
 *    the Most Holy Trinity (Easter+56), Corpus Christi (Easter+60), and the Most
 *    Sacred Heart (Easter+68) — all first class.
 *  - **Civil-anchored** (a Sunday rule, the placement logic this class exists for):
 *    the Most Holy Name of Jesus (the Sunday between 2 and 5 January, or 2 January
 *    when no Sunday falls there), the Holy Family (the Sunday between 7 and 13
 *    January, the first after the Epiphany), and Christ the King (the last Sunday
 *    of October) — the first two second class, Christ the King first class.
 *
 * These feasts overlay the temporal skeleton the block-fillers emit — the Most
 * Holy Trinity, for instance, occupies the first-Sunday-after-Pentecost slot —
 * so this class places them; composing them onto the skeleton by precedence and
 * commemoration is the resolver's job (#29). See docs/design/temporal-fill-model.md.
 *
 * Two further moveable feasts the pre-1955 rite keeps that the 1962 rubrics dropped
 * are minted only when the edition declares their archetype (1954 and 1955), gated
 * on {@see TemporalAttributes::has()} so the 1962 calendar is unmoved (#453): the
 * Passiontide **Seven Sorrows of the BVM** (the Friday in Passion Week, Easter-9)
 * and the moveable **Solemnity of St Joseph** (the Wednesday before the 3rd Sunday
 * after Easter, Easter+17). The Solemnity's common octave is minted by {@see Eastertide}.
 */
final class MovableFeasts implements MovableFeastCalendar
{
    private int $year;

    private DateTimeImmutable $easter;

    private DateTimeImmutable $trinitySunday;

    private DateTimeImmutable $corpusChristi;

    private DateTimeImmutable $sacredHeart;

    private DateTimeImmutable $holyName;

    private DateTimeImmutable $holyFamily;

    private DateTimeImmutable $christTheKing;

    private TemporalAttributes $attributes;

    /** @var array<string, TemporalObservance> Keyed by 'Y-m-d', in chronological order. */
    private array $feasts;

    private function __construct(int $year, ?Corpus $corpus = null, ?string $editionDir = null)
    {
        if ($year < Computus::GREGORIAN_REFORM_YEAR) {
            throw new InvalidArgumentException(sprintf(
                'The movable feasts are defined from %d onward; got %d.',
                Computus::GREGORIAN_REFORM_YEAR,
                $year
            ));
        }

        $this->attributes = new TemporalAttributes($corpus, $editionDir);
        $skeleton = PaschalSkeleton::forYear($year);
        $this->year = $year;
        $this->easter = $skeleton->easter();
        $this->trinitySunday = $skeleton->date('trinity-sunday');
        $this->corpusChristi = $skeleton->date('corpus-christi');
        $this->sacredHeart = $skeleton->date('sacred-heart');
        $this->holyName = $this->computeHolyName();
        $this->holyFamily = $this->computeHolyFamily();
        $this->christTheKing = $this->computeChristTheKing();

        $this->feasts = $this->build();
    }

    public static function forYear(int $year, ?Corpus $corpus = null, ?string $editionDir = null): self
    {
        return new self($year, $corpus, $editionDir);
    }

    public function year(): int
    {
        return $this->year;
    }

    /** The Most Holy Name of Jesus — the Sunday of 2–5 January, or 2 January if none. */
    public function holyName(): DateTimeImmutable
    {
        return $this->holyName;
    }

    /** The Holy Family — the Sunday of 7–13 January (the first after the Epiphany). */
    public function holyFamily(): DateTimeImmutable
    {
        return $this->holyFamily;
    }

    /** Christ the King — the last Sunday of October. */
    public function christTheKing(): DateTimeImmutable
    {
        return $this->christTheKing;
    }

    public function trinitySunday(): DateTimeImmutable
    {
        return $this->trinitySunday;
    }

    public function corpusChristi(): DateTimeImmutable
    {
        return $this->corpusChristi;
    }

    public function sacredHeart(): DateTimeImmutable
    {
        return $this->sacredHeart;
    }

    /**
     * The six movable feasts, keyed by 'Y-m-d', in chronological order.
     *
     * @return array<string, TemporalObservance>
     */
    public function feasts(): array
    {
        return $this->feasts;
    }

    /** The movable feast on a date, or null if none falls there. */
    public function on(DateTimeImmutable $date): ?TemporalObservance
    {
        return $this->feasts[$date->format('Y-m-d')] ?? null;
    }

    /**
     * @return array<string, TemporalObservance>
     */
    private function build(): array
    {
        $feasts = [
            $this->holyName->format('Y-m-d') => $this->feast(
                'roman:temporale:christmas:holy-name',
                Season::christmastide(),
                'holy-name',
                $this->holyName
            ),
            $this->holyFamily->format('Y-m-d') => $this->feast(
                'roman:temporale:epiphany:holy-family',
                Season::epiphany(),
                'holy-family',
                $this->holyFamily
            ),
            $this->trinitySunday->format('Y-m-d') => $this->feast(
                'roman:temporale:paschal:trinity-sunday',
                Season::pentecost(),
                'trinity-sunday',
                $this->trinitySunday
            ),
            $this->corpusChristi->format('Y-m-d') => $this->feast(
                'roman:temporale:paschal:corpus-christi',
                Season::pentecost(),
                'corpus-christi',
                $this->corpusChristi
            ),
            $this->sacredHeart->format('Y-m-d') => $this->feast(
                'roman:temporale:paschal:sacred-heart',
                Season::pentecost(),
                'sacred-heart',
                $this->sacredHeart
            ),
            $this->christTheKing->format('Y-m-d') => $this->feast(
                'roman:temporale:month-computed:christ-the-king',
                Season::pentecost(),
                'christ-the-king',
                $this->christTheKing
            ),
        ];

        // The two moveable feasts the pre-1955 rite keeps that the 1962 rubrics dropped (#453),
        // minted only when the edition declares the archetype (1954 and 1955; never 1962, whose
        // has() is false — so the base calendar is unmoved). The Passiontide Seven Sorrows of the
        // BVM falls on the Friday in Passion Week (Easter-9) and the moveable Solemnity of St
        // Joseph on the Wednesday before the 3rd Sunday after Easter (Easter+17); the Solemnity's
        // common octave is minted separately by Eastertide. Their seasons match the block they sit
        // in (the day's own season comes from the temporal filler, not these overlays).
        if ($this->attributes->has('seven-sorrows')) {
            $sevenSorrows = $this->easter->sub(new DateInterval('P9D'));
            $feasts[$sevenSorrows->format('Y-m-d')] = $this->feast(
                'roman:temporale:paschal:passion-week:septem-dolorum',
                Season::passiontide(),
                'seven-sorrows',
                $sevenSorrows
            );
        }
        if ($this->attributes->has('solemnitas-ioseph')) {
            $solemnity = TemporalCalendar::addDays($this->easter, 17);
            $feasts[$solemnity->format('Y-m-d')] = $this->feast(
                'roman:temporale:paschal:solemnitas-ioseph',
                Season::eastertide(),
                'solemnitas-ioseph',
                $solemnity
            );
        }

        ksort($feasts);

        return $feasts;
    }

    private function computeHolyName(): DateTimeImmutable
    {
        // The Sunday falling 2–5 January; if none, the feast is kept on 2 January.
        for ($day = 2; $day <= 5; $day++) {
            $date = TemporalCalendar::utcDate($this->year, 1, $day);
            if (TemporalCalendar::isSunday($date)) {
                return $date;
            }
        }

        return TemporalCalendar::utcDate($this->year, 1, 2);
    }

    private function computeHolyFamily(): DateTimeImmutable
    {
        // The first Sunday after the Epiphany always falls within 7–13 January.
        for ($day = 7; $day <= 13; $day++) {
            $date = TemporalCalendar::utcDate($this->year, 1, $day);
            if (TemporalCalendar::isSunday($date)) {
                return $date;
            }
        }

        throw new LogicException('Unreachable: a seven-day span always contains a Sunday.');
    }

    private function computeChristTheKing(): DateTimeImmutable
    {
        // The last Sunday of October: the latest Sunday on or before 31 October.
        $lastOfOctober = TemporalCalendar::utcDate($this->year, 10, 31);
        $weekday = (int) $lastOfOctober->format('w'); // 0 = Sunday … 6 = Saturday

        return $lastOfOctober->sub(new DateInterval('P' . $weekday . 'D'));
    }

    /**
     * Build one movable feast: the slug and season are structural (the feast's
     * placement rule), while the kind, rank, colour, and Latin name come from the
     * corpus archetype overlay. `$date` is the feast's own date, unused by these
     * fixed (non-ordinal, non-feria) names.
     */
    private function feast(string $slug, Season $season, string $archetype, DateTimeImmutable $date): TemporalObservance
    {
        $office = $this->attributes->archetype($archetype);

        return new TemporalObservance(
            ObservanceId::parse($slug),
            $office->kind(),
            $season,
            $office->rank(),
            $office->colour(),
            $office->renderName($date)
        );
    }
}
