<?php

declare(strict_types=1);

namespace Directorium\Core\Precedence;

use InvalidArgumentException;

/**
 * The fate of the office that loses an occurrence — when two offices fall on one
 * day, the higher (by {@see PrecedenceTier}) is celebrated and the loser is
 * resolved to one of these.
 *
 *  - **commemorate** — kept as a commemoration within the celebrated office;
 *  - **transfer** — moved to another day (only first-class feasts; the office
 *    is preserved so the transfer queue (#34) can re-place it);
 *  - **omit** — dropped entirely this year (no commemoration, no transfer).
 *
 * Which outcome applies is the edition's decision ({@see PrecedenceRules}); this
 * value object only names the three.
 */
final class OccurrenceOutcome
{
    public const COMMEMORATE = 'commemorate';
    public const TRANSFER = 'transfer';
    public const OMIT = 'omit';

    /** @var list<string> */
    private const VALID = [
        self::COMMEMORATE,
        self::TRANSFER,
        self::OMIT,
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
                'Unknown occurrence outcome "%s"; valid values: %s',
                $value,
                implode(', ', self::VALID)
            ));
        }

        return new self($value);
    }

    public static function commemorate(): self
    {
        return new self(self::COMMEMORATE);
    }

    public static function transfer(): self
    {
        return new self(self::TRANSFER);
    }

    public static function omit(): self
    {
        return new self(self::OMIT);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isTransfer(): bool
    {
        return $this->value === self::TRANSFER;
    }

    public function isCommemoration(): bool
    {
        return $this->value === self::COMMEMORATE;
    }

    public function isOmission(): bool
    {
        return $this->value === self::OMIT;
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
