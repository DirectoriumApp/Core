<?php

declare(strict_types=1);

namespace Directorium\Core\Attribute;

use InvalidArgumentException;

/**
 * A liturgical colour from the traditional Roman set: white, red, green,
 * violet, black, or rose.
 *
 * Colour is a PER-EDITION attribute, never part of an observance's identity:
 * the same subject can wear a different colour across editions, and it is
 * borne per celebration *element* rather than per day (see {@see ElementColour}).
 * A colour is therefore constructed from an edition's attribute data, not
 * parsed from an identifier.
 *
 * The set is treated as closed for the traditional editions. Rose is included
 * here as an addressable value (output may render it), but a day is never
 * *stored* as rose: Gaudete and Laetare are violet with rose permitted — see
 * {@see ElementColour}.
 */
final class Colour
{
    public const WHITE = 'white';
    public const RED = 'red';
    public const GREEN = 'green';
    public const VIOLET = 'violet';
    public const BLACK = 'black';
    public const ROSE = 'rose';

    /** @var list<string> */
    private const VALID = [
        self::WHITE,
        self::RED,
        self::GREEN,
        self::VIOLET,
        self::BLACK,
        self::ROSE,
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
                'Unknown liturgical colour "%s"; valid values: %s',
                $value,
                implode(', ', self::VALID)
            ));
        }

        return new self($value);
    }

    public static function white(): self
    {
        return new self(self::WHITE);
    }

    public static function red(): self
    {
        return new self(self::RED);
    }

    public static function green(): self
    {
        return new self(self::GREEN);
    }

    public static function violet(): self
    {
        return new self(self::VIOLET);
    }

    public static function black(): self
    {
        return new self(self::BLACK);
    }

    public static function rose(): self
    {
        return new self(self::ROSE);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isViolet(): bool
    {
        return $this->value === self::VIOLET;
    }

    public function isRose(): bool
    {
        return $this->value === self::ROSE;
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
