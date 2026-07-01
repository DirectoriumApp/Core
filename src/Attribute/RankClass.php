<?php

declare(strict_types=1);

namespace Introibo\Core\Attribute;

use InvalidArgumentException;

/**
 * A liturgical day's class under the 1960 rank system: I, II, III, or IV
 * (class I is the highest).
 *
 * Rank is a PER-EDITION attribute, never part of an observance's identity: the
 * same subject can hold a different class across editions, so a `RankClass` is
 * constructed from an edition's attribute data — not parsed from an identifier.
 * Legacy pre-1960 vocabularies are normalized onto these four classes at
 * data-load time (see {@see LegacyRank}); the comparator only ever sees the
 * normalized class.
 */
final class RankClass
{
    /** @var array<int, string> */
    private const LABELS = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV'];

    /** Lower ordinal = higher rank (class I is 1). */
    private int $ordinal;

    private string $label;

    private function __construct(int $ordinal, string $label)
    {
        $this->ordinal = $ordinal;
        $this->label = $label;
    }

    public static function fromOrdinal(int $ordinal): self
    {
        if (!isset(self::LABELS[$ordinal])) {
            throw new InvalidArgumentException(sprintf(
                'Rank class ordinal must be 1–4 (1 = highest), got %d.',
                $ordinal
            ));
        }

        return new self($ordinal, self::LABELS[$ordinal]);
    }

    public static function classI(): self
    {
        return self::fromOrdinal(1);
    }

    public static function classII(): self
    {
        return self::fromOrdinal(2);
    }

    public static function classIII(): self
    {
        return self::fromOrdinal(3);
    }

    public static function classIV(): self
    {
        return self::fromOrdinal(4);
    }

    /** 1 (class I, highest) … 4 (class IV, lowest). */
    public function ordinal(): int
    {
        return $this->ordinal;
    }

    /** The Roman-numeral label: 'I', 'II', 'III', or 'IV'. */
    public function label(): string
    {
        return $this->label;
    }

    public function isHigherThan(self $other): bool
    {
        return $this->ordinal < $other->ordinal;
    }

    public function isLowerThan(self $other): bool
    {
        return $this->ordinal > $other->ordinal;
    }

    public function equals(self $other): bool
    {
        return $this->ordinal === $other->ordinal;
    }

    public function __toString(): string
    {
        return $this->label;
    }
}
