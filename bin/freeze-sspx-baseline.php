<?php

/**
 * Regenerate the SSPX class-difference baseline (#45 / #49 / #80).
 *
 * The baseline at tests/Validation/fixtures/sspx/sspx-differences.ndjson pins the
 * current set of engine-under-the-SSPX-overlay ↔ SSPX class mismatches — see
 * {@see SspxOracle}. The validation test asserts the live mismatches equal it exactly,
 * so a regression and a drift both fail until reviewed. The overlay (#76/#80) conforms
 * on every FSSPX particular, so the baseline is the tracked *residual*: corroborated
 * base-1962 ranks the base engine still gets wrong, and the two SSPX divergences the
 * fixed-date overlay does not model. Run this only after a reviewed change to the
 * resolved calendar or a refreshed fixture, then commit the regenerated baseline
 * alongside that change.
 *
 * Usage: php bin/freeze-sspx-baseline.php
 */

declare(strict_types=1);

use Directorium\Core\Tests\Validation\SspxOracle;

require dirname(__DIR__) . '/vendor/autoload.php';

$path = SspxOracle::baselinePath();
$rows = SspxOracle::divergences();
file_put_contents($path, SspxOracle::freezeText());

fwrite(STDOUT, sprintf('Froze %d SSPX class differences to %s%s', count($rows), $path, PHP_EOL));
