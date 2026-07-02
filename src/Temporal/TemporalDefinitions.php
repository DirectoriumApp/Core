<?php

declare(strict_types=1);

namespace Introibo\Core\Temporal;

use Introibo\Core\Corpus\Corpus;
use RuntimeException;

/**
 * The read seam for the edition-invariant temporal skeleton (#42): the
 * Easter-anchored day offsets and the block->season assignments, read from the
 * generated corpus.
 *
 * The date arithmetic stays in PHP — the fillers still count days from Gregorian
 * Easter — but the *facts* they count with (which anchor is how many days from
 * Easter, which season a block belongs to) are now data, not literals. The
 * {@see PaschalSkeleton} and {@see Season} read through this seam, so a later
 * rules-family can vary the skeleton without touching the engine.
 *
 * The shaped maps are memoised for the life of the process (the corpus is
 * immutable at runtime), keyed by the reader's identity.
 */
final class TemporalDefinitions
{
    /** @var array<string, array<string, int>> Easter-offset maps, keyed by corpus base. */
    private static array $offsets = [];

    /** @var array<string, array<string, string>> Block->season maps, keyed by corpus base. */
    private static array $blockSeasons = [];

    private Corpus $corpus;

    private string $cacheKey;

    public function __construct(?Corpus $corpus = null)
    {
        $this->corpus = $corpus ?? Corpus::default();
        $this->cacheKey = spl_object_hash($this->corpus);
    }

    public static function default(): self
    {
        return new self();
    }

    /**
     * The Easter offsets: anchor slot => signed days from Easter Sunday.
     *
     * @return array<string, int>
     */
    public function easterOffsets(): array
    {
        if (!isset(self::$offsets[$this->cacheKey])) {
            $map = [];
            foreach ($this->corpus->easterOffsets() as $row) {
                $slot = $row['slot'] ?? null;
                $offset = $row['offset'] ?? null;
                if (!is_string($slot) || !is_int($offset)) {
                    throw new RuntimeException('Temporal skeleton has a malformed Easter-offset row.');
                }
                $map[$slot] = $offset;
            }
            self::$offsets[$this->cacheKey] = $map;
        }

        return self::$offsets[$this->cacheKey];
    }

    /**
     * The block->season assignments: block key => season machine name.
     *
     * @return array<string, string>
     */
    public function blockSeasons(): array
    {
        if (!isset(self::$blockSeasons[$this->cacheKey])) {
            $map = [];
            foreach ($this->corpus->blockSeasons() as $row) {
                $block = $row['block'] ?? null;
                $season = $row['season'] ?? null;
                if (!is_string($block) || !is_string($season)) {
                    throw new RuntimeException('Temporal skeleton has a malformed block-season row.');
                }
                $map[$block] = $season;
            }
            self::$blockSeasons[$this->cacheKey] = $map;
        }

        return self::$blockSeasons[$this->cacheKey];
    }
}
