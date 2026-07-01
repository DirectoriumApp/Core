<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Attribute;

use Introibo\Core\Attribute\Colour;
use Introibo\Core\Attribute\ElementColour;
use PHPUnit\Framework\TestCase;

final class ElementColourTest extends TestCase
{
    public function testPlainElementCarriesItsBaseColourAndNoRose(): void
    {
        $element = ElementColour::of(Colour::red());

        self::assertTrue($element->base()->equals(Colour::red()));
        self::assertFalse($element->roseAllowed());
    }

    public function testVioletWithRoseIsVioletWithTheFlagSet(): void
    {
        $element = ElementColour::violetWithRose();

        self::assertTrue($element->base()->isViolet());
        self::assertTrue($element->roseAllowed());
    }

    public function testPlainVioletDoesNotAllowRose(): void
    {
        self::assertFalse(ElementColour::of(Colour::violet())->roseAllowed());
    }

    public function testPrincipalAndCommemorationEachCarryTheirOwnColour(): void
    {
        // A day whose principal is a martyr (red) with a violet ferial
        // commemoration: each element carries its own, distinct colour.
        $principal = ElementColour::of(Colour::red());
        $commemoration = ElementColour::of(Colour::violet());

        self::assertTrue($principal->base()->equals(Colour::red()));
        self::assertTrue($commemoration->base()->equals(Colour::violet()));
        self::assertFalse($principal->equals($commemoration));
    }

    public function testEquality(): void
    {
        self::assertTrue(ElementColour::of(Colour::white())->equals(ElementColour::of(Colour::white())));
        self::assertFalse(ElementColour::of(Colour::white())->equals(ElementColour::of(Colour::green())));
        // Same violet base, but the rose flag distinguishes them.
        self::assertFalse(ElementColour::of(Colour::violet())->equals(ElementColour::violetWithRose()));
    }
}
