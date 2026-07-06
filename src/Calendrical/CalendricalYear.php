<?php

declare(strict_types=1);

namespace Directorium\Core\Calendrical;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Temporal\Computus;
use InvalidArgumentException;

/**
 * The traditional calendrical numbers of a year — the cyclic figures printed at
 * the head of an ordo or in the front matter of the Martyrologium Romanum: the
 * Golden Number, Epact, Solar Cycle, Dominical Letter(s), and Roman Indiction.
 *
 * These are edition-invariant facts of the Gregorian reckoning, orthogonal to
 * the liturgical cycle: they depend on the year alone, never on which feast or
 * rubric system falls on a day. This is a clean-room implementation of the
 * standard computus arithmetic (pure integer maths; the same lineage as
 * {@see \Directorium\Core\Temporal\Computus}), with the century corrections
 * phased so the epact table matches the printed sources across the reform's
 * solar and lunar equations.
 *
 * The lunar age — the one figure that varies within a year — is a per-date
 * attribute and lives in {@see LunarAge}, which reads this year's epact.
 */
final class CalendricalYear
{
    /** A→G assigned to 1 Jan…7 Jan; the Sundays of the year all carry one letter. */
    private const DOMINICAL_LETTERS = 'ABCDEFG';

    private int $year;

    private int $goldenNumber;

    private int $epact;

    private int $solarCycle;

    private int $romanIndiction;

    private string $dominicalLetter;

    private function __construct(
        int $year,
        int $goldenNumber,
        int $epact,
        int $solarCycle,
        int $romanIndiction,
        string $dominicalLetter
    ) {
        $this->year = $year;
        $this->goldenNumber = $goldenNumber;
        $this->epact = $epact;
        $this->solarCycle = $solarCycle;
        $this->romanIndiction = $romanIndiction;
        $this->dominicalLetter = $dominicalLetter;
    }

    /**
     * The calendrical block for a Gregorian year (1583 onward — before the reform
     * the Julian numbers differ and are a later concern, exactly as with the
     * paschal computus).
     */
    public static function forYear(int $year): self
    {
        if ($year < Computus::GREGORIAN_REFORM_YEAR) {
            throw new InvalidArgumentException(sprintf(
                'The Gregorian calendrical numbers are defined from %d onward; got %d '
                . '(the pre-reform Julian reckoning is a later concern).',
                Computus::GREGORIAN_REFORM_YEAR,
                $year
            ));
        }

        return new self(
            $year,
            self::computeGoldenNumber($year),
            self::computeEpact($year),
            self::computeSolarCycle($year),
            self::computeRomanIndiction($year),
            self::computeDominicalLetter($year)
        );
    }

    public function year(): int
    {
        return $this->year;
    }

    /**
     * The Golden Number, 1–19: the year's place in the 19-year Metonic lunar
     * cycle, the key into the epact and the paschal moon.
     */
    public function goldenNumber(): int
    {
        return $this->goldenNumber;
    }

    /**
     * The Epact, 0–29: the age of the ecclesiastical moon on 1 January, whence
     * the whole year's lunar calendar is reckoned. Traditionally the value 0 is
     * printed as an asterisk (`*`); see {@see epactLabel()}.
     */
    public function epact(): int
    {
        return $this->epact;
    }

    /** The Epact as printed: `*` for 0 (i.e. 30), otherwise the number. */
    public function epactLabel(): string
    {
        return $this->epact === 0 ? '*' : (string) $this->epact;
    }

    /** The Solar Cycle, 1–28: the year's place in the 28-year cycle of weekday↔date. */
    public function solarCycle(): int
    {
        return $this->solarCycle;
    }

    /** The Roman Indiction, 1–15: the year's place in the 15-year fiscal cycle. */
    public function romanIndiction(): int
    {
        return $this->romanIndiction;
    }

    /**
     * The Dominical Letter(s): the letter A–G marking the Sundays of the year.
     * A leap year carries two — the first for January–February, the second (one
     * earlier in the alphabet) for the rest of the year after the bissextile day
     * — rendered as a two-letter string, e.g. `GF` for 2024.
     */
    public function dominicalLetter(): string
    {
        return $this->dominicalLetter;
    }

    /** True in a leap year, when {@see dominicalLetter()} carries two letters. */
    public function hasTwoDominicalLetters(): bool
    {
        return strlen($this->dominicalLetter) === 2;
    }

    public function equals(self $other): bool
    {
        return $this->year === $other->year;
    }

    private static function computeGoldenNumber(int $year): int
    {
        return ($year % 19) + 1;
    }

    /**
     * The Gregorian epact by the Clavian computus. The base advance is 11 per
     * Golden Number (the Metonic remainder); the solar equation removes the leap
     * days the Gregorian century rule drops, and the lunar equation applies the
     * eight Metonic corrections spread over 2500 years. The century terms are
     * phased so the epact holds constant across a leap century (no spurious step
     * at 2000) and steps at the true correction years (1700/1800/1900/2100…).
     */
    private static function computeEpact(int $year): int
    {
        $century = intdiv($year, 100);

        $epact = (
            11 * ($year % 19)
            + intdiv(8 * $century + 5, 25)
            + intdiv($century, 4)
            - $century
            + 8
        ) % 30;

        return $epact < 0 ? $epact + 30 : $epact;
    }

    private static function computeSolarCycle(int $year): int
    {
        return (($year + 8) % 28) + 1;
    }

    private static function computeRomanIndiction(int $year): int
    {
        return (($year + 2) % 15) + 1;
    }

    private static function computeDominicalLetter(int $year): string
    {
        $jan1 = (new DateTimeImmutable('1970-01-01 00:00:00', new DateTimeZone('UTC')))
            ->setDate($year, 1, 1);
        // 0 = Sunday … 6 = Saturday.
        $weekday = (int) $jan1->format('w');

        // The letter on the Sundays: A if 1 Jan is Sunday, B if Saturday, … G if Monday.
        $index = ((7 - $weekday) % 7) + 1;
        // In a common year this single letter governs all Sundays; in a leap year it
        // governs January–February, up to the bissextile day.
        $januaryToFebruary = self::DOMINICAL_LETTERS[$index - 1];

        if (!self::isLeapYear($year)) {
            return $januaryToFebruary;
        }

        // After the doubled 24 February the letters fall back one place, so March
        // onward carries the letter one earlier in the alphabet, e.g. 2024 = GF.
        $marchToDecember = self::DOMINICAL_LETTERS[($index - 2 + 7) % 7];

        return $januaryToFebruary . $marchToDecember;
    }

    private static function isLeapYear(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;
    }
}
