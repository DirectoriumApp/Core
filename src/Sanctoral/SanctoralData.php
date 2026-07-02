<?php

declare(strict_types=1);

namespace Introibo\Core\Sanctoral;

/**
 * A source of fixed-date sanctoral entries.
 *
 * The overlay loader ({@see SanctoralCalendar}) depends on this interface, not
 * on any concrete source, so the provisional {@see SeedSanctoralData} can be
 * swapped for the cited corpus generator (#38) with no change to the loader.
 * Entries are edition-invariant; the loader realizes them for a given year.
 */
interface SanctoralData
{
    /** @return list<SanctoralEntry> */
    public function entries(): array;
}
