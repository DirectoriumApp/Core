<?php

declare(strict_types=1);

namespace Introibo\Core\Sanctoral;

use DateInterval;
use DateTimeImmutable;
use Introibo\Core\Temporal\Computus;
use Introibo\Core\Temporal\TemporalCalendar;
use InvalidArgumentException;

/**
 * The fixed-date sanctoral overlay for one civil year.
 *
 * `forYear($year)` realizes every {@see SanctoralEntry} from a
 * {@see SanctoralData} source onto its civil date, producing a map of
 * `Y-m-d` => the {@see SanctoralObservance}s that fall there. It is the
 * sanctoral analogue of the temporal block-fillers: a standalone layer that
 * says, for each day, "which fixed-date office(s) occur here, realized how?"
 *
 * Composing this overlay with the temporal skeleton — deciding by precedence
 * which office is celebrated and which are commemorated or displaced — is the
 * resolver's work (#29). This layer only places and (from #25) orders the
 * candidates. The default source is the cited {@see CorpusSanctoralData}, read
 * from the generated corpus (#41); any {@see SanctoralData} can be injected
 * without changing this class. See docs/design/sanctoral-overlay-model.md.
 */
final class SanctoralCalendar
{
    private int $year;

    /**
     * Whether a common vigil that falls on a Sunday is anticipated to the preceding Saturday
     * (the pre-1955 rule) rather than kept on its nominal date. Off by default so the 1962
     * placement — and its golden fixture — is unchanged; the resolver turns it on for the
     * Divino Afflatu edition (from {@see \Introibo\Core\Precedence\PrecedenceRules::anticipatesSundayVigils()}).
     */
    private bool $anticipateSundayVigils;

    /** @var array<string, list<SanctoralObservance>> Keyed by 'Y-m-d', in chronological order. */
    private array $days;

    private function __construct(int $year, SanctoralData $data, bool $anticipateSundayVigils)
    {
        if ($year < Computus::GREGORIAN_REFORM_YEAR) {
            throw new InvalidArgumentException(sprintf(
                'The sanctoral overlay is defined from %d onward; got %d.',
                Computus::GREGORIAN_REFORM_YEAR,
                $year
            ));
        }

        $this->year = $year;
        $this->anticipateSundayVigils = $anticipateSundayVigils;
        $this->days = $this->build($data);
    }

    public static function forYear(
        int $year,
        ?SanctoralData $data = null,
        bool $anticipateSundayVigils = false
    ): self {
        return new self($year, $data ?? new CorpusSanctoralData(), $anticipateSundayVigils);
    }

    public function year(): int
    {
        return $this->year;
    }

    /**
     * The sanctoral office(s) placed on a date, or an empty list if none falls
     * there. Co-occurring offices are ordered by rank (highest first), so the
     * resolver (#29) sees the strongest candidate first.
     *
     * @return list<SanctoralObservance>
     */
    public function on(DateTimeImmutable $date): array
    {
        return $this->days[$date->format('Y-m-d')] ?? [];
    }

    /**
     * The whole overlay for the year: 'Y-m-d' => the offices placed there.
     *
     * @return array<string, list<SanctoralObservance>>
     */
    public function all(): array
    {
        return $this->days;
    }

    /**
     * @return array<string, list<SanctoralObservance>>
     */
    private function build(SanctoralData $data): array
    {
        /** @var array<string, list<SanctoralObservance>> $days */
        $days = [];

        foreach ($data->entries() as $entry) {
            $date = $this->placementDate($entry);
            if ($date === null) {
                continue;
            }

            $days[$date->format('Y-m-d')][] = new SanctoralObservance(
                $entry->identity(),
                $entry->rank(),
                $entry->colour(),
                $entry->vigilOfId(),
                $entry->legacyRank(),
                $entry->octaveOfId()
            );
        }

        foreach ($days as $key => $offices) {
            usort($offices, [self::class, 'byPrecedence']);
            $days[$key] = $offices;
        }

        ksort($days);

        return $days;
    }

    /**
     * Order two co-occurring offices for the resolver: highest rank first, then
     * a stable tiebreak on the canonical identifier.
     *
     * Rank comes first — a lower {@see RankClass} ordinal is a higher class
     * (class I = 1), so ascending ordinal is descending precedence. When two
     * offices share a class the canonical {@see ObservanceId} string breaks the
     * tie: it is fixed and edition-invariant, so the order is deterministic and
     * reproducible run to run (which the validation oracle depends on). This is a
     * pre-sort of candidates, not the precedence decision itself — deciding which
     * office is actually celebrated is the resolver's work (#29).
     */
    private static function byPrecedence(SanctoralObservance $a, SanctoralObservance $b): int
    {
        $byRank = $a->rank()->ordinal() <=> $b->rank()->ordinal();
        if ($byRank !== 0) {
            return $byRank;
        }

        return $a->id()->toString() <=> $b->id()->toString();
    }

    /**
     * The civil date an entry is realized on this year, or null if the entry
     * does not occur (a feast fixed to 29 February in a common year).
     *
     * Bissextile handling: in a leap year the sixth day before the Kalends of
     * March is doubled, so every feast on 24–28 February is kept one day later —
     * St Matthias 24 Feb → 25 Feb, St Gabriel of Our Lady of Sorrows 27 Feb →
     * 28 Feb, and a 28 Feb feast → 29 Feb. 24 February itself becomes the
     * bis-sextus feria. A VIGIL on 23 February is the eve of St Matthias, so it
     * tracks its doubled feast: as the feast moves 24 → 25 Feb, its vigil moves
     * 23 → 24 Feb (the pre-1955 Matthias vigil restored for the 1954 edition, #66).
     * A non-vigil 23 Feb feast, being septimo Kalendas, is not doubled and stays.
     */
    private function placementDate(SanctoralEntry $entry): ?DateTimeImmutable
    {
        $month = $entry->month();
        $day = $entry->day();

        if ($month === 2) {
            $leapYear = $this->isLeapYear();
            if ($day === 29 && !$leapYear) {
                return null;
            }
            if ($leapYear) {
                if ($day >= 24 && $day <= 28) {
                    $day++;
                } elseif ($day === 23 && $entry->isVigil()) {
                    $day++;
                }
            }
        }

        $date = TemporalCalendar::utcDate($this->year, $month, $day);

        // Pre-1955: a common vigil that falls on a Sunday is anticipated to the preceding
        // Saturday (1955/1962 omit it instead). Edition-gated — off by default, so the 1962
        // placement is untouched; the resolver enables it for the Divino Afflatu edition.
        if ($this->anticipateSundayVigils && $entry->isVigil() && (int) $date->format('w') === 0) {
            $date = $date->sub(new DateInterval('P1D'));
        }

        return $date;
    }

    private function isLeapYear(): bool
    {
        return TemporalCalendar::utcDate($this->year, 1, 1)->format('L') === '1';
    }
}
