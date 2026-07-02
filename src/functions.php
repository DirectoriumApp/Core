<?php

declare(strict_types=1);

namespace Introibo\Core;

use DateTimeImmutable;
use Introibo\Core\Calendar\LiturgicalDay;
use Introibo\Core\Precedence\DayResolver;
use Introibo\Core\Precedence\ResolvedYear;

/**
 * The engine entry point: the liturgical day for a civil date.
 *
 * Returns the resolved {@see LiturgicalDay} — its celebration, commemorations,
 * displaced offices, and tempora under the 1962 rubrics. Because a transferred
 * feast makes any day depend on earlier ones, the whole civil year is resolved
 * once and memoised for the life of the process, so repeated calls within a year
 * are cheap. A {@see DateTimeImmutable} is required so the returned day cannot be
 * changed out from under the caller.
 */
function day(DateTimeImmutable $date): LiturgicalDay
{
    /** @var array<int, ResolvedYear> $resolved */
    static $resolved = [];

    $year = (int) $date->format('Y');
    if (!isset($resolved[$year])) {
        $resolved[$year] = DayResolver::for1962()->resolveYear($year);
    }

    return $resolved[$year]->day($date);
}
