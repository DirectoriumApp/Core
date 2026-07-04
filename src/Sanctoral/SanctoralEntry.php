<?php

declare(strict_types=1);

namespace Introibo\Core\Sanctoral;

use Introibo\Core\Attribute\ElementColour;
use Introibo\Core\Attribute\LegacyRank;
use Introibo\Core\Attribute\RankClass;
use Introibo\Core\Citation\CitationSet;
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
 * preceding day. Trailing optionals let later issues add data, not constructor
 * churn: {@see vigilOfId} arrived with #27 vigils, and {@see citations} arrives
 * with the cited corpus (#41) — the {@see CitationSet} the corpus generator (#38)
 * attaches per datum, carried here ready for the output contract's reserved
 * `citations` slot to surface in a later issue. See
 * docs/design/sanctoral-overlay-model.md.
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

    private CitationSet $citations;

    private ?LegacyRank $legacyRank;

    public function __construct(
        int $month,
        int $day,
        Observance $identity,
        RankClass $rank,
        ElementColour $colour,
        ?ObservanceId $vigilOfId = null,
        ?CitationSet $citations = null,
        ?LegacyRank $legacyRank = null
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
        $this->citations = $citations ?? CitationSet::empty();
        $this->legacyRank = $legacyRank;
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

    /** The provenance citations for this entry's fields; empty for uncited sources (the seed). */
    public function citations(): CitationSet
    {
        return $this->citations;
    }

    /**
     * The native pre-1960 grade token (duplex/semiduplex/simplex…) for a legacy edition,
     * or null under the 1960 rank scheme. Carried alongside the normalized {@see RankClass}
     * so the 1954/1955 precedence engines can order the fine grades the four classes collapse.
     */
    public function legacyRank(): ?LegacyRank
    {
        return $this->legacyRank;
    }

    public function isVigil(): bool
    {
        return $this->vigilOfId !== null;
    }
}
