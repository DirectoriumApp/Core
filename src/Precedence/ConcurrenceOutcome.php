<?php

declare(strict_types=1);

namespace Directorium\Core\Precedence;

use InvalidArgumentException;

/**
 * How the evening between two adjacent days is resolved — whose Vespers is said,
 * and whether the other is commemorated.
 *
 * Concurrence is the meeting of the Second Vespers of one day with the First
 * Vespers of the next. The four outcomes:
 *
 *  - **full-of-preceding** — Second Vespers of the preceding day, no commemoration;
 *  - **preceding-commem-following** — Second Vespers of the preceding, First
 *    Vespers of the following commemorated;
 *  - **following-commem-preceding** — First Vespers of the following, Second
 *    Vespers of the preceding commemorated;
 *  - **full-of-following** — First Vespers of the following day, no commemoration.
 *
 * v0.1.0 is calendar-level (no Divine Office), so the resolver reports which
 * office holds the evening and which is commemorated; the finer Table of
 * Concurrence rulings arrive with the Office layer.
 */
final class ConcurrenceOutcome
{
    public const FULL_OF_PRECEDING = 'full-of-preceding';
    public const PRECEDING_COMMEM_FOLLOWING = 'preceding-commem-following';
    public const FOLLOWING_COMMEM_PRECEDING = 'following-commem-preceding';
    public const FULL_OF_FOLLOWING = 'full-of-following';

    /** @var list<string> */
    private const VALID = [
        self::FULL_OF_PRECEDING,
        self::PRECEDING_COMMEM_FOLLOWING,
        self::FOLLOWING_COMMEM_PRECEDING,
        self::FULL_OF_FOLLOWING,
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
                'Unknown concurrence outcome "%s"; valid values: %s',
                $value,
                implode(', ', self::VALID)
            ));
        }

        return new self($value);
    }

    public static function fullOfPreceding(): self
    {
        return new self(self::FULL_OF_PRECEDING);
    }

    public static function precedingWithCommemorationOfFollowing(): self
    {
        return new self(self::PRECEDING_COMMEM_FOLLOWING);
    }

    public static function followingWithCommemorationOfPreceding(): self
    {
        return new self(self::FOLLOWING_COMMEM_PRECEDING);
    }

    public static function fullOfFollowing(): self
    {
        return new self(self::FULL_OF_FOLLOWING);
    }

    public function value(): string
    {
        return $this->value;
    }

    /** True when the evening belongs to the following day's First Vespers. */
    public function favoursFollowing(): bool
    {
        return $this->value === self::FULL_OF_FOLLOWING
            || $this->value === self::FOLLOWING_COMMEM_PRECEDING;
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
