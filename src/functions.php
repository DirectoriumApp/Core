<?php

declare(strict_types=1);

namespace Introibo\Core;

use DateTimeImmutable;
use Introibo\Core\Calendar\LiturgicalDay;
use Introibo\Core\Contract\DayContract;
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
    return resolvedYear((int) $date->format('Y'))->day($date);
}

/**
 * The public output contract for a civil date: the same resolved day as
 * {@see day()}, serialised to the versioned, JSON-ready structure the other
 * Introibo repos build on (see {@see DayContract} and
 * docs/design/output-contract.md).
 *
 * @return array<string, mixed>
 */
function contract(DateTimeImmutable $date): array
{
    $year = resolvedYear((int) $date->format('Y'));

    return DayContract::from($year->day($date), $year->provenance())->toArray();
}

/**
 * The resolved civil year, memoised for the life of the process.
 *
 * Shared by {@see day()} and {@see contract()} so a year is resolved at most
 * once regardless of which entry point is called.
 *
 * @internal Not part of the public contract; the stable API is day()/contract().
 */
function resolvedYear(int $year): ResolvedYear
{
    /** @var array<int, ResolvedYear> $resolved */
    static $resolved = [];

    if (!isset($resolved[$year])) {
        $resolved[$year] = DayResolver::for1962()->resolveYear($year);
    }

    return $resolved[$year];
}
