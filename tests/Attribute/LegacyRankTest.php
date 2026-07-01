<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Attribute;

use Introibo\Core\Attribute\LegacyRank;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LegacyRankTest extends TestCase
{
    public function testRoundTripsValidTokens(): void
    {
        $tokens = ['duplex-i-classis', 'duplex-maius', 'semiduplex', 'simplex', 'commemoratio'];

        foreach ($tokens as $token) {
            self::assertSame($token, LegacyRank::fromString($token)->value());
        }

        self::assertSame('simplex', (string) LegacyRank::fromString(LegacyRank::SIMPLEX));
    }

    public function testEquality(): void
    {
        self::assertTrue(
            LegacyRank::fromString('duplex')->equals(LegacyRank::fromString(LegacyRank::DUPLEX))
        );
        self::assertFalse(
            LegacyRank::fromString('duplex')->equals(LegacyRank::fromString('simplex'))
        );
    }

    public function testRejectsUnknownToken(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LegacyRank::fromString('totum-duplex');
    }
}
