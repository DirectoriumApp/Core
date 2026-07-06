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

    /**
     * The ecclesiastical Paschal Full Moon: the fourteenth day of the paschal
     * lunation (Luna 14), the full moon on or after 21 March from which Easter is
     * reckoned — Easter is the first Sunday strictly after this date.
     *
     * This is the same Gregorian computus as {@see gregorianEaster()}: the moon's
     * offset from 21 March, with the two Clavian corrections that cap the paschal
     * moon at 18 April (and at 17 April for the higher golden numbers). It anchors
     * the ecclesiastical moon's age (see {@see \Directorium\Core\Calendrical\LunarAge}),
     * and the "Easter follows it" invariant is asserted in the tests.
     */
    public static function paschalFullMoon(int $year): DateTimeImmutable
    {
        if ($year < self::GREGORIAN_REFORM_YEAR) {
            throw new InvalidArgumentException(sprintf(
                'The Gregorian paschal moon is defined from %d onward; got %d '
                . '(the pre-reform Julian computus is a later concern).',
                self::GREGORIAN_REFORM_YEAR,
                $year
            ));
        }

        $a = $year % 19;
        $b = intdiv($year, 100);
        $d = intdiv($b, 4);
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        // Days from 21 March to the (uncorrected) ecclesiastical full moon, 0–29.
        $h = (19 * $a + $b - $d - $g + 15) % 30;

        // The paschal moon may not fall later than 18 April; the second rule keeps
        // the higher golden numbers off 18 April too. These are the corrections
        // folded into the Easter formula's `m` term, applied here to the moon itself.
        $offset = $h;
        if ($offset === 29) {
            $offset = 28;
        } elseif ($offset === 28 && $a >= 11) {
            $offset = 27;
        }

        // 21 March + offset, expressed as a date (offset 0 = 21 March).
        return (new DateTimeImmutable('1970-01-01 00:00:00', new DateTimeZone('UTC')))
            ->setDate($year, 3, 21)
            ->modify(sprintf('+%d days', $offset));
    }
}
