<?php

declare(strict_types=1);

namespace Directorium\Core\Compare;

use DateInterval;
use DateTimeImmutable;
use Directorium\Core\Temporal\TemporalCalendar;
use InvalidArgumentException;

/**
 * Calendar-mode comparison (#312): resolve a date or an inclusive date range under two
 * or more editions and report, day by day, where they diverge ({@see ComparedDay}).
 *
 * This is the cheapest, earliest comparison mode — it needs no text layer, only ≥2
 * calendar engines (1954 / 1955 / 1962 today) — and is the pattern the later rite- and
 * office-mode diff generalises. See docs/design/calendar-comparison-model.md.
 */
final class CalendarComparator
{
    private EditionResolver $resolver;

    public function __construct()
    {
        $this->resolver = new EditionResolver();
    }

    /**
     * Compare a single date across the given editions.
     *
     * @param list<string> $editions two or more edition selectors (urn or alias)
     */
    public function compareDay(array $editions, DateTimeImmutable $date): ComparedDay
    {
        $urns = $this->normaliseEditions($editions);

        return $this->comparedDay($urns, $date);
    }

    /**
     * Compare every day in an inclusive date range across the given editions.
     *
     * @param list<string> $editions two or more edition selectors (urn or alias)
     */
    public function compareRange(
        array $editions,
        DateTimeImmutable $from,
        DateTimeImmutable $to
    ): CalendarComparison {
        $urns = $this->normaliseEditions($editions);

        $start = self::asDate($from);
        $end = self::asDate($to);
        if ($start > $end) {
            throw new InvalidArgumentException('The comparison range start must not be after its end.');
        }

        $days = [];
        $oneDay = new DateInterval('P1D');
        for ($date = $start; $date <= $end; $date = $date->add($oneDay)) {
            $days[] = $this->comparedDay($urns, $date);
        }

        return new CalendarComparison($urns, $days);
    }

    /**
     * @param list<string> $urns already-normalised edition urns
     */
    private function comparedDay(array $urns, DateTimeImmutable $date): ComparedDay
    {
        $cells = [];
        foreach ($urns as $urn) {
            $cells[$urn] = $this->resolver->cell($urn, $date);
        }

        return ComparedDay::of($date, $cells);
    }

    /**
     * Normalise the selectors to distinct edition urns (in the order given), and
     * require at least two — a comparison of one edition against itself is meaningless.
     *
     * @param list<string> $editions
     *
     * @return list<string>
     */
    private function normaliseEditions(array $editions): array
    {
        $urns = [];
        foreach ($editions as $selector) {
            $urn = $this->resolver->normalise($selector);
            if (!in_array($urn, $urns, true)) {
                $urns[] = $urn;
            }
        }

        if (count($urns) < 2) {
            throw new InvalidArgumentException('A calendar comparison needs at least two distinct editions.');
        }

        return $urns;
    }

    /** Reduce a datetime to a UTC calendar date, so range iteration is DST- and time-safe. */
    private static function asDate(DateTimeImmutable $date): DateTimeImmutable
    {
        return TemporalCalendar::utcDate(
            (int) $date->format('Y'),
            (int) $date->format('m'),
            (int) $date->format('d')
        );
    }
}
