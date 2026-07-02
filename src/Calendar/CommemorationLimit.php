<?php

declare(strict_types=1);

namespace Introibo\Core\Calendar;

use Introibo\Core\Attribute\RankClass;
use LogicException;

/**
 * How many commemorations the 1960 rubrics admit on a day, by the day's class.
 *
 * The 1960 reform sharply cut commemorations. The counts:
 *  - **class I**: at most one, and only a *privileged* commemoration;
 *  - **class II**: at most one;
 *  - **class III and IV**: at most two.
 *
 * Some days admit none at all (the privileged octaves, the Sacred Triduum). This
 * type only makes the *counts* representable — enforcing them against a day's
 * actual commemorations, honouring the class-I "privileged only" restriction,
 * and the zero-commemoration days is the resolver's work (#29 / #36).
 */
final class CommemorationLimit
{
    /** The maximum number of commemorations admitted on a day of the given class. */
    public static function forDayClass(RankClass $dayClass): int
    {
        $maxByOrdinal = [1 => 1, 2 => 1, 3 => 2, 4 => 2];
        $ordinal = $dayClass->ordinal();

        if (!isset($maxByOrdinal[$ordinal])) {
            throw new LogicException(sprintf(
                'No commemoration limit defined for day class ordinal %d.',
                $ordinal
            ));
        }

        return $maxByOrdinal[$ordinal];
    }
}
