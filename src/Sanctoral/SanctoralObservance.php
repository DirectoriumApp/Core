<?php

declare(strict_types=1);

namespace Introibo\Core\Sanctoral;

use Introibo\Core\Attribute\ElementColour;
use Introibo\Core\Attribute\RankClass;
use Introibo\Core\Calendar\RealizedObservance;
use Introibo\Core\Observance\Observance;
use Introibo\Core\Observance\ObservanceId;
use Introibo\Core\Observance\ObservanceKind;

/**
 * A sanctoral office as it is realized on one day of one edition.
 *
 * The sanctoral analogue of {@see \Introibo\Core\Temporal\TemporalObservance}:
 * it pairs the Layer-1 {@see Observance} identity shell — which, unlike a
 * temporal day, carries titular subjects and localized names — with the Layer-2
 * per-edition attributes it wears, the 1960 {@see RankClass} and the
 * {@see ElementColour}. It implements {@see RealizedObservance} so the resolver
 * (#29) treats it uniformly with temporal offices.
 *
 * Immutable: it holds only value objects.
 */
final class SanctoralObservance implements RealizedObservance
{
    private Observance $identity;

    private RankClass $rank;

    private ElementColour $colour;

    private ?ObservanceId $vigilOfId;

    public function __construct(
        Observance $identity,
        RankClass $rank,
        ElementColour $colour,
        ?ObservanceId $vigilOfId = null
    ) {
        $this->identity = $identity;
        $this->rank = $rank;
        $this->colour = $colour;
        $this->vigilOfId = $vigilOfId;
    }

    /** The Layer-1 identity shell: id, kind, titulars, and names. */
    public function identity(): Observance
    {
        return $this->identity;
    }

    /** The id of the feast this office is the vigil of, or null when it is not a vigil. */
    public function vigilOfId(): ?ObservanceId
    {
        return $this->vigilOfId;
    }

    public function isVigil(): bool
    {
        return $this->vigilOfId !== null;
    }

    public function id(): ObservanceId
    {
        return $this->identity->id();
    }

    public function kind(): ObservanceKind
    {
        return $this->identity->kind();
    }

    public function rank(): RankClass
    {
        return $this->rank;
    }

    public function colour(): ElementColour
    {
        return $this->colour;
    }

    public function latinName(): string
    {
        return $this->identity->latinName();
    }

    public function equals(self $other): bool
    {
        return $this->identity->id()->equals($other->identity->id())
            && $this->identity->kind()->equals($other->identity->kind())
            && $this->rank->equals($other->rank)
            && $this->colour->equals($other->colour)
            && $this->identity->latinName() === $other->identity->latinName()
            && $this->vigilOfMatches($other);
    }

    private function vigilOfMatches(self $other): bool
    {
        if ($this->vigilOfId === null || $other->vigilOfId === null) {
            return $this->vigilOfId === $other->vigilOfId;
        }

        return $this->vigilOfId->equals($other->vigilOfId);
    }
}
