<?php

declare(strict_types=1);

namespace Directorium\Core\Compare;

/**
 * The dimensions a calendar-mode comparison diffs between editions: the feast that
 * wins the day, its class/rank, its liturgical colour, the commemorations kept, and
 * the season.
 *
 * This is the v1.0 calendar-mode taxonomy — deliberately the small, text-free set two
 * *calendar* engines can differ on — and the pattern the later rite- and office-mode
 * diff (comparison Epic #307) generalises to structural units and texts. See
 * docs/design/calendar-comparison-model.md.
 */
final class ComparisonField
{
    /** The principal celebration's stable id — which feast (or feria) wins the day. */
    public const FEAST = 'feast';

    /** The principal celebration's class/rank ordinal (I–IV; lower is higher). */
    public const RANK = 'rank';

    /** The principal celebration's base liturgical colour. */
    public const COLOUR = 'colour';

    /** The set of commemorations kept, as a sorted list of ids. */
    public const COMMEMORATIONS = 'commemorations';

    /** The day's liturgical season token (open, edition-scoped vocabulary). */
    public const SEASON = 'season';

    /**
     * The compared fields, in a stable display order.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::FEAST, self::RANK, self::COLOUR, self::COMMEMORATIONS, self::SEASON];
    }

    private function __construct()
    {
    }
}
