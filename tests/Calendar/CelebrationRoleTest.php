<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Calendar;

use Introibo\Core\Calendar\CelebrationRole;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CelebrationRoleTest extends TestCase
{
    /**
     * @dataProvider roles
     */
    public function testFactoriesAndFromStringAgree(string $value): void
    {
        self::assertSame($value, CelebrationRole::fromString($value)->value());
    }

    /**
     * @return array<string, array{string}>
     */
    public function roles(): array
    {
        return [
            'celebration' => [CelebrationRole::CELEBRATION],
            'commemoration' => [CelebrationRole::COMMEMORATION],
            'displaced' => [CelebrationRole::DISPLACED],
            'tempora' => [CelebrationRole::TEMPORA],
        ];
    }

    public function testNamedConstructorsMatchTheirConstants(): void
    {
        self::assertSame('celebration', CelebrationRole::celebration()->value());
        self::assertSame('commemoration', CelebrationRole::commemoration()->value());
        self::assertSame('displaced', CelebrationRole::displaced()->value());
        self::assertSame('tempora', CelebrationRole::tempora()->value());
    }

    public function testRejectsUnknownRole(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CelebrationRole::fromString('winner');
    }

    public function testEquals(): void
    {
        self::assertTrue(CelebrationRole::commemoration()->equals(CelebrationRole::commemoration()));
        self::assertFalse(CelebrationRole::commemoration()->equals(CelebrationRole::displaced()));
    }
}
