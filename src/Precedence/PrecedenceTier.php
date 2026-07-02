<?php

declare(strict_types=1);

namespace Introibo\Core\Precedence;

use InvalidArgumentException;

/**
 * A position in an edition's Table of Liturgical Days — the true sort key for
 * occurrence.
 *
 * The 1962 Table of Liturgical Days (Codex Rubricarum n. 91) is NOT a total
 * order on {@see \Introibo\Core\Attribute\RankClass} alone: a first-class Sunday
 * of Lent and a first-class saint's feast are both class I yet resolve
 * oppositely (the Sunday wins). So precedence sorts on this derived tier, which
 * an edition's {@see PrecedenceRules} computes from a day's kind, class, season,
 * and identity.
 *
 * A lower `ordinal` is higher precedence (ordinal 1 is the apex). Within one
 * ordinal a lower `subOrder` breaks the tie (e.g. a feast of the Lord before a
 * feast of a saint on the same line). The concrete numbers are edition data and
 * live in the rules object, never here.
 */
final class PrecedenceTier
{
    private int $ordinal;

    private int $subOrder;

    private function __construct(int $ordinal, int $subOrder)
    {
        if ($ordinal < 1) {
            throw new InvalidArgumentException(sprintf(
                'A precedence tier ordinal must be >= 1 (1 = highest), got %d.',
                $ordinal
            ));
        }

        $this->ordinal = $ordinal;
        $this->subOrder = $subOrder;
    }

    public static function of(int $ordinal, int $subOrder = 0): self
    {
        return new self($ordinal, $subOrder);
    }

    /** The line in the Table of Liturgical Days: 1 (apex) and up. */
    public function ordinal(): int
    {
        return $this->ordinal;
    }

    /** The tiebreak within a line: lower sorts first. */
    public function subOrder(): int
    {
        return $this->subOrder;
    }

    public function isHigherThan(self $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function isLowerThan(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    /** Negative if this outranks $other, positive if it yields, zero if equal. */
    public function compareTo(self $other): int
    {
        return [$this->ordinal, $this->subOrder] <=> [$other->ordinal, $other->subOrder];
    }

    public function equals(self $other): bool
    {
        return $this->ordinal === $other->ordinal && $this->subOrder === $other->subOrder;
    }
}
