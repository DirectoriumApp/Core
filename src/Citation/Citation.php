<?php

declare(strict_types=1);

namespace Directorium\Core\Citation;

use InvalidArgumentException;

/**
 * A single provenance citation: the source a datum is asserted from, and an
 * optional locator within it.
 *
 * This is the PHP-side counterpart of the corpus's `cites` markers (issue #44):
 * every human-readable string and every edition-varying fact in the corpus names
 * the source it came from, so the engine can attribute what it publishes. A
 * citation reference is written `key` or `key:locator` — the head is the source
 * key that foreign-keys into the source registry (`sources.ndjson`), the tail an
 * optional locator (a page, a decree number). The split mirrors the generator's
 * gate exactly, so what the build enforces and what the engine reads agree.
 *
 * Immutable: a source key and an optional locator.
 */
final class Citation
{
    private string $sourceKey;

    private ?string $locator;

    public function __construct(string $sourceKey, ?string $locator = null)
    {
        if ($sourceKey === '') {
            throw new InvalidArgumentException('A citation requires a non-empty source key.');
        }
        if ($locator === '') {
            throw new InvalidArgumentException('A citation locator, when present, must be non-empty.');
        }

        $this->sourceKey = $sourceKey;
        $this->locator = $locator;
    }

    /**
     * Parse a citation reference `key` or `key:locator`. The source key is the
     * segment before the first colon; anything after it is the locator (kept
     * verbatim, colons and all).
     */
    public static function parse(string $ref): self
    {
        $parts = explode(':', $ref, 2);

        return new self($parts[0], $parts[1] ?? null);
    }

    /** The source-registry key this citation resolves into (e.g. `mr-1920`). */
    public function sourceKey(): string
    {
        return $this->sourceKey;
    }

    /** The locator within the source (page, decree, …), or null when unspecified. */
    public function locator(): ?string
    {
        return $this->locator;
    }

    /** The URN of the cited source — the stable identifier used across the platform. */
    public function sourceUrn(): string
    {
        return 'directorium:source:' . $this->sourceKey;
    }

    /** The canonical reference form: `key`, or `key:locator` when a locator is present. */
    public function toString(): string
    {
        return $this->locator === null ? $this->sourceKey : $this->sourceKey . ':' . $this->locator;
    }

    public function equals(self $other): bool
    {
        return $this->sourceKey === $other->sourceKey && $this->locator === $other->locator;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
