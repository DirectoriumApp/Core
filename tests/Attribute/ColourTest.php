<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Attribute;

use Directorium\Core\Attribute\Colour;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ColourTest extends TestCase
{
    /**
     * @dataProvider colours
     */
    public function testFactoryValueAndRoundTrip(string $value, Colour $colour): void
    {
        self::assertSame($value, $colour->value());
        self::assertSame($value, (string) $colour);
        self::assertTrue(Colour::fromString($value)->equals($colour));
    }

    /**
     * @return array<string, array{string, Colour}>
     */
    public function colours(): array
    {
        return [
            'white' => [Colour::WHITE, Colour::white()],
            'red' => [Colour::RED, Colour::red()],
            'green' => [Colour::GREEN, Colour::green()],
            'violet' => [Colour::VIOLET, Colour::violet()],
            'black' => [Colour::BLACK, Colour::black()],
            'rose' => [Colour::ROSE, Colour::rose()],
        ];
    }

    public function testFromStringRejectsUnknownColour(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Colour::fromString('gold');
    }

    public function testFromStringIsCaseSensitive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Colour::fromString('White');
    }

    public function testEquality(): void
    {
        self::assertTrue(Colour::violet()->equals(Colour::fromString(Colour::VIOLET)));
        self::assertFalse(Colour::violet()->equals(Colour::rose()));
    }

    public function testVioletAndRosePredicates(): void
    {
        self::assertTrue(Colour::violet()->isViolet());
        self::assertFalse(Colour::violet()->isRose());
        self::assertTrue(Colour::rose()->isRose());
        self::assertFalse(Colour::rose()->isViolet());
        self::assertFalse(Colour::white()->isViolet());
    }
}
