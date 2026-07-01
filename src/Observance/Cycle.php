<?php

declare(strict_types=1);

namespace Introibo\Core\Observance;

use InvalidArgumentException;

/**
 * The cycle of the Roman calendar an observance belongs to.
 *
 * - `temporale`  — the movable, season-anchored backbone and fixed temporal points.
 * - `sanctorale` — fixed-date saints/mysteries and special-movable sanctoral feasts.
 * - `votive`     — offices inserted only on otherwise-free days (e.g. Our Lady on Saturday).
 *
 * The cycle is the second segment of an {@see ObservanceId} and selects the body
 * sub-grammar the parser applies.
 */
final class Cycle
{
    public const TEMPORALE = 'temporale';
    public const SANCTORALE = 'sanctorale';
    public const VOTIVE = 'votive';

    /** @var list<string> */
    private const VALID = [self::TEMPORALE, self::SANCTORALE, self::VOTIVE];

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        if (!in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown cycle "%s"; valid values: %s',
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

    public function isTemporale(): bool
    {
        return $this->value === self::TEMPORALE;
    }

    public function isSanctorale(): bool
    {
        return $this->value === self::SANCTORALE;
    }

    public function isVotive(): bool
    {
        return $this->value === self::VOTIVE;
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
