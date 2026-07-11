<?php

declare(strict_types=1);

namespace Directorium\Core\Observance;

use InvalidArgumentException;

/**
 * The temporal anchor family — how the resolver computes a temporale day's date.
 *
 * Every family names a genuine computation, so it honestly routes date
 * arithmetic:
 *
 * - `paschal`        — signed offset from Easter (Septuagesima → Pentecost octave, Triduum, …).
 * - `advent`         — Advent Sundays/ferias counted toward Christmas.
 * - `christmas`      — Christmastide counted forward from 25 December.
 * - `epiphany`       — the resumed-green Sundays/ferias after Epiphany.
 * - `ordinary-time`  — the Novus-Ordo `tempus per annum`: its two green blocks and the
 *                      single 1–34 week series counted backward from Christ the King. It is
 *                      neither `paschal` nor `epiphany` (it spans a Baptism-to-Lent block and a
 *                      Pentecost-to-Advent block under one count), so it is its own family;
 *                      only the Novus-Ordo cycle mints it. Additive with the `ordinary-time`
 *                      season token (docs/design/novus-ordo-calendar-model.md).
 * - `civil-fixed`    — a true absolute civil date (Christmas Day, Epiphany, Major Rogation).
 * - `month-computed` — anchored to a civil-month landmark but computed (e.g. September Embers).
 *
 * It is the first body segment of a `temporale` {@see ObservanceId}. (Sanctorale
 * and votive identifiers have no anchor family.)
 */
final class AnchorFamily
{
    public const PASCHAL = 'paschal';
    public const ADVENT = 'advent';
    public const CHRISTMAS = 'christmas';
    public const EPIPHANY = 'epiphany';
    public const ORDINARY_TIME = 'ordinary-time';
    public const CIVIL_FIXED = 'civil-fixed';
    public const MONTH_COMPUTED = 'month-computed';

    /** @var list<string> */
    private const VALID = [
        self::PASCHAL,
        self::ADVENT,
        self::CHRISTMAS,
        self::EPIPHANY,
        self::ORDINARY_TIME,
        self::CIVIL_FIXED,
        self::MONTH_COMPUTED,
    ];

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        if (!in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown anchor family "%s"; valid values: %s',
                $value,
                implode(', ', self::VALID)
            ));
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
