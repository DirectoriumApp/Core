<?php

declare(strict_types=1);

namespace Introibo\Core\Attribute;

use InvalidArgumentException;

/**
 * The native rank token of a pre-1960 rubric edition, retained verbatim for
 * fidelity and round-trip.
 *
 * Normalizing these legacy ranks onto the four {@see RankClass} values is
 * lossy-upward (several tokens collapse to one class), so the original token is
 * kept alongside the normalized class in an edition's attribute record. The
 * remap itself is a data-load concern of the edition attribute layer (the 1954 /
 * 1955 engines, Core v0.3.0), not of this value object.
 */
final class LegacyRank
{
    public const DUPLEX_I_CLASSIS = 'duplex-i-classis';
    public const DUPLEX_II_CLASSIS = 'duplex-ii-classis';
    public const DUPLEX_MAIUS = 'duplex-maius';
    public const DUPLEX = 'duplex';
    public const SEMIDUPLEX = 'semiduplex';
    public const SIMPLEX = 'simplex';
    public const FERIA_MAIOR = 'feria-maior';
    public const DOMINICA_MAIOR = 'dominica-maior';
    public const DOMINICA_MINOR = 'dominica-minor';
    public const COMMEMORATIO = 'commemoratio';

    /** @var list<string> */
    private const VALID = [
        self::DUPLEX_I_CLASSIS,
        self::DUPLEX_II_CLASSIS,
        self::DUPLEX_MAIUS,
        self::DUPLEX,
        self::SEMIDUPLEX,
        self::SIMPLEX,
        self::FERIA_MAIOR,
        self::DOMINICA_MAIOR,
        self::DOMINICA_MINOR,
        self::COMMEMORATIO,
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
                'Unknown legacy rank "%s"; valid values: %s',
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
