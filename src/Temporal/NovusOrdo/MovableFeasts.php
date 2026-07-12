<?php

declare(strict_types=1);

namespace Directorium\Core\Temporal\NovusOrdo;

use DateTimeImmutable;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Observance\ObservanceId;
use Directorium\Core\Temporal\ChristmasCycle;
use Directorium\Core\Temporal\Computus;
use Directorium\Core\Temporal\MovableFeastCalendar;
use Directorium\Core\Temporal\PaschalSkeleton;
use Directorium\Core\Temporal\Season;
use Directorium\Core\Temporal\TemporalAttributes;
use Directorium\Core\Temporal\TemporalCalendar;
use Directorium\Core\Temporal\TemporalObservance;
use InvalidArgumentException;

/**
 * The movable solemnities of the Lord the Novus Ordo keeps in Ordinary Time — the
 * reform's counterpart of the traditional {@see \Directorium\Core\Temporal\MovableFeasts},
 * pruned to the four the reformed general calendar retains and re-placed / re-titled.
 *
 * `forYear($year)` computes the four for the civil year and emits each as a white
 * {@see TemporalObservance} on its own date:
 *
 *  - **Easter-anchored** (via the {@see PaschalSkeleton}): the Most Holy Trinity
 *    (the Sunday after Pentecost, Easter+56), the Most Holy Body and Blood of Christ
 *    (the Thursday after Trinity, Easter+60 — the universal-calendar placement; the
 *    Sunday transfer where it is not a holy day of obligation is a *conference* resolve-time
 *    option, a future addition on the conference-propers hook, not a dated decree — see
 *    docs/design/edition-governance.md), and the Most Sacred Heart (the Friday of
 *    the third week after Pentecost, Easter+68).
 *  - **Advent-anchored**: Christ the King, the **last** Sunday of Ordinary Time — the
 *    Sunday before the First Sunday of Advent — not the traditional last Sunday of
 *    October.
 *
 * These overlay the green Ordinary-Time office {@see OrdinaryTime} emits beneath them, so
 * this class places them; composing them by precedence (each a line-3 Solemnity that wins
 * its Sunday) is the resolver's job. The reform drops the traditional Holy Name and the
 * January Holy Family from the movable set (its Holy Family is the Sunday within the
 * Christmas octave, minted by the Novus-Ordo Advent/Christmas filler as `no-holy-family`)
 * — so unlike the traditional class this mints exactly four, and never reads a Holy-Name /
 * Holy-Family archetype the reformed corpus does not carry. The office facts come from the
 * edition's temporal archetypes; only the placement arithmetic stays in code.
 *
 * @internal Not part of the public API (docs/api-stability.md).
 */
final class MovableFeasts implements MovableFeastCalendar
{
    private DateTimeImmutable $trinity;

    private DateTimeImmutable $corpusChristi;

    private DateTimeImmutable $sacredHeart;

    private DateTimeImmutable $christTheKing;

    private TemporalAttributes $attributes;

    /** @var array<string, TemporalObservance> Keyed by 'Y-m-d', in chronological order. */
    private array $feasts;

    private function __construct(int $year, ?Corpus $corpus = null, ?string $editionDir = null)
    {
        if ($year < Computus::GREGORIAN_REFORM_YEAR) {
            throw new InvalidArgumentException(sprintf(
                'The Novus-Ordo movable feasts are defined from %d onward; got %d.',
                Computus::GREGORIAN_REFORM_YEAR,
                $year
            ));
        }

        $this->attributes = new TemporalAttributes($corpus, $editionDir);
        $skeleton = PaschalSkeleton::forYear($year);
        $this->trinity = $skeleton->date('trinity-sunday');
        $this->corpusChristi = $skeleton->date('corpus-christi');
        $this->sacredHeart = $skeleton->date('sacred-heart');
        // The last Sunday of Ordinary Time: the Sunday before the First Sunday of Advent.
        $this->christTheKing = TemporalCalendar::addDays(ChristmasCycle::firstSundayOfAdvent($year), -7);

        $this->feasts = $this->build();
    }

    public static function forYear(int $year, ?Corpus $corpus = null, ?string $editionDir = null): self
    {
        return new self($year, $corpus, $editionDir);
    }

    public function trinity(): DateTimeImmutable
    {
        return $this->trinity;
    }

    public function corpusChristi(): DateTimeImmutable
    {
        return $this->corpusChristi;
    }

    public function sacredHeart(): DateTimeImmutable
    {
        return $this->sacredHeart;
    }

    public function christTheKing(): DateTimeImmutable
    {
        return $this->christTheKing;
    }

    /**
     * The four movable solemnities, keyed by 'Y-m-d', in chronological order.
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
            $this->trinity->format('Y-m-d') => $this->feast(
                'roman:temporale:paschal:trinity-sunday',
                'no-trinity',
                $this->trinity
            ),
            $this->corpusChristi->format('Y-m-d') => $this->feast(
                'roman:temporale:paschal:corpus-christi',
                'no-corpus-christi',
                $this->corpusChristi
            ),
            $this->sacredHeart->format('Y-m-d') => $this->feast(
                'roman:temporale:paschal:sacred-heart',
                'no-sacred-heart',
                $this->sacredHeart
            ),
            $this->christTheKing->format('Y-m-d') => $this->feast(
                'roman:temporale:ordinary-time:christ-the-king',
                'no-christ-the-king',
                $this->christTheKing
            ),
        ];

        ksort($feasts);

        return $feasts;
    }

    /**
     * Build one movable solemnity: the slug is structural and the season is always the reform's
     * {@see Season::ordinaryTime()} (all four fall in the green season after Pentecost), while
     * the kind, rank, colour, and Latin name come from the edition's temporal archetype. `$date`
     * is the feast's own date, unused by these fixed (non-ordinal) names.
     */
    private function feast(string $slug, string $archetype, DateTimeImmutable $date): TemporalObservance
    {
        $office = $this->attributes->archetype($archetype);

        return new TemporalObservance(
            ObservanceId::parse($slug),
            $office->kind(),
            Season::ordinaryTime(),
            $office->rank(),
            $office->colour(),
            $office->renderName($date)
        );
    }
}
