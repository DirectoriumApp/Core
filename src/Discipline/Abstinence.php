<?php

declare(strict_types=1);

namespace Directorium\Core\Discipline;

use InvalidArgumentException;

/**
 * The abstinence a penitential day carries: complete (`full` — no flesh meat),
 * `partial` (flesh at the principal meal only), or `none`.
 *
 * These are the three grades the 1917 Code distinguishes (cann. 1251–1252). The
 * grade is comparable by severity so a day that meets more than one rule keeps the
 * strictest — a Friday in Lent is full, not partial.
 */
final class Abstinence
{
    public const NONE = 'none';

    public const PARTIAL = 'partial';

    public const FULL = 'full';

    /** @var array<string, int> Severity, so the strictest of several rules can be kept. */
    private const SEVERITY = [
        self::NONE => 0,
        self::PARTIAL => 1,
        self::FULL => 2,
    ];

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        if (!isset(self::SEVERITY[$value])) {
            throw new InvalidArgumentException(sprintf(
                'Abstinence must be one of %s, got "%s".',
                implode(', ', array_keys(self::SEVERITY)),
                $value
            ));
        }

        return new self($value);
    }

    public static function none(): self
    {
        return new self(self::NONE);
    }

    public static function partial(): self
    {
        return new self(self::PARTIAL);
    }

    public static function full(): self
    {
        return new self(self::FULL);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isStricterThan(self $other): bool
    {
        return self::SEVERITY[$this->value] > self::SEVERITY[$other->value];
    }

    /** True unless the grade is `none` — i.e. some abstinence is in force. */
    public function applies(): bool
    {
        return $this->value !== self::NONE;
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
