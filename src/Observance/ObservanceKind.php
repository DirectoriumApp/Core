<?php

declare(strict_types=1);

namespace Introibo\Core\Observance;

use InvalidArgumentException;

/**
 * The intrinsic species of an observance.
 *
 * The kind is a stable identity-level trait (a feast is a feast across editions);
 * it governs which per-edition attributes are meaningful and how precedence and
 * transfer branch. Edition-varying facts (rank, colour, octave membership) are
 * NOT expressed here — they belong to the per-edition attribute layer.
 */
final class ObservanceKind
{
    public const SUNDAY = 'sunday';
    public const FERIA = 'feria';
    public const FEAST = 'feast';
    public const SPECIAL_MOVABLE = 'special-movable';
    public const VIGIL = 'vigil';
    public const OCTAVE_DAY = 'octave-day';
    public const WITHIN_OCTAVE = 'within-octave';
    public const EMBER_DAY = 'ember-day';
    public const ROGATION_DAY = 'rogation-day';
    public const LADY_ON_SATURDAY = 'lady-on-saturday';
    public const COMMEMORATION_ONLY = 'commemoration-only';
    public const OFFICE_OF_THE_DEAD = 'office-of-the-dead';

    /** @var list<string> */
    private const VALID = [
        self::SUNDAY,
        self::FERIA,
        self::FEAST,
        self::SPECIAL_MOVABLE,
        self::VIGIL,
        self::OCTAVE_DAY,
        self::WITHIN_OCTAVE,
        self::EMBER_DAY,
        self::ROGATION_DAY,
        self::LADY_ON_SATURDAY,
        self::COMMEMORATION_ONLY,
        self::OFFICE_OF_THE_DEAD,
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
                'Unknown observance kind "%s"; valid values: %s',
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
