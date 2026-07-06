<?php

/**
 * Regenerate the golden-year digest fixture (#365).
 *
 * The fixture at tests/Golden/resolve-year-digests.ndjson freezes the engine's
 * resolved liturgy for 1584–2200 so the data-for-code swaps (#41 sanctoral, #42
 * temporal, #43 precedence) can be proven inert — see {@see GoldenYear}. Those
 * refactors must leave the fixture *unchanged*; run this script only when a
 * change to the resolved calendar has been reviewed and accepted (for example
 * after #41 expands the sanctoral from the seed slice to the full calendar), then
 * commit the regenerated fixture with that change.
 *
 * Usage: php bin/freeze-golden-year.php
 */

declare(strict_types=1);

use Directorium\Core\Tests\Golden\GoldenYear;

require dirname(__DIR__) . '/vendor/autoload.php';

$path = GoldenYear::fixturePath();
file_put_contents($path, GoldenYear::freezeText());

$count = GoldenYear::LAST_YEAR - GoldenYear::FIRST_YEAR + 1;
fwrite(STDOUT, sprintf(
    "Froze %d years (%d–%d) to %s%s",
    $count,
    GoldenYear::FIRST_YEAR,
    GoldenYear::LAST_YEAR,
    $path,
    PHP_EOL
));
