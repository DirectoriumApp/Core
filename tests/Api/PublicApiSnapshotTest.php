<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Api;

use PHPUnit\Framework\TestCase;

/**
 * The public API freeze guard (#89): the live public surface must match the committed
 * snapshot. This fails on any change to a public function, class, constant, or method
 * signature — additive changes are allowed within 1.x but must be consciously re-frozen
 * (`php bin/freeze-api-surface.php`); a removal or an incompatible signature change is a
 * breaking change forbidden before a major version. See docs/api-stability.md.
 */
final class PublicApiSnapshotTest extends TestCase
{
    public function testTheLivePublicSurfaceMatchesTheFrozenSnapshot(): void
    {
        $fixture = PublicApiSurface::fixturePath();
        self::assertFileExists($fixture, 'Run `php bin/freeze-api-surface.php` to create the snapshot.');

        self::assertSame(
            file_get_contents($fixture),
            PublicApiSurface::snapshot(),
            "The public API surface changed. If this is a reviewed, intended change, run "
            . "`php bin/freeze-api-surface.php` and commit the new snapshot — remembering that a "
            . 'removed or altered member is a breaking change (major version), not an additive one.'
        );
    }

    /** Every listed class and function actually exists — the allowlist cannot rot silently. */
    public function testEveryListedMemberExists(): void
    {
        foreach (PublicApiSurface::FUNCTIONS as $function) {
            self::assertTrue(function_exists($function), "Missing public function: $function");
        }
        foreach (PublicApiSurface::CLASSES as $class) {
            self::assertTrue(
                class_exists($class) || interface_exists($class),
                "Missing public class/interface: $class"
            );
        }
    }
}
