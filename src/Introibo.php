<?php

declare(strict_types=1);

namespace Introibo\Core;

/**
 * Package facade for the Introibo liturgical engine.
 *
 * This class anchors the PSR-4 namespace for the library and serves as the
 * stable public entry point. The calendar-resolution API — most notably the
 * `day()` method — is attached here as the engine is built out (issue #13).
 *
 * The engine is clean-room: no code originates from any prior calendar project.
 */
final class Introibo
{
    /**
     * Human-readable identifier for the library.
     *
     * Present so the PSR-4 autoloader has a resolvable symbol to smoke-test
     * against before any engine behaviour exists.
     */
    public const NAME = 'introibo/core';
}
