<?php

/**
 * Regenerate the missalemeum known-differences baseline (#45 / #48).
 *
 * The baseline at tests/Validation/fixtures/missalemeum/known-differences.ndjson
 * pins the current set of engine ↔ oracle divergences — see {@see MissalemeumOracle}.
 * The validation test asserts the live divergences equal it exactly, so both a
 * regression and an accuracy improvement fail until reviewed. Run this only after a
 * reviewed change to the resolved calendar (an engine/corpus fix, or a refreshed
 * fixture), then commit the regenerated baseline with that change; the diff is the
 * record of what got better or worse.
 *
 * Usage: php bin/freeze-oracle-baseline.php
 */

declare(strict_types=1);

use Directorium\Core\Tests\Validation\MissalemeumOracle;

require dirname(__DIR__) . '/vendor/autoload.php';

$path = MissalemeumOracle::baselinePath();
$rows = MissalemeumOracle::divergences();
file_put_contents($path, MissalemeumOracle::freezeText());

fwrite(STDOUT, sprintf('Froze %d known differences to %s%s', count($rows), $path, PHP_EOL));
