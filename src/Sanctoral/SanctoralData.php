<?php

declare(strict_types=1);

namespace Introibo\Core\Sanctoral;

/**
 * A source of fixed-date sanctoral entries.
 *
 * The overlay loader ({@see SanctoralCalendar}) depends on this interface, not
 * on any concrete source, so the cited {@see CorpusSanctoralData} is the
 * production source while a controlled fixture can be injected in tests — with no
 * change to the loader. Entries are edition-invariant; the loader realizes them
 * for a given year.
 */
interface SanctoralData
{
    /** @return list<SanctoralEntry> */
    public function entries(): array;

    /**
     * The version stamp of this corpus of calendar data — a stable identifier of
     * the feast-list build, carrying no edition token (the same data can be
     * resolved under different editions). It is stamped onto the output
     * contract's provenance (#52) so consumers can key caches on the data build.
     */
    public function version(): string;
}
