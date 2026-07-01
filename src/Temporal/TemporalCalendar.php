<?php

declare(strict_types=1);

namespace Introibo\Core\Temporal;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Stateless calendar and liturgical-naming helpers shared by the temporal
 * block-fillers ({@see ChristmasCycle}, {@see LentenCycle}, …).
 *
 * Two concerns live here so every filler expresses them identically:
 *  - plain date arithmetic on UTC-midnight {@see DateTimeImmutable} values
 *    (the whole engine works in whole days at a fixed zone, free of DST); and
 *  - the liturgical naming of weekdays — the feria numbering (Monday = feria II
 *    … Friday = feria VI, Saturday = Sabbatum) and the Roman numerals used in
 *    structural slugs and Latin names.
 *
 * Sundays are addressed by their slot, never a feria token, so the two feria
 * helpers are never called for a Sunday.
 */
final class TemporalCalendar
{
    /** @var array<int, string> */
    private const ROMAN = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII'];

    private function __construct()
    {
    }

    public static function roman(int $number): string
    {
        if (!isset(self::ROMAN[$number])) {
            throw new InvalidArgumentException(sprintf('No Roman numeral for %d (supported: 1–7).', $number));
        }

        return self::ROMAN[$number];
    }

    /** The structural feria token of a weekday: `feria-2` … `feria-6`, or `sabbatum`. */
    public static function feriaToken(DateTimeImmutable $date): string
    {
        $dow = (int) $date->format('N'); // 1 = Monday … 7 = Sunday

        return $dow === 6 ? 'sabbatum' : 'feria-' . ($dow + 1);
    }

    /** The Latin feria name of a weekday, with the given season phrase appended. */
    public static function feriaLatin(DateTimeImmutable $date, string $phrase): string
    {
        $dow = (int) $date->format('N'); // 1 = Monday … 7 = Sunday

        if ($dow === 6) {
            return 'Sabbato ' . $phrase;
        }

        return 'Feria ' . self::roman($dow + 1) . ' ' . $phrase;
    }

    public static function isSunday(DateTimeImmutable $date): bool
    {
        return (int) $date->format('N') === 7;
    }

    public static function sameDay(DateTimeImmutable $a, DateTimeImmutable $b): bool
    {
        return $a->format('Y-m-d') === $b->format('Y-m-d');
    }

    public static function daysBetween(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        return (int) $from->diff($to)->days;
    }

    public static function addDays(DateTimeImmutable $date, int $days): DateTimeImmutable
    {
        return $date->add(new DateInterval('P' . $days . 'D'));
    }

    public static function utcDate(int $year, int $month, int $day): DateTimeImmutable
    {
        return (new DateTimeImmutable('1970-01-01 00:00:00', new DateTimeZone('UTC')))
            ->setDate($year, $month, $day);
    }
}
