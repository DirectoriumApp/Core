<?php

declare(strict_types=1);

namespace Introibo\Core\Overlay;

use RuntimeException;

/**
 * Raised when a particular-calendar overlay cannot be applied deterministically:
 * two operations target the same feast, or an operation's precondition fails
 * (re-ranking or suppressing a feast absent from the base calendar, or adding one
 * already present). An overlay is data authored against a known universal
 * calendar, so a conflict is a data error to fix, never a resolvable ambiguity.
 */
final class OverlayConflict extends RuntimeException
{
}
