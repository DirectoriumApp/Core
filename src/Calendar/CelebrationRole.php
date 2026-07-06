<?php

declare(strict_types=1);

namespace Directorium\Core\Calendar;

use InvalidArgumentException;

/**
 * The role an office plays on a resolved day.
 *
 * A {@see LiturgicalDay} groups the offices in play into four roles: the
 * **celebration** (the office actually celebrated, normally one principal),
 * the **commemoration**s made within it, the **displaced** offices impeded that
 * day, and the **tempora** (the temporal office of the season, always reported
 * even when it is also the celebration or a commemoration).
 *
 * This value object names those roles so an office can be *marked* with one.
 * Deciding which office takes which role — running precedence, applying the
 * commemoration limits ({@see CommemorationLimit}), queueing transfers — is the
 * resolver's work (#29); this type only makes the roles representable.
 */
final class CelebrationRole
{
    public const CELEBRATION = 'celebration';
    public const COMMEMORATION = 'commemoration';
    public const DISPLACED = 'displaced';
    public const TEMPORA = 'tempora';

    /** @var list<string> */
    private const VALID = [
        self::CELEBRATION,
        self::COMMEMORATION,
        self::DISPLACED,
        self::TEMPORA,
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
                'Unknown celebration role "%s"; valid values: %s',
                $value,
                implode(', ', self::VALID)
            ));
        }

        return new self($value);
    }

    public static function celebration(): self
    {
        return new self(self::CELEBRATION);
    }

    public static function commemoration(): self
    {
        return new self(self::COMMEMORATION);
    }

    public static function displaced(): self
    {
        return new self(self::DISPLACED);
    }

    public static function tempora(): self
    {
        return new self(self::TEMPORA);
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
