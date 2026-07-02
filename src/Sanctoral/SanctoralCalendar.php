<?php

declare(strict_types=1);

namespace Introibo\Core\Sanctoral;

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
 * candidates. The default source is the provisional {@see SeedSanctoralData};
 * the cited corpus generator (#38) will supply a fuller one without changing
 * this class. See docs/design/sanctoral-overlay-model.md.
 */
final class SanctoralCalendar
{
    private int $year;

    /** @var array<string, list<SanctoralObservance>> Keyed by 'Y-m-d', in chronological order. */
    private array $days;

    private function __construct(int $year, SanctoralData $data)
    {
        if ($year < Computus::GREGORIAN_REFORM_YEAR) {
            throw new InvalidArgumentException(sprintf(
                'The sanctoral overlay is defined from %d onward; got %d.',
                Computus::GREGORIAN_REFORM_YEAR,
                $year
            ));
        }

        $this->year = $year;
        $this->days = $this->build($data);
    }

    public static function forYear(int $year, ?SanctoralData $data = null): self
    {
        return new self($year, $data ?? new SeedSanctoralData());
    }

    public function year(): int
    {
        return $this->year;
    }

    /**
     * The sanctoral office(s) placed on a date, or an empty list if none falls
     * there. From #25 the list is ordered by rank; until then it is in source
     * order.
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
                $entry->colour()
            );
        }

        ksort($days);

        return $days;
    }

    /**
     * The civil date an entry is realized on this year, or null if the entry
     * does not occur (a feast fixed to 29 February in a common year).
     *
     * The bissextile shift that moves the 24–28 February feasts in a leap year
     * is applied in #333; this method places each entry on its own civil date
     * and only guards the one date that need not exist.
     */
    private function placementDate(SanctoralEntry $entry): ?DateTimeImmutable
    {
        $month = $entry->month();
        $day = $entry->day();

        if ($month === 2 && $day === 29 && !$this->isLeapYear()) {
            return null;
        }

        return TemporalCalendar::utcDate($this->year, $month, $day);
    }

    private function isLeapYear(): bool
    {
        return TemporalCalendar::utcDate($this->year, 1, 1)->format('L') === '1';
    }
}
