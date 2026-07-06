<?php

declare(strict_types=1);

namespace Directorium\Core\Citation;

/**
 * The citations attached to one corpus datum, keyed by the field they justify.
 *
 * A sanctoral entry, for instance, cites its Latin name under `names.la`, its
 * rank under `rank`, its colour under `colour`, and its date under `month`/`day`
 * — one {@see Citation} per field. This immutable map is how those markers travel
 * from the corpus into the engine, ready to be surfaced on the output contract's
 * reserved `citations` slot in a later issue.
 */
final class CitationSet
{
    /** @var array<string, Citation> Field name => the citation justifying it. */
    private array $byField;

    /**
     * @param array<string, Citation> $byField
     */
    private function __construct(array $byField)
    {
        $this->byField = $byField;
    }

    /**
     * Build a set from raw `cites` markers (`field => reference`), as read from
     * the corpus records. Later fields win on collision, matching how the merged
     * identity/attribute/placement markers are layered.
     *
     * @param array<string, string> $cites
     */
    public static function fromMarkers(array $cites): self
    {
        $byField = [];
        foreach ($cites as $field => $ref) {
            $byField[$field] = Citation::parse($ref);
        }

        return new self($byField);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * A new set with the other set's citations layered over this one — the other
     * set's fields win on collision. Used when a particular-calendar overlay
     * re-cites a field it changes (a re-ranked feast's `rank` now cites the
     * overlay's source, not the universal edition's).
     */
    public function merge(self $other): self
    {
        return new self(array_merge($this->byField, $other->byField));
    }

    /** The citation justifying a field, or null when the field is uncited. */
    public function for(string $field): ?Citation
    {
        return $this->byField[$field] ?? null;
    }

    public function has(string $field): bool
    {
        return isset($this->byField[$field]);
    }

    /** @return list<string> The cited field names, in insertion order. */
    public function fields(): array
    {
        return array_keys($this->byField);
    }

    public function isEmpty(): bool
    {
        return $this->byField === [];
    }

    /** @return array<string, Citation> */
    public function all(): array
    {
        return $this->byField;
    }
}
