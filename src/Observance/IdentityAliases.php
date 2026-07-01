<?php

declare(strict_types=1);

namespace Introibo\Core\Observance;

/**
 * Non-identity relationships an observance's ID participates in.
 *
 * - `secondaryFacet` — genuine double-identity days: one canonical id plus its
 *   other liturgical address (e.g. Low Sunday is also the Octave Day of Easter).
 *   This is NOT the same as two encodings of one identity; it records that a
 *   single day legitimately bears more than one liturgical identity.
 * - `splitFrom` / `mergedInto` — identity lineage, recorded rather than ever
 *   mutating or reusing an id.
 * - `externalUrns` — mappings to identifiers in other systems (for the stable
 *   cross-system export).
 */
final class IdentityAliases
{
    /** @var list<ObservanceId> */
    private array $secondaryFacet;

    private ?ObservanceId $splitFrom;

    private ?ObservanceId $mergedInto;

    /** @var list<string> */
    private array $externalUrns;

    /**
     * @param list<ObservanceId> $secondaryFacet
     * @param list<string>       $externalUrns
     */
    public function __construct(
        array $secondaryFacet = [],
        ?ObservanceId $splitFrom = null,
        ?ObservanceId $mergedInto = null,
        array $externalUrns = []
    ) {
        $this->secondaryFacet = array_values($secondaryFacet);
        $this->splitFrom = $splitFrom;
        $this->mergedInto = $mergedInto;
        $this->externalUrns = array_values($externalUrns);
    }

    public static function none(): self
    {
        return new self();
    }

    /** @return list<ObservanceId> */
    public function secondaryFacet(): array
    {
        return $this->secondaryFacet;
    }

    public function splitFrom(): ?ObservanceId
    {
        return $this->splitFrom;
    }

    public function mergedInto(): ?ObservanceId
    {
        return $this->mergedInto;
    }

    /** @return list<string> */
    public function externalUrns(): array
    {
        return $this->externalUrns;
    }

    public function isEmpty(): bool
    {
        return $this->secondaryFacet === []
            && $this->splitFrom === null
            && $this->mergedInto === null
            && $this->externalUrns === [];
    }
}
