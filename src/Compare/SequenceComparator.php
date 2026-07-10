<?php

declare(strict_types=1);

namespace Directorium\Core\Compare;

use DateTimeImmutable;
use Directorium\Core\Temporal\TemporalCalendar;
use InvalidArgumentException;

/**
 * Year-sequence comparison for the slider (#313): produce a diff-tagged
 * {@see ComparedSequence} for a fixed reference scrubbed along one axis.
 *
 * Two axes, both reusing the calendar-mode cell + field taxonomy ({@see EditionDayCell},
 * {@see ComparisonField}):
 *
 * - {@see acrossYears()} fixes a civil date and an edition and varies the year — the
 *   "watch a fixed date drift through the movable cycle" story (a date that is a green
 *   feria one year and violet Sexagesima the next as Easter moves).
 * - {@see acrossEditions()} fixes a date and varies the edition — the within-tradition
 *   "watch the 1955/1960 reforms take effect" story.
 *
 * Each point carries the fields that changed from the previous one, so the slider can
 * mark exactly where a change lands. See docs/design/calendar-comparison-model.md.
 */
final class SequenceComparator
{
    private EditionResolver $resolver;

    public function __construct()
    {
        $this->resolver = new EditionResolver();
    }

    /**
     * Scrub a fixed civil date (the anchor's month and day) across a span of years under
     * one edition. Years in which the anchor's month/day is not a real date (29 February
     * in a common year) are skipped.
     *
     * @param string|null $edition an edition selector, or null for the default (1962)
     */
    public function acrossYears(
        DateTimeImmutable $anchor,
        int $fromYear,
        int $toYear,
        ?string $edition = null
    ): ComparedSequence {
        if ($fromYear > $toYear) {
            throw new InvalidArgumentException('The sequence start year must not be after its end year.');
        }

        $urn = $this->resolver->normalise($edition ?? '');
        $month = (int) $anchor->format('m');
        $day = (int) $anchor->format('d');

        $points = [];
        $previous = null;
        for ($year = $fromYear; $year <= $toYear; $year++) {
            if (!checkdate($month, $day, $year)) {
                continue;
            }

            $date = TemporalCalendar::utcDate($year, $month, $day);
            $cell = $this->resolver->cell($urn, $date);
            $points[] = new SequencePoint(
                (string) $year,
                $date,
                $cell,
                $previous === null ? [] : self::changedFields($previous, $cell)
            );
            $previous = $cell;
        }

        return new ComparedSequence(ComparedSequence::AXIS_YEAR, $points);
    }

    /**
     * Scrub a single date across two or more editions, in the order given. This is the
     * same information a one-day {@see CalendarComparator::compareDay()} carries, framed
     * as an ordered slider run where each edition is tagged against the previous one.
     *
     * @param list<string> $editions two or more edition selectors (urn or alias)
     */
    public function acrossEditions(DateTimeImmutable $date, array $editions): ComparedSequence
    {
        $urns = [];
        foreach ($editions as $selector) {
            $urn = $this->resolver->normalise($selector);
            if (!in_array($urn, $urns, true)) {
                $urns[] = $urn;
            }
        }

        if (count($urns) < 2) {
            throw new InvalidArgumentException('An edition sequence needs at least two distinct editions.');
        }

        $anchor = TemporalCalendar::utcDate(
            (int) $date->format('Y'),
            (int) $date->format('m'),
            (int) $date->format('d')
        );

        $points = [];
        $previous = null;
        foreach ($urns as $urn) {
            $cell = $this->resolver->cell($urn, $anchor);
            $points[] = new SequencePoint(
                $urn,
                $anchor,
                $cell,
                $previous === null ? [] : self::changedFields($previous, $cell)
            );
            $previous = $cell;
        }

        return new ComparedSequence(ComparedSequence::AXIS_EDITION, $points);
    }

    /**
     * The fields on which two cells differ, in {@see ComparisonField} order.
     *
     * @return list<string>
     */
    private static function changedFields(EditionDayCell $previous, EditionDayCell $current): array
    {
        $before = $previous->comparableValues();
        $after = $current->comparableValues();

        $changed = [];
        foreach (ComparisonField::all() as $field) {
            if ($before[$field] !== $after[$field]) {
                $changed[] = $field;
            }
        }

        return $changed;
    }
}
