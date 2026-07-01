<?php

declare(strict_types=1);

namespace Introibo\Core\Temporal;

use InvalidArgumentException;

/**
 * A liturgical season (tempus) of the traditional Roman year, as divided by the
 * 1962 calendar.
 *
 * The season is a structural feature of the temporal cycle — it is reported by
 * the office of the day and consumed by both temporal fill and the output
 * contract, so it needs one stable machine name rather than free strings.
 *
 * The traditional year has eight seasons. Two of them are easily confused with
 * their neighbours and are the reason a typed season exists: Septuagesima is
 * the distinct pre-Lenten fore-season (violet, no Alleluia), part neither of
 * the Christmas cycle nor of Lent proper; and Passiontide is the last two weeks
 * of Lent — Passion Week and Holy Week — treated as its own tempus, not merely
 * "Lent".
 *
 * The set is closed for the traditional editions (v0.1.0). Later rules-families
 * that drop or rename tempora (e.g. the Novus Ordo, which keeps neither
 * Septuagesima nor Passiontide) are a separate concern.
 */
final class Season
{
    public const ADVENT = 'advent';
    public const CHRISTMASTIDE = 'christmastide';
    public const EPIPHANY = 'epiphany';
    public const SEPTUAGESIMA = 'septuagesima';
    public const LENT = 'lent';
    public const PASSIONTIDE = 'passiontide';
    public const EASTERTIDE = 'eastertide';
    public const PENTECOST = 'pentecost';

    /** @var list<string> */
    private const VALID = [
        self::ADVENT,
        self::CHRISTMASTIDE,
        self::EPIPHANY,
        self::SEPTUAGESIMA,
        self::LENT,
        self::PASSIONTIDE,
        self::EASTERTIDE,
        self::PENTECOST,
    ];

    /**
     * The coarse temporal blocks of the Proper of Time, each mapped to the
     * season it belongs to. This is the seam the temporal factory feeds: a
     * block is a contiguous named stretch of the temporal cycle, and several
     * blocks may share one season — Passion Week and Holy Week are both
     * Passiontide.
     *
     * @var array<string, string>
     */
    private const BLOCK_SEASONS = [
        'advent' => self::ADVENT,
        'christmastide' => self::CHRISTMASTIDE,
        'time-after-epiphany' => self::EPIPHANY,
        'septuagesima' => self::SEPTUAGESIMA,
        'lent' => self::LENT,
        'passiontide' => self::PASSIONTIDE,
        'holy-week' => self::PASSIONTIDE,
        'eastertide' => self::EASTERTIDE,
        'time-after-pentecost' => self::PENTECOST,
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
                'Unknown liturgical season "%s"; valid values: %s',
                $value,
                implode(', ', self::VALID)
            ));
        }

        return new self($value);
    }

    /**
     * Map a coarse temporal block — a named stretch of the Proper of Time — to
     * the season it belongs to.
     *
     * @throws InvalidArgumentException if the block is not a known temporal block
     */
    public static function forBlock(string $block): self
    {
        if (!isset(self::BLOCK_SEASONS[$block])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown temporal block "%s"; valid blocks: %s',
                $block,
                implode(', ', array_keys(self::BLOCK_SEASONS))
            ));
        }

        return new self(self::BLOCK_SEASONS[$block]);
    }

    public static function advent(): self
    {
        return new self(self::ADVENT);
    }

    public static function christmastide(): self
    {
        return new self(self::CHRISTMASTIDE);
    }

    public static function epiphany(): self
    {
        return new self(self::EPIPHANY);
    }

    public static function septuagesima(): self
    {
        return new self(self::SEPTUAGESIMA);
    }

    public static function lent(): self
    {
        return new self(self::LENT);
    }

    public static function passiontide(): self
    {
        return new self(self::PASSIONTIDE);
    }

    public static function eastertide(): self
    {
        return new self(self::EASTERTIDE);
    }

    public static function pentecost(): self
    {
        return new self(self::PENTECOST);
    }

    /** The stable machine name used in output. */
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
