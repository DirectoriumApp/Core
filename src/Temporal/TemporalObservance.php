<?php

declare(strict_types=1);

namespace Directorium\Core\Temporal;

use Directorium\Core\Attribute\ElementColour;
use Directorium\Core\Attribute\RankClass;
use Directorium\Core\Calendar\RealizedObservance;
use Directorium\Core\Observance\ObservanceId;
use Directorium\Core\Observance\ObservanceKind;
use InvalidArgumentException;

/**
 * A temporal office as it is realized on one day of one edition.
 *
 * The temporal cycle is filled block by block; each filler answers, for every
 * day it owns, "which temporal office is celebrated, and how is it realized this
 * year?" This value object is that answer: it pairs the day's Layer-1 identity
 * (its {@see ObservanceId} and intrinsic {@see ObservanceKind}) with the Layer-2
 * per-edition realization it carries — the {@see Season}, the 1960 {@see RankClass},
 * and the {@see ElementColour} — plus the Latin display name (the invariant `la`
 * label, as required of every observance).
 *
 * It is deliberately not the sanctoral {@see \Directorium\Core\Observance\Observance}
 * shell: a temporal day has no titular in the sanctoral sense, so its identity is
 * carried structurally by the id alone. Bridging temporal observances into the
 * {@see \Directorium\Core\Calendar\LiturgicalDay} aggregate is a later concern
 * (precedence #29 / output contract #52). See docs/design/temporal-fill-model.md.
 *
 * Immutable: it holds only value objects and an invariant string name.
 */
final class TemporalObservance implements RealizedObservance
{
    private ObservanceId $id;

    private ObservanceKind $kind;

    private Season $season;

    private RankClass $rank;

    private ElementColour $colour;

    private string $latinName;

    public function __construct(
        ObservanceId $id,
        ObservanceKind $kind,
        Season $season,
        RankClass $rank,
        ElementColour $colour,
        string $latinName
    ) {
        if ($latinName === '') {
            throw new InvalidArgumentException(
                'A temporal observance requires a Latin name as the invariant fallback.'
            );
        }

        $this->id = $id;
        $this->kind = $kind;
        $this->season = $season;
        $this->rank = $rank;
        $this->colour = $colour;
        $this->latinName = $latinName;
    }

    public function id(): ObservanceId
    {
        return $this->id;
    }

    public function kind(): ObservanceKind
    {
        return $this->kind;
    }

    public function season(): Season
    {
        return $this->season;
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
        return $this->latinName;
    }

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id)
            && $this->kind->equals($other->kind)
            && $this->season->equals($other->season)
            && $this->rank->equals($other->rank)
            && $this->colour->equals($other->colour)
            && $this->latinName === $other->latinName;
    }
}
