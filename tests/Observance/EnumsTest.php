<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Observance;

use Directorium\Core\Observance\AnchorFamily;
use Directorium\Core\Observance\Cycle;
use Directorium\Core\Observance\ObservanceKind;
use Directorium\Core\Observance\Rite;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EnumsTest extends TestCase
{
    public function testRiteAcceptsRomanAndReportsIt(): void
    {
        $rite = Rite::fromString(Rite::ROMAN);

        self::assertTrue($rite->isRoman());
        self::assertSame('roman', $rite->value());
        self::assertTrue($rite->equals(Rite::roman()));
    }

    public function testRiteRejectsUnknown(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Rite::fromString('byzantine');
    }

    public function testCycleHelpers(): void
    {
        self::assertTrue(Cycle::fromString(Cycle::TEMPORALE)->isTemporale());
        self::assertTrue(Cycle::fromString(Cycle::SANCTORALE)->isSanctorale());
        self::assertTrue(Cycle::fromString(Cycle::VOTIVE)->isVotive());
    }

    public function testCycleRejectsUnknown(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Cycle::fromString('proprium');
    }

    public function testAnchorFamilyAcceptsEachValue(): void
    {
        $values = ['paschal', 'advent', 'christmas', 'epiphany', 'civil-fixed', 'month-computed'];

        foreach ($values as $value) {
            self::assertSame($value, AnchorFamily::fromString($value)->value());
        }
    }

    public function testAnchorFamilyRejectsEasterOffset(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AnchorFamily::fromString('easter-offset');
    }

    public function testObservanceKindRoundTrips(): void
    {
        $kind = ObservanceKind::fromString(ObservanceKind::LADY_ON_SATURDAY);

        self::assertSame('lady-on-saturday', $kind->value());
        self::assertTrue($kind->equals(ObservanceKind::fromString('lady-on-saturday')));
    }

    public function testObservanceKindRejectsUnknown(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ObservanceKind::fromString('great-feast');
    }
}
