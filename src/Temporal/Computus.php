<?php

declare(strict_types=1);

namespace Directorium\Core\Temporal;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Computus: the date of Easter Sunday, the anchor of the entire temporal cycle.
 *
 * Easter is the Sunday following the ecclesiastical full moon on or after 21
 * March. This is a clean-room implementation of the anonymous Gregorian
 * algorithm (Meeus / Jones / Butcher) — pure integer arithmetic with no
 * external dependency and no reliance on the platform's `easter_days()`; the
 * validation epic cross-checks the two.
 *
 * The result is a UTC midnight {@see DateTimeImmutable}: a liturgical day is a
 * calendar date, carried with a fixed time and zone so downstream date maths is
 * free of DST surprises.
 */
final class Computus
{
    /**
     * The first year the Gregorian calendar — and therefore this computus — is
     * in force (Easter 1583 is the first Gregorian Easter). Computing Gregorian
     * Easter for an earlier year would silently return a date the calendar of
     * that year never used; the pre-reform Julian computus is a later concern.
     */
    public const GREGORIAN_REFORM_YEAR = 1583;

    private function __construct()
    {
    }

    public static function gregorianEaster(int $year): DateTimeImmutable
    {
        if ($year < self::GREGORIAN_REFORM_YEAR) {
            throw new InvalidArgumentException(sprintf(
                'Gregorian Easter is defined from %d onward; got %d '
                . '(the pre-reform Julian computus is a later concern).',
                self::GREGORIAN_REFORM_YEAR,
                $year
            ));
        }

        // Anonymous Gregorian algorithm (Meeus / Jones / Butcher).
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return (new DateTimeImmutable('1970-01-01 00:00:00', new DateTimeZone('UTC')))
            ->setDate($year, $month, $day);
    }
}
