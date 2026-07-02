<?php

declare(strict_types=1);

namespace Introibo\Core\Sanctoral;

use Introibo\Core\Attribute\ElementColour;
use Introibo\Core\Attribute\RankClass;
use Introibo\Core\Observance\Observance;
use Introibo\Core\Observance\ObservanceId;
use InvalidArgumentException;

/**
 * One fixed-date sanctoral entry as it lives in the corpus: the raw datum the
 * overlay loader ({@see SanctoralCalendar}) places on the calendar grid.
 *
 * An entry is edition-invariant placement + attribute data — a civil month/day,
 * the Layer-1 {@see Observance} identity, and the 1962 {@see RankClass} and
 * {@see ElementColour} it wears. A vigil additionally records the id of the
 * feast it is the vigil OF ({@see vigilOfId}); the loader then places it on the
 * preceding day. That field is a trailing optional so later issues (#27 vigils)
 * add data, not constructor churn — the shape is fixed now, and is structured so
 * the cited corpus generator (#38) can attach provenance later without changing
 * it. See docs/design/sanctoral-overlay-model.md.
 *
 * Immutable: it holds only value objects and two integers.
 */
final class SanctoralEntry
{
    private int $month;

    private int $day;

    private Observance $identity;

    private RankClass $rank;

    private ElementColour $colour;

    private ?ObservanceId $vigilOfId;

    public function __construct(
        int $month,
        int $day,
        Observance $identity,
        RankClass $rank,
        ElementColour $colour,
        ?ObservanceId $vigilOfId = null
    ) {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException(sprintf('Month must be 1-12, got %d.', $month));
        }
        if ($day < 1 || $day > 31) {
            throw new InvalidArgumentException(sprintf('Day must be 1-31, got %d.', $day));
        }

        $this->month = $month;
        $this->day = $day;
        $this->identity = $identity;
        $this->rank = $rank;
        $this->colour = $colour;
        $this->vigilOfId = $vigilOfId;
    }

    public function month(): int
    {
        return $this->month;
    }

    public function day(): int
    {
        return $this->day;
    }

    public function identity(): Observance
    {
        return $this->identity;
    }

    public function rank(): RankClass
    {
        return $this->rank;
    }

    public function colour(): ElementColour
    {
        return $this->colour;
    }

    /** The id of the feast this entry is the vigil of, or null when it is not a vigil. */
    public function vigilOfId(): ?ObservanceId
    {
        return $this->vigilOfId;
    }

    public function isVigil(): bool
    {
        return $this->vigilOfId !== null;
    }
}
