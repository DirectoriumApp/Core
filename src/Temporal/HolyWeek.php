<?php

declare(strict_types=1);

namespace Directorium\Core\Temporal;

use DateTimeImmutable;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Observance\ObservanceId;
use InvalidArgumentException;
use LogicException;

/**
 * The temporal skeleton of Holy Week and the Paschal Triduum — Palm Sunday
 * through Holy Saturday, the summit of the liturgical year.
 *
 * `forYear($year)` fills the seven days `[Palm Sunday, Easter)` (Easter−7 to
 * Easter−1) under the 1960 rubrics. `$year` is the civil year in which Easter
 * falls. Every day is **first class** and belongs to Passiontide; no sanctoral
 * feast may displace them, and the three days of the **Sacrum Triduum** (Holy
 * Thursday, Good Friday, Holy Saturday) stand at the very apex of the Table of
 * Precedence — {@see triduum()} marks them so the precedence engine (#29) can
 * give them absolute priority. Septuagesima-to-Passiontide (#18) hands off at
 * Palm Sunday; Easter Sunday opens Eastertide (#20).
 *
 * Colours are the **principal** vestment colour of each day's chief act under
 * the 1955/1962 reform: violet for Palm Sunday and the first ferias, white for
 * the Mass of the Lord's Supper, **black** for the Good Friday liturgy (the
 * modern rite's red is not the 1962 colour), violet for Holy Saturday. The
 * within-day changes the reform introduced — the red Palm procession, the violet
 * Good Friday Communion, the violet-to-white Paschal Vigil — are a per-element
 * concern of the rubrics layer, not this skeleton. See
 * docs/design/temporal-fill-model.md.
 */
final class HolyWeek implements TriduumWindow
{
    private int $year;

    private DateTimeImmutable $easter;

    private DateTimeImmutable $palmSunday;

    private DateTimeImmutable $maundyThursday;

    private DateTimeImmutable $goodFriday;

    private DateTimeImmutable $holySaturday;

    private TemporalAttributes $attributes;

    /** @var array<string, TemporalObservance> Keyed by 'Y-m-d', in chronological order. */
    private array $days;

    private function __construct(int $year, ?Corpus $corpus = null, ?string $editionDir = null)
    {
        if ($year < Computus::GREGORIAN_REFORM_YEAR) {
            throw new InvalidArgumentException(sprintf(
                'Holy Week is defined from %d onward; got %d.',
                Computus::GREGORIAN_REFORM_YEAR,
                $year
            ));
        }

        $this->attributes = new TemporalAttributes($corpus, $editionDir);
        $skeleton = PaschalSkeleton::forYear($year);
        $this->year = $year;
        $this->easter = $skeleton->easter();
        $this->palmSunday = $skeleton->date('palm-sunday');
        $this->maundyThursday = $skeleton->date('maundy-thursday');
        $this->goodFriday = $skeleton->date('good-friday');
        $this->holySaturday = $skeleton->date('holy-saturday');

        $this->days = [];
        for ($date = $this->palmSunday; $date < $this->easter; $date = TemporalCalendar::addDays($date, 1)) {
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

    /** Palm Sunday: the first day of this block. */
    public function palmSunday(): DateTimeImmutable
    {
        return $this->palmSunday;
    }

    public function maundyThursday(): DateTimeImmutable
    {
        return $this->maundyThursday;
    }

    public function goodFriday(): DateTimeImmutable
    {
        return $this->goodFriday;
    }

    public function holySaturday(): DateTimeImmutable
    {
        return $this->holySaturday;
    }

    /** Easter Sunday: the first day NOT in this block (handed to Eastertide, #20). */
    public function endsBefore(): DateTimeImmutable
    {
        return $this->easter;
    }

    /**
     * The three days of the Sacrum Triduum, in order — the apex of the Table of
     * Precedence, which no observance may displace.
     *
     * @return array{DateTimeImmutable, DateTimeImmutable, DateTimeImmutable}
     */
    public function triduum(): array
    {
        return [$this->maundyThursday, $this->goodFriday, $this->holySaturday];
    }

    public function isTriduum(DateTimeImmutable $date): bool
    {
        foreach ($this->triduum() as $day) {
            if (TemporalCalendar::sameDay($date, $day)) {
                return true;
            }
        }

        return false;
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

        if ($offset === -7) {
            return $this->mint('roman:temporale:paschal:palm-sunday', 'palm-sunday', $date);
        }

        // Monday, Tuesday, Wednesday of Holy Week — first-class ferias, violet.
        if ($offset === -6 || $offset === -5 || $offset === -4) {
            return $this->mint(
                'roman:temporale:paschal:holy-week:' . TemporalCalendar::feriaToken($date),
                'holy-week-feria',
                $date
            );
        }

        // The Sacred Triduum.
        if ($offset === -3) {
            return $this->mint('roman:temporale:paschal:maundy-thursday', 'maundy-thursday', $date);
        }
        if ($offset === -2) {
            return $this->mint('roman:temporale:paschal:good-friday', 'good-friday', $date);
        }
        if ($offset === -1) {
            return $this->mint('roman:temporale:paschal:holy-saturday', 'holy-saturday', $date);
        }

        throw new LogicException(sprintf('Unclassified Holy Week day at Easter offset %d.', $offset));
    }

    /**
     * Mint the temporal office of a Holy Week day: the slug is structural, the
     * season is always Passiontide, and the kind, rank, colour, and Latin name
     * come from the corpus archetype overlay (Holy Week has no ordinal names).
     */
    private function mint(string $slug, string $archetype, DateTimeImmutable $date): TemporalObservance
    {
        $office = $this->attributes->archetype($archetype);

        return new TemporalObservance(
            ObservanceId::parse($slug),
            $office->kind(),
            Season::passiontide(),
            $office->rank(),
            $office->colour(),
            $office->renderName($date)
        );
    }
}
