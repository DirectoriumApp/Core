<?php

declare(strict_types=1);

namespace Directorium\Core\Precedence;

use InvalidArgumentException;

/**
 * A position in an edition's Table of Liturgical Days — the true sort key for
 * occurrence.
 *
 * The 1962 Table of Liturgical Days (Codex Rubricarum n. 91) is NOT a total
 * order on {@see \Directorium\Core\Attribute\RankClass} alone: a first-class Sunday
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

    private ?string $selector;

    private ?int $line;

    private function __construct(int $ordinal, int $subOrder, ?string $selector, ?int $line)
    {
        if ($ordinal < 1) {
            throw new InvalidArgumentException(sprintf(
                'A precedence tier ordinal must be >= 1 (1 = highest), got %d.',
                $ordinal
            ));
        }

        $this->ordinal = $ordinal;
        $this->subOrder = $subOrder;
        $this->selector = $selector;
        $this->line = $line;
    }

    /**
     * A tier. The optional `selector` (its name in the edition's table) and `line`
     * (its line in the Table of Liturgical Days, n. 91) are provenance the resolution
     * trace (#233) reports; they never affect the sort — only `ordinal`/`subOrder` do.
     */
    public static function of(int $ordinal, int $subOrder = 0, ?string $selector = null, ?int $line = null): self
    {
        return new self($ordinal, $subOrder, $selector, $line);
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

    /** The tier's name in the edition's table (e.g. `first-sunday`), or null. */
    public function selector(): ?string
    {
        return $this->selector;
    }

    /** The tier's line in the n. 91 Table of Liturgical Days, or null. */
    public function line(): ?int
    {
        return $this->line;
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
