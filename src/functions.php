<?php

declare(strict_types=1);

namespace Introibo\Core;

use DateTimeImmutable;
use Introibo\Core\Calendar\LiturgicalDay;

/**
 * The engine entry point: the liturgical day for a civil date.
 *
 * This is the public surface of the library — `day($date)` returns a
 * {@see LiturgicalDay} carrying the day's celebration, commemoration, displaced
 * and tempora roles. Until the resolver phases are wired in it returns an empty
 * placeholder day; the signature is stable, so callers can build against it now.
 *
 * A {@see DateTimeImmutable} is required rather than a mutable date so the
 * returned day cannot be changed out from under the caller.
 */
function day(DateTimeImmutable $date): LiturgicalDay
{
    return LiturgicalDay::placeholder($date);
}
