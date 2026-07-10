<?php

/**
 * Regenerate the frozen public-API-surface snapshot (#89).
 *
 * The fixture at tests/Api/public-api-surface.txt pins the public API — every
 * function, class, constant, and method signature a consumer builds on (see
 * {@see \Directorium\Core\Tests\Api\PublicApiSurface} and docs/api-stability.md).
 * PublicApiSnapshotTest fails if the live surface drifts from it. Run this script
 * only when an API change has been reviewed and accepted (additive within 1.x; a
 * removal or signature change is a major-version break), then commit the result.
 *
 * Usage: php bin/freeze-api-surface.php
 */

declare(strict_types=1);

use Directorium\Core\Tests\Api\PublicApiSurface;

require dirname(__DIR__) . '/vendor/autoload.php';

file_put_contents(PublicApiSurface::fixturePath(), PublicApiSurface::snapshot());
echo 'Froze the public API surface to ' . PublicApiSurface::fixturePath() . "\n";
