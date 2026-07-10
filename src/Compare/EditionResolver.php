<?php

declare(strict_types=1);

namespace Directorium\Core\Compare;

use DateTimeImmutable;
use Directorium\Core\Contract\DayContract;
use Directorium\Core\Edition\RubricSystem;
use Directorium\Core\Overlay\CalendarCatalog;
use Directorium\Core\Precedence\ResolvedYear;

/**
 * Resolves an {@see EditionDayCell} for a (edition, date) pair, memoising the resolved
 * year so a range or sequence resolves each (edition, year) at most once.
 *
 * @internal shared plumbing for {@see CalendarComparator} and {@see SequenceComparator};
 *           not part of the public comparison surface
 */
final class EditionResolver
{
    private CalendarCatalog $catalog;

    /** @var array<string, ResolvedYear> keyed by "editionUrn|year" */
    private array $years = [];

    public function __construct(?CalendarCatalog $catalog = null)
    {
        $this->catalog = $catalog ?? new CalendarCatalog();
    }

    /** Normalise an edition selector (urn or friendly alias) to its stable edition urn. */
    public function normalise(string $selector): string
    {
        return RubricSystem::fromString($selector)->urn();
    }

    /**
     * The cell for a date under an edition already normalised to its urn. Resolves the
     * universal calendar under that edition (no particular-calendar overlay) and reads
     * the published day contract.
     */
    public function cell(string $editionUrn, DateTimeImmutable $date): EditionDayCell
    {
        $year = (int) $date->format('Y');
        $key = $editionUrn . '|' . $year;
        if (!isset($this->years[$key])) {
            $this->years[$key] = $this->catalog->resolver(null, $editionUrn)->resolveYear($year);
        }

        $resolved = $this->years[$key];
        $contract = DayContract::from($resolved->day($date), $resolved->provenance())->toArray();

        return EditionDayCell::fromContract($editionUrn, $contract);
    }
}
