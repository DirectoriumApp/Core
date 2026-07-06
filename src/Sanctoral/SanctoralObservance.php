<?php

declare(strict_types=1);

namespace Directorium\Core\Sanctoral;

use Directorium\Core\Attribute\ElementColour;
use Directorium\Core\Attribute\LegacyRank;
use Directorium\Core\Attribute\RankClass;
use Directorium\Core\Calendar\RealizedObservance;
use Directorium\Core\Observance\Observance;
use Directorium\Core\Observance\ObservanceId;
use Directorium\Core\Observance\ObservanceKind;

/**
 * A sanctoral office as it is realized on one day of one edition.
 *
 * The sanctoral analogue of {@see \Directorium\Core\Temporal\TemporalObservance}:
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

    private ?LegacyRank $legacyRank;

    private ?ObservanceId $octaveOfId;

    public function __construct(
        Observance $identity,
        RankClass $rank,
        ElementColour $colour,
        ?ObservanceId $vigilOfId = null,
        ?LegacyRank $legacyRank = null,
        ?ObservanceId $octaveOfId = null
    ) {
        $this->identity = $identity;
        $this->rank = $rank;
        $this->colour = $colour;
        $this->vigilOfId = $vigilOfId;
        $this->legacyRank = $legacyRank;
        $this->octaveOfId = $octaveOfId;
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

    /** The native pre-1960 grade token for a legacy edition, or null under the 1960 rank scheme. */
    public function legacyRank(): ?LegacyRank
    {
        return $this->legacyRank;
    }

    /**
     * The id of the feast whose octave this office belongs to — a day within the
     * octave or the octave day — or null when it is not part of an octave. The
     * {@see kind()} (within-octave vs octave-day) distinguishes the two.
     */
    public function octaveOfId(): ?ObservanceId
    {
        return $this->octaveOfId;
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
            && $this->vigilOfMatches($other)
            && $this->legacyRankMatches($other)
            && $this->octaveOfMatches($other);
    }

    private function octaveOfMatches(self $other): bool
    {
        if ($this->octaveOfId === null || $other->octaveOfId === null) {
            return $this->octaveOfId === $other->octaveOfId;
        }

        return $this->octaveOfId->equals($other->octaveOfId);
    }

    private function legacyRankMatches(self $other): bool
    {
        if ($this->legacyRank === null || $other->legacyRank === null) {
            return $this->legacyRank === $other->legacyRank;
        }

        return $this->legacyRank->equals($other->legacyRank);
    }

    private function vigilOfMatches(self $other): bool
    {
        if ($this->vigilOfId === null || $other->vigilOfId === null) {
            return $this->vigilOfId === $other->vigilOfId;
        }

        return $this->vigilOfId->equals($other->vigilOfId);
    }
}
