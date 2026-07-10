<?php

declare(strict_types=1);

namespace Directorium\Core\Compare;

/**
 * A calendar-mode comparison over a date range: the editions compared (as edition
 * urns, in the order requested) and the ordered per-day results ({@see ComparedDay}).
 *
 * Carries the roll-up a UI's "only-changed" filter and summary line need — how many of
 * the days in the range the editions actually disagree on.
 */
final class CalendarComparison
{
    /** @var list<string> */
    private array $editions;

    /** @var list<ComparedDay> */
    private array $days;

    /**
     * @param list<string> $editions
     * @param list<ComparedDay> $days
     */
    public function __construct(array $editions, array $days)
    {
        $this->editions = $editions;
        $this->days = $days;
    }

    /** @return list<string> */
    public function editions(): array
    {
        return $this->editions;
    }

    /** @return list<ComparedDay> */
    public function days(): array
    {
        return $this->days;
    }

    public function dayCount(): int
    {
        return count($this->days);
    }

    /** @return list<ComparedDay> the days on which at least one field diverges */
    public function divergentDays(): array
    {
        return array_values(array_filter(
            $this->days,
            static fn (ComparedDay $day): bool => $day->isDivergent()
        ));
    }

    public function divergentDayCount(): int
    {
        return count($this->divergentDays());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'editions' => $this->editions,
            'dayCount' => $this->dayCount(),
            'divergentDayCount' => $this->divergentDayCount(),
            'days' => array_map(
                static fn (ComparedDay $day): array => $day->toArray(),
                $this->days
            ),
        ];
    }
}
