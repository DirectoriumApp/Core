<?php

declare(strict_types=1);

namespace Introibo\Core\Observance;

use InvalidArgumentException;

/**
 * The liturgical rite an observance belongs to — the top level of the identity.
 *
 * The rite is the leading segment of every {@see ObservanceId}. For v0.1.0 only
 * the Roman rite exists; other rites (Ambrosian, Mozarabic, Byzantine, …) are
 * reserved names to be added additively, each with its own engine and cycle
 * sub-grammar, without any change to Roman identifiers.
 */
final class Rite
{
    public const ROMAN = 'roman';

    /**
     * Rites that may currently appear in an identifier.
     *
     * @var list<string>
     */
    private const VALID = [self::ROMAN];

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        if (!in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown rite "%s"; valid values: %s',
                $value,
                implode(', ', self::VALID)
            ));
        }

        return new self($value);
    }

    public static function roman(): self
    {
        return new self(self::ROMAN);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isRoman(): bool
    {
        return $this->value === self::ROMAN;
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
